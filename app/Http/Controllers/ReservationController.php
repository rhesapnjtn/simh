<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Setting;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $query = Reservation::query()
            ->with(['guest', 'room.roomType', 'user'])
            ->orderByDesc('created_at');

        $user = auth()->user();
        if (! $user->hasPermission('reservations.view') && $user->hasPermission('reservations.own')) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%'.$search.'%')
                    ->orWhereHas('guest', function ($g) use ($search) {
                        $g->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('room_type_id')) {
            $query->whereHas('room', fn ($q) => $q->where('room_type_id', $request->input('room_type_id')));
        }

        if ($request->filled('from')) {
            $query->whereDate('check_out_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('check_in_date', '<=', $request->input('to'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        $reservations = $query->paginate(12);

        return response()->json($reservations->through(fn (Reservation $r) => $this->listShape($r)));
    }

    public function pricing(Request $request)
    {
        $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults' => ['required', 'integer', 'min:1'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $roomType = RoomType::findOrFail($request->input('room_type_id'));
        $checkIn = $request->date('check_in_date');
        $checkOut = $request->date('check_out_date');
        $nights = $checkIn->diffInDays($checkOut);

        $taxRate = (float) $request->input('tax_rate', Setting::get('tax_rate', 10));

        $pricing = \App\Services\PricingService::compute(
            $nights,
            (float) $roomType->base_rate,
            (float) $roomType->extra_person_rate,
            (int) $roomType->capacity,
            (int) $request->input('adults', 1),
            (float) $request->input('discount', 0),
            $taxRate
        );

        return response()->json([
            'room_type' => ['id' => $roomType->id, 'name' => $roomType->name, 'base_rate' => (float) $roomType->base_rate],
            'nights' => $pricing['nights'],
            'subtotal' => $pricing['subtotal'],
            'extra_person_fee' => $pricing['extra_person_fee'],
            'extra_persons' => $pricing['extra_persons'],
            'discount' => (float) $request->input('discount', 0),
            'tax_rate' => $taxRate,
            'tax_amount' => $pricing['tax_amount'],
            'total' => $pricing['total'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $guestId = $data['guest_id'] ?? $this->createGuest($request)->id;
        $room = $data['room_id'] ?? null ? Room::find($data['room_id']) : null;

        $checkIn = $request->date('check_in_date');
        $checkOut = $request->date('check_out_date');
        $nights = $checkIn->diffInDays($checkOut);
        if ($nights < 1) {
            return response()->json(['message' => 'Tanggal check-out harus setelah tanggal check-in.'], 422);
        }

        if ($room) {
            $this->assertRoomAvailable($room, $checkIn, $checkOut);
        }

        $taxRate = (float) ($data['tax_rate'] ?? Setting::get('tax_rate', 10));
        $roomRate = (float) ($data['room_rate'] ?? ($room?->roomType?->base_rate ?? 0));
        $discount = (float) ($data['discount'] ?? 0);

        $reservation = new Reservation([
            'guest_id' => (int) $guestId,
            'user_id' => auth()->id(),
            'room_id' => $room?->id,
            'status' => $data['status'] ?? Reservation::STATUS_PENDING,
            'source' => $data['source'] ?? 'walk_in',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'adults' => (int) $data['adults'],
            'children' => (int) ($data['children'] ?? 0),
            'room_rate' => $roomRate,
            'discount' => $discount,
            'tax_rate' => $taxRate,
            'special_requests' => $data['special_requests'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $reservation->recalculate($room?->roomType);
        $reservation->save();

        ActivityLogService::log('create', "Reservasi {$reservation->code} dibuat untuk {$reservation->guest->full_name}.", $reservation);

        return response()->json([
            'message' => 'Reservasi berhasil dibuat.',
            'data' => $this->detailShape($reservation->load(['guest', 'room.roomType', 'user'])),
        ], 201);
    }

    public function show(Reservation $reservation)
    {
        $user = auth()->user();
        if (! $user->hasPermission('reservations.view') && $user->hasPermission('reservations.own') && $reservation->user_id !== $user->id) {
            return response()->json(['message' => 'Anda tidak memiliki izin untuk akses ini.'], 403);
        }

        $reservation->load(['guest', 'room.roomType', 'user', 'payments.user', 'charges']);
        $reservation->charges->each(fn ($c) => $c->makeHidden(['reservation_id']));

        return response()->json($this->detailShape($reservation, true));
    }

    public function update(Request $request, Reservation $reservation)
    {
        if (in_array($reservation->status, [Reservation::STATUS_CHECKED_IN, Reservation::STATUS_CHECKED_OUT])) {
            return response()->json(['message' => 'Reservasi yang sudah check-in/check-out tidak dapat diubah.'], 422);
        }

        $data = $this->validateData($request, true);

        $room = $data['room_id'] ?? null ? Room::find($data['room_id']) : $reservation->room;
        $checkIn = $request->date('check_in_date');
        $checkOut = $request->date('check_out_date');
        $nights = $checkIn->diffInDays($checkOut);
        if ($nights < 1) {
            return response()->json(['message' => 'Tanggal check-out harus setelah tanggal check-in.'], 422);
        }

        $roomChanged = $room && ($room->id !== $reservation->room_id);
        if ($roomChanged) {
            $this->assertRoomAvailable($room, $checkIn, $checkOut, $reservation->id);
        }

        $taxRate = (float) ($data['tax_rate'] ?? Setting::get('tax_rate', 10));
        $roomRate = (float) ($data['room_rate'] ?? $reservation->room_rate);

        $reservation->fill([
            'room_id' => $room?->id,
            'source' => $data['source'] ?? $reservation->source,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'adults' => (int) $data['adults'],
            'children' => (int) ($data['children'] ?? 0),
            'room_rate' => $roomRate,
            'discount' => (float) ($data['discount'] ?? 0),
            'tax_rate' => $taxRate,
            'special_requests' => $data['special_requests'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $reservation->recalculate($room?->roomType);
        $reservation->save();

        ActivityLogService::log('update', "Reservasi {$reservation->code} diperbarui.", $reservation);

        return response()->json([
            'message' => 'Reservasi berhasil diperbarui.',
            'data' => $this->detailShape($reservation->load(['guest', 'room.roomType', 'user'])),
        ]);
    }

    public function destroy(Request $request, Reservation $reservation)
    {
        if ($reservation->status === Reservation::STATUS_CHECKED_IN) {
            return response()->json(['message' => 'Tidak dapat menghapus reservasi yang sedang check-in.'], 422);
        }

        if ($reservation->payments()->exists() || Reservation::query()->where('id', $reservation->id)->whereHas('charges')->exists()) {
            return response()->json(['message' => 'Reservasi memiliki transaksi pembayaran/biaya dan tidak dapat dihapus. Batalkan saja.'], 422);
        }

        $code = $reservation->code;
        $reservation->delete();

        ActivityLogService::log('delete', "Reservasi {$code} dihapus.");

        return response()->json(['message' => 'Reservasi berhasil dihapus.']);
    }

    public function assignRoom(Request $request, Reservation $reservation)
    {
        if (! in_array($reservation->status, [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED])) {
            return response()->json(['message' => 'Kamar hanya dapat diubah pada reservasi pending/confirmed.'], 422);
        }

        $request->validate(['room_id' => ['required', 'exists:rooms,id']]);
        $room = Room::findOrFail($request->input('room_id'));

        $this->assertRoomAvailable($room, $reservation->check_in_date, $reservation->check_out_date, $reservation->id);

        $reservation->room_id = $room->id;
        $reservation->recalculate($room->roomType);
        $reservation->save();

        ActivityLogService::log('assign', "Kamar {$room->room_number} ditugaskan ke {$reservation->code}.", $reservation);

        return response()->json(['message' => 'Kamar berhasil ditugaskan.', 'data' => $this->detailShape($reservation->load(['guest', 'room.roomType']))]);
    }

    public function confirm(Reservation $reservation)
    {
        if ($reservation->status !== Reservation::STATUS_PENDING) {
            return response()->json(['message' => 'Hanya reservasi pending yang dapat dikonfirmasi.'], 422);
        }

        $reservation->update(['status' => Reservation::STATUS_CONFIRMED]);

        ActivityLogService::log('confirm', "Reservasi {$reservation->code} dikonfirmasi.", $reservation);

        return response()->json(['message' => 'Reservasi dikonfirmasi.', 'data' => $this->detailShape($reservation->load(['guest', 'room.roomType']))]);
    }

    public function checkIn(Request $request, Reservation $reservation)
    {
        if (! in_array($reservation->status, [Reservation::STATUS_CONFIRMED, Reservation::STATUS_PENDING])) {
            return response()->json(['message' => 'Reservasi tidak dapat di-check-in.'], 422);
        }

        $room = $reservation->room;
        if (! $room) {
            $request->validate(['room_id' => ['required', 'exists:rooms,id']]);
            $room = Room::findOrFail($request->input('room_id'));
            $this->assertRoomAvailable($room, $reservation->check_in_date, $reservation->check_out_date, $reservation->id);
            $reservation->room_id = $room->id;
        }

        if ($room->status === Room::STATUS_MAINTENANCE) {
            return response()->json(['message' => 'Kamar sedang dalam perawatan.'], 422);
        }

        DB::transaction(function () use ($reservation, $room, $request) {
            if ($request->filled('actual_nights') && is_numeric($request->input('actual_nights'))) {
                $nights = max(1, (int) $request->input('actual_nights'));
                $reservation->check_out_date = $reservation->check_in_date->copy()->addDays($nights);
                $reservation->recalculate($room->roomType);
            }

            $reservation->status = Reservation::STATUS_CHECKED_IN;
            $reservation->check_in_at = now();
            $reservation->save();

            $room->update(['status' => Room::STATUS_OCCUPIED]);

            ActivityLogService::log('check_in', "Tamu check-in {$reservation->code} di kamar {$room->room_number}.", $reservation);
        });

        return response()->json([
            'message' => 'Check-in berhasil.',
            'data' => $this->detailShape($reservation->fresh(['guest', 'room.roomType', 'user'])),
        ]);
    }

    public function checkOut(Request $request, Reservation $reservation)
    {
        if ($reservation->status !== Reservation::STATUS_CHECKED_IN) {
            return response()->json(['message' => 'Hanya reservasi dengan status check-in yang dapat di-check-out.'], 422);
        }

        if ($reservation->balance > 0 && ! $request->boolean('force')) {
            return response()->json([
                'message' => 'Masih ada saldo yang belum lunas.',
                'balance' => $reservation->balance,
            ], 422);
        }

        DB::transaction(function () use ($reservation) {
            $room = $reservation->room;
            if ($room) {
                $room->update(['status' => Room::STATUS_HOUSEKEEPING, 'notes' => 'Check-out, menunggu dibersihkan.']);
            }

            $reservation->status = Reservation::STATUS_CHECKED_OUT;
            $reservation->check_out_at = now();
            $reservation->save();

            ActivityLogService::log('check_out', "Tamu check-out {$reservation->code} dari kamar {$room?->room_number}.", $reservation);
        });

        return response()->json([
            'message' => 'Check-out berhasil.',
            'data' => $this->detailShape($reservation->fresh(['guest', 'room.roomType', 'user'])),
        ]);
    }

    public function cancel(Request $request, Reservation $reservation)
    {
        if (in_array($reservation->status, [Reservation::STATUS_CHECKED_OUT, Reservation::STATUS_CANCELLED])) {
            return response()->json(['message' => 'Reservasi tidak dapat dibatalkan.'], 422);
        }

        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        if ($reservation->room && $reservation->status === Reservation::STATUS_CHECKED_IN) {
            $reservation->room->update(['status' => Room::STATUS_AVAILABLE]);
        }

        $reservation->update([
            'status' => Reservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $request->input('reason'),
        ]);

        ActivityLogService::log('cancel', "Reservasi {$reservation->code} dibatalkan.", $reservation);

        return response()->json(['message' => 'Reservasi dibatalkan.', 'data' => $this->detailShape($reservation->load(['guest', 'room.roomType']))]);
    }

    public function noShow(Reservation $reservation)
    {
        if (! in_array($reservation->status, [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED])) {
            return response()->json(['message' => 'Reservasi tidak dapat ditandai no-show.'], 422);
        }

        if ($reservation->check_in_date->greaterThanOrEqualTo(now()->startOfDay())) {
            return response()->json(['message' => 'Tanggal check-in belum lewat.'], 422);
        }

        $reservation->update(['status' => Reservation::STATUS_NO_SHOW]);

        ActivityLogService::log('no_show', "Reservasi {$reservation->code} ditandai no-show.", $reservation);

        return response()->json(['message' => 'Reservasi ditandai no-show.', 'data' => $this->detailShape($reservation->load(['guest', 'room.roomType']))]);
    }

    protected function createGuest(Request $request): Guest
    {
        $guestData = $request->validate([
            'first_name' => ['required_without:guest_id', 'string', 'max:100'],
            'last_name' => ['required_without:guest_id', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'id_type' => ['nullable', 'string', 'max:30'],
            'id_number' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string'],
            'nationality' => ['nullable', 'string', 'max:60'],
        ]);

        return Guest::create($guestData);
    }

    protected function validateData(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'guest_id' => ['nullable', 'exists:guests,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'source' => ['nullable', 'in:walk_in,phone,online,agent'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'room_rate' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'special_requests' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];

        if (! $isUpdate) {
            $rules['guest_id'] = ['required_without:first_name', 'exists:guests,id'];
        }

        return $request->validate($rules);
    }

    protected function assertRoomAvailable(Room $room, \Carbon\Carbon $checkIn, \Carbon\Carbon $checkOut, ?int $excludeReservationId = null): void
    {
        if (in_array($room->status, [Room::STATUS_OCCUPIED, Room::STATUS_MAINTENANCE])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'room_id' => ["Kamar {$room->room_number} sedang tidak tersedia."],
            ]);
        }

        $conflict = Reservation::where('room_id', $room->id)
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])
            ->whereDate('check_in_date', '<', $checkOut->toDateString())
            ->whereDate('check_out_date', '>', $checkIn->toDateString())
            ->when($excludeReservationId, fn ($q) => $q->where('id', '!=', $excludeReservationId))
            ->exists();

        if ($conflict) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'room_id' => ["Kamar {$room->room_number} sudah direservasi pada rentang tanggal tersebut."],
            ]);
        }
    }

    protected function listShape(Reservation $r): array
    {
        return [
            'id' => $r->id,
            'code' => $r->code,
            'status' => $r->status,
            'source' => $r->source,
            'guest' => $r->guest ? ['id' => $r->guest->id, 'name' => $r->guest->full_name, 'phone' => $r->guest->phone] : null,
            'room' => $r->room ? ['id' => $r->room->id, 'room_number' => $r->room->room_number, 'room_type_id' => $r->room->room_type_id, 'room_type' => $r->room->roomType?->name] : null,
            'user' => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name] : null,
            'check_in_date' => $r->check_in_date?->format('Y-m-d'),
            'check_out_date' => $r->check_out_date?->format('Y-m-d'),
            'nights' => (int) $r->nights,
            'adults' => (int) $r->adults,
            'children' => (int) $r->children,
            'total_amount' => (float) $r->total_amount,
            'charges_total' => $r->charges_total,
            'grand_total' => $r->grand_total,
            'paid_amount' => (float) $r->paid_amount,
            'balance' => $r->balance,
            'payment_status' => $r->payment_status,
            'check_in_at' => $r->check_in_at?->format('Y-m-d H:i'),
            'check_out_at' => $r->check_out_at?->format('Y-m-d H:i'),
            'created_at' => $r->created_at?->format('d M Y H:i'),
        ];
    }

    protected function detailShape(Reservation $r, bool $withTransactions = false): array
    {
        $data = $this->listShape($r);
        $data['room_rate'] = (float) $r->room_rate;
        $data['subtotal'] = (float) $r->subtotal;
        $data['discount'] = (float) $r->discount;
        $data['tax_rate'] = (float) $r->tax_rate;
        $data['tax_amount'] = (float) $r->tax_amount;
        $data['extra_person_fee'] = (float) $r->extra_person_fee;
        $data['special_requests'] = $r->special_requests;
        $data['notes'] = $r->notes;
        $data['cancelled_at'] = $r->cancelled_at?->format('Y-m-d H:i');
        $data['cancellation_reason'] = $r->cancellation_reason;

        if ($withTransactions) {
            $data['payments'] = $r->payments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'reference' => $p->reference,
                'paid_at' => $p->paid_at->format('d M Y H:i'),
                'user' => $p->user?->name,
                'notes' => $p->notes,
            ])->all();

            $data['charges'] = $r->charges->map(fn ($c) => [
                'id' => $c->id,
                'description' => $c->description,
                'category' => $c->category,
                'room' => $c->room?->room_number,
                'quantity' => (int) $c->quantity,
                'unit_price' => (float) $c->unit_price,
                'amount' => (float) $c->amount,
                'charged_at' => $c->charged_at->format('d M Y H:i'),
                'user' => $c->user?->name,
            ])->all();
        }

        return $data;
    }
}