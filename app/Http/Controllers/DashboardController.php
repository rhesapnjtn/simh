<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $rooms = Room::count();
        $roomsAvailable = Room::where('status', 'available')->count();
        $roomsOccupied = Reservation::where('status', 'checked_in')->count(); // occupied room nights today
        $roomsHousekeeping = Room::where('status', 'housekeeping')->count();
        $roomsMaintenance = Room::where('status', 'maintenance')->count();

        $totalRooms = max(1, $rooms);
        $occupancyToday = round($roomsOccupied / $totalRooms * 100, 1);

        $arrivalsToday = Reservation::where('status', 'confirmed')
            ->whereDate('check_in_date', $today)
            ->count();
        $departuresToday = Reservation::where('status', 'checked_in')
            ->whereDate('check_out_date', $today)
            ->count();

        $revenueToday = (float) Payment::whereDate('paid_at', $today)->sum('amount');
        $revenueMonth = (float) Payment::whereMonth('paid_at', $today->month)
            ->whereYear('paid_at', $today->year)
            ->sum('amount');

        $runningReservations = Reservation::active()->count();

        $revenueDaily = Payment::where('paid_at', '>=', $today->copy()->subDays(13)->startOfDay())
            ->selectRaw('DATE(paid_at) as date, SUM(amount) as total')
            ->groupByRaw('DATE(paid_at)')
            ->pluck('total', 'date');

        $revenueChart = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $key = $d->toDateString();
            $revenueChart[] = [
                'date' => $key,
                'label' => $d->format('d M'),
                'value' => round((float) ($revenueDaily[$key] ?? 0), 2),
            ];
        }

        $occupancyChart = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $count = $this->occupiedRoomsOn($d);
            $occupancyChart[] = [
                'date' => $d->toDateString(),
                'label' => $d->format('d M'),
                'value' => round($count / $totalRooms * 100, 1),
            ];
        }

        $statusBreakdown = Reservation::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $upcomingArrivals = Reservation::with(['guest', 'room'])
            ->where('status', 'confirmed')
            ->whereDate('check_in_date', '>=', $today)
            ->orderBy('check_in_date')
            ->limit(8)
            ->get()
            ->map(fn ($r) => $this->reservationSummary($r));

        $inHouse = Reservation::with(['guest', 'room'])
            ->where('status', 'checked_in')
            ->orderBy('check_out_date')
            ->limit(8)
            ->get()
            ->map(fn ($r) => $this->reservationSummary($r));

        return response()->json([
            'stats' => [
                'rooms' => $rooms,
                'rooms_available' => $roomsAvailable,
                'rooms_occupied' => $roomsOccupied,
                'rooms_housekeeping' => $roomsHousekeeping,
                'rooms_maintenance' => $roomsMaintenance,
                'occupancy_today' => $occupancyToday,
                'arrivals_today' => $arrivalsToday,
                'departures_today' => $departuresToday,
                'revenue_today' => $revenueToday,
                'revenue_month' => $revenueMonth,
                'active_reservations' => $runningReservations,
            ],
            'revenue_chart' => $revenueChart,
            'occupancy_chart' => $occupancyChart,
            'status_breakdown' => $statusBreakdown,
            'upcoming_arrivals' => $upcomingArrivals,
            'in_house' => $inHouse,
        ]);
    }

    protected function occupiedRoomsOn(Carbon $date): int
    {
        return Reservation::where('status', 'checked_in')
            ->whereDate('check_in_at', '<=', $date)
            ->whereDate('check_out_date', '>=', $date)
            ->count();
    }

    protected function reservationSummary(Reservation $r): array
    {
        return [
            'id' => $r->id,
            'code' => $r->code,
            'guest' => $r->guest?->full_name,
            'room' => $r->room?->room_number,
            'room_type' => $r->room?->roomType?->name,
            'check_in_date' => $r->check_in_date?->format('Y-m-d'),
            'check_out_date' => $r->check_out_date?->format('Y-m-d'),
            'total_amount' => (float) $r->total_amount,
            'balance' => $r->balance,
        ];
    }
}