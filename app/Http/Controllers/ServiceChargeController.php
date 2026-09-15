<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ServiceCharge;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class ServiceChargeController extends Controller
{
    public function index(Request $request, Reservation $reservation)
    {
        $charges = $reservation->charges()
            ->with('user')
            ->orderByDesc('charged_at')
            ->get();

        return response()->json($charges->map(fn (ServiceCharge $c) => $this->shape($c)));
    }

    public function store(Request $request, Reservation $reservation)
    {
        if (! in_array($reservation->status, [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])) {
            return response()->json(['message' => 'Biaya tambahan hanya dapat ditambahkan pada reservasi aktif.'], 422);
        }

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:food_beverage,laundry,spa,transport,minibar,other'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'charged_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $charge = $reservation->charges()->create(array_merge($data, [
            'user_id' => auth()->id(),
            'amount' => round((float) $data['quantity'] * (float) $data['unit_price'], 2),
            'charged_at' => $request->date('charged_at') ?? now(),
        ]));

        $reservation->refreshPaymentStatus();
        $reservation->save();

        ActivityLogService::log('charge', "Biaya \"{$charge->description}\" untuk {$reservation->code} ditambahkan.", $reservation);

        return response()->json(['message' => 'Biaya tambahan berhasil dicatat.', 'data' => $this->shape($charge)], 201);
    }

    public function void(ServiceCharge $charge)
    {
        if ($charge->status === 'void') {
            return response()->json(['message' => 'Biaya ini sudah dibatalkan.'], 422);
        }

        $reservation = $charge->reservation;
        if ($reservation->status === Reservation::STATUS_CHECKED_OUT) {
            return response()->json(['message' => 'Biaya pada reservasi check-out tidak dapat dibatalkan.'], 422);
        }

        $charge->update(['status' => 'void']);
        $reservation->refreshPaymentStatus();
        $reservation->save();
        ActivityLogService::log('void', "Biaya \"{$charge->description}\" pada {$reservation->code} dibatalkan.", $reservation);

        return response()->json(['message' => 'Biaya berhasil dibatalkan.']);
    }

    protected function shape(ServiceCharge $c): array
    {
        return [
            'id' => $c->id,
            'reservation_id' => $c->reservation_id,
            'description' => $c->description,
            'category' => $c->category,
            'room' => $c->room?->room_number,
            'quantity' => (int) $c->quantity,
            'unit_price' => (float) $c->unit_price,
            'amount' => (float) $c->amount,
            'charged_at' => $c->charged_at->format('Y-m-d H:i'),
            'user' => $c->user?->name,
            'status' => $c->status,
        ];
    }
}