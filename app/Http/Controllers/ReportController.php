<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function options()
    {
        return response()->json([
            'methods' => Payment::query()->select('method')->distinct()->pluck('method'),
            'min_date' => Payment::query()->min('paid_at'),
        ]);
    }

    public function revenue(Request $request)
    {
        [$from, $to] = $this->range($request);

        $payments = Payment::with(['user'])
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->orderBy('paid_at')
            ->get();

        $total = $payments->sum('amount');

        $daily = $payments->groupBy(fn ($p) => $p->paid_at->toDateString())
            ->map(fn ($group) => (float) round($group->sum('amount'), 2));

        $series = [];
        $cursor = Carbon::parse($from);
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $series[] = [
                'date' => $key,
                'label' => $cursor->format('d M'),
                'value' => (float) ($daily[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        $byMethod = $payments->groupBy('method')
            ->map(fn ($g) => (float) round($g->sum('amount'), 2))
            ->sortDesc();

        $byUser = $payments->groupBy('user_id')
            ->map(fn ($g) => ['name' => $g->first()->user?->name, 'total' => (float) round($g->sum('amount'), 2)])
            ->sortByDesc('total')
            ->take(10);

        $topGuests = Payment::query()
            ->with('reservation.guest')
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->get()
            ->groupBy('reservation.guest_id')
            ->map(fn ($g) => [
                'name' => $g->first()->reservation?->guest?->full_name,
                'total' => (float) round($g->sum('amount'), 2),
                'count' => $g->count(),
            ])
            ->reject(fn ($g) => empty($g['name']))
            ->sortByDesc('total')
            ->take(10)
            ->values();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'total' => round($total, 2),
            'count' => $payments->count(),
            'daily' => $series,
            'by_method' => $byMethod,
            'by_user' => $byUser->values(),
            'top_guests' => $topGuests,
        ]);
    }

    public function occupancy(Request $request)
    {
        [$from, $to] = $this->range($request);

        $rooms = Room::with('roomType')->get();
        $totalRooms = max(1, $rooms->count());
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

        $reservations = Reservation::where('status', Reservation::STATUS_CHECKED_IN)
            ->whereDate('check_out_date', '>', $from)
            ->where(function ($q) use ($to) {
                $q->whereNull('check_in_at')->orWhereDate('check_in_at', '<=', $to);
            })
            ->get(['id', 'room_id', 'check_in_at', 'check_out_date']);

        $daily = [];
        $occupiedByType = [];
        $cursor = Carbon::parse($from);
        $totalOccupiedNights = 0;

        while ($cursor->lte($to)) {
            $day = $cursor->toDateString();

            $occupied = $reservations->filter(function ($r) use ($day) {
                $checkIn = $r->check_in_at ? Carbon::parse($r->check_in_at)->toDateString() : $day;

                return $checkIn <= $day && $r->check_out_date->toDateString() > $day;
            })->pluck('room_id')->unique();

            $totalOccupiedNights += $occupied->count();

            $daily[] = [
                'date' => $day,
                'label' => $cursor->format('d M'),
                'available' => $totalRooms,
                'occupied' => $occupied->count(),
                'rate' => round($occupied->count() / $totalRooms * 100, 1),
            ];

            foreach ($occupied as $roomId) {
                $room = $rooms->firstWhere('id', $roomId);
                $typeId = $room?->room_type_id;
                $occupiedByType[$typeId] = ($occupiedByType[$typeId] ?? 0) + 1;
            }

            $cursor->addDay();
        }

        $byType = $rooms->groupBy('room_type_id')->map(function ($group, $typeId) use ($occupiedByType, $days) {
            $type = $group->first()->roomType;
            $typeTotal = $group->count() * $days;
            $typeOccupied = $occupiedByType[$typeId] ?? 0;

            return [
                'name' => $type?->name ?? 'Tanpa tipe',
                'rooms' => $group->count(),
                'occupied_nights' => $typeOccupied,
                'available_nights' => $typeTotal,
                'rate' => round($typeTotal > 0 ? $typeOccupied / $typeTotal * 100 : 0, 1),
            ];
        })->values();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'total_rooms' => $totalRooms,
            'days' => $days,
            'total_available_nights' => $totalRooms * $days,
            'total_occupied_nights' => $totalOccupiedNights,
            'average_occupancy' => round($totalOccupiedNights / ($totalRooms * $days) * 100, 1),
            'daily' => $daily,
            'by_type' => $byType,
        ]);
    }

    public function export(Request $request)
    {
        $type = $request->input('type', 'revenue');
        [$from, $to] = $this->range($request);

        $filename = 'laporan-'.$type.'-'.$from.'-'.$to.'.csv';

        return response()->streamDownload(function () use ($type, $from, $to) {
            $stream = fopen('php://output', 'w');

            if ($type === 'revenue') {
                fputs($stream, "\xEF\xBB\xBF");
                fputcsv($stream, ['Tanggal', 'Reservasi', 'Tamu', 'Metode', 'Referensi', 'Jumlah', 'Petugas']);
                Payment::with(['reservation.guest', 'user'])
                    ->whereDate('paid_at', '>=', $from)
                    ->whereDate('paid_at', '<=', $to)
                    ->orderBy('paid_at')
                    ->get()
                    ->each(function (Payment $p) use ($stream) {
                        fputcsv($stream, [
                            $p->paid_at->format('d M Y H:i'),
                            $p->reservation?->code,
                            $p->reservation?->guest?->full_name,
                            strtoupper(str_replace('_', ' ', $p->method)),
                            $p->reference,
                            number_format((float) $p->amount, 2, ',', '.'),
                            $p->user?->name,
                        ]);
                    });
            } else {
                fputs($stream, "\xEF\xBB\xBF");
                fputcsv($stream, ['Tanggal', 'Kamar Tersedia', 'Kamar Terisi', 'Tingkat Hunian (%)']);
                $data = $this->occupancy(request()->merge(['type' => null]));
                foreach ($data->getData()->daily as $row) {
                    fputcsv($stream, [
                        $row['date'],
                        $row['available'],
                        $row['occupied'],
                        number_format($row['rate'], 1, ',', '.'),
                    ]);
                }
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function range(Request $request): array
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return [$request->input('from'), $request->input('to')];
    }
}