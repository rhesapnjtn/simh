<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::query()->with(['reservation.guest', 'user'])->orderByDesc('paid_at');

        if ($request->filled('from')) {
            $query->whereDate('paid_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('paid_at', '<=', $request->input('to'));
        }

        if ($request->filled('method')) {
            $query->where('method', $request->input('method'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('reservation', function ($r) use ($search) {
                    $r->where('code', 'like', '%'.$search.'%')
                        ->orWhereHas('guest', fn ($g) => $g->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
                })->orWhere('reference', 'like', '%'.$search.'%');
            });
        }

        $payments = $query->paginate(15);

        return response()->json($payments->through(fn (Payment $p) => $this->shape($p)));
    }

    public function store(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,credit_card,debit_card,bank_transfer,e_wallet,qr'],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = DB::transaction(function () use ($data, $reservation, $request) {
            $payment = $reservation->payments()->create([
                'user_id' => auth()->id(),
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => $request->date('paid_at') ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $reservation->paid_amount = round((float) $reservation->paid_amount + (float) $data['amount'], 2);
            $reservation->refreshPaymentStatus();
            $reservation->save();

            ActivityLogService::log('payment', "Pembayaran {$payment->amount} untuk {$reservation->code} tercatat.", $reservation);

            return $payment;
        });

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat.',
            'data' => $this->shape($payment->load(['user', 'reservation.guest'])),
            'reservation' => $this->paymentStatusOf($reservation->fresh()),
        ], 201);
    }

    public function destroy(Request $request, Payment $payment)
    {
        $reservation = $payment->reservation;

        if ($reservation->status === Reservation::STATUS_CHECKED_OUT) {
            return response()->json(['message' => 'Pembayaran pada reservasi yang sudah check-out tidak dapat dihapus.'], 422);
        }

        DB::transaction(function () use ($payment, $reservation) {
            $reservation->paid_amount = round(max(0, (float) $reservation->paid_amount - (float) $payment->amount), 2);
            $reservation->refreshPaymentStatus();
            $reservation->save();
            $payment->delete();

            ActivityLogService::log('delete', "Pembayaran {$payment->amount} untuk {$reservation->code} dihapus.", $reservation);
        });

        return response()->json([
            'message' => 'Pembayaran dihapus.',
            'reservation' => $this->paymentStatusOf($reservation->fresh()),
        ]);
    }

    protected function paymentStatusOf(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'paid_amount' => (float) $reservation->paid_amount,
            'total_amount' => (float) $reservation->total_amount,
            'balance' => $reservation->balance,
            'payment_status' => $reservation->payment_status,
        ];
    }

    protected function shape(Payment $p): array
    {
        return [
            'id' => $p->id,
            'reservation_id' => $p->reservation_id,
            'code' => $p->reservation?->code,
            'guest' => $p->reservation?->guest?->full_name,
            'amount' => (float) $p->amount,
            'method' => $p->method,
            'reference' => $p->reference,
            'paid_at' => $p->paid_at->format('Y-m-d H:i'),
            'user' => $p->user?->name,
            'notes' => $p->notes,
        ];
    }
}