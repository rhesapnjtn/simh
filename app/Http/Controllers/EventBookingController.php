<?php

namespace App\Http\Controllers;

use App\Models\EventBooking;
use App\Models\EventPayment;
use App\Models\EventVenue;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventBookingController extends Controller
{
    public function index(Request $request)
    {
        $query = EventBooking::query()
            ->with(['venue', 'aycePackage', 'assignedUser'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhere('contact_name', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('start_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('end_date', '<=', $request->input('to'));
        }

        return response()->json($query->paginate(12)->through(fn (EventBooking $b) => $this->shape($b)));
    }

    public function show(EventBooking $booking)
    {
        $booking->load(['venue', 'aycePackage', 'assignedUser', 'payments.user']);

        return response()->json($this->shape($booking, true));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $booking = new EventBooking($this->mapped($data));
        $booking->assigned_to = $data['assigned_to'] ?? auth()->id();

        if ($booking->venue_id) {
            $this->assertVenueCapacity($booking->venue, (int) $booking->pax);
            $this->assertVenueAvailable($booking->venue_id, $booking->start_date, $booking->end_date);
        }

        $booking->recalculate();
        $booking->save();

        ActivityLogService::log('create', "Event \"{$booking->title}\" ({$booking->code}) dijadwalkan.", $booking);

        return response()->json([
            'message' => 'Event berhasil dijadwalkan.',
            'data' => $this->shape($booking->load(['venue', 'aycePackage', 'assignedUser'])),
        ], 201);
    }

    public function update(Request $request, EventBooking $booking)
    {
        if (in_array($booking->status, [EventBooking::STATUS_COMPLETED, EventBooking::STATUS_CANCELLED])) {
            return response()->json(['message' => 'Event yang sudah selesai/dibatalkan tidak dapat diubah.'], 422);
        }

        $data = $this->validateData($request);

        $booking->fill($this->mapped($data));
        $booking->assigned_to = $data['assigned_to'] ?? $booking->assigned_to;

        if ($booking->venue_id) {
            $this->assertVenueCapacity($booking->venue, (int) $booking->pax);
            $this->assertVenueAvailable($booking->venue_id, $booking->start_date, $booking->end_date, $booking->id);
        }

        $booking->recalculate();
        $booking->save();

        ActivityLogService::log('update', "Event \"{$booking->title}\" ({$booking->code}) diperbarui.", $booking);

        return response()->json([
            'message' => 'Event berhasil diperbarui.',
            'data' => $this->shape($booking->load(['venue', 'aycePackage', 'assignedUser'])),
        ]);
    }

    public function destroy(EventBooking $booking)
    {
        if (in_array($booking->status, [EventBooking::STATUS_COMPLETED, EventBooking::STATUS_IN_PROGRESS])) {
            return response()->json(['message' => 'Event yang sedang berlangsung/selesai tidak dapat dihapus.'], 422);
        }

        if ($booking->payments()->exists()) {
            return response()->json(['message' => 'Event memiliki pembayaran dan tidak dapat dihapus. Batalkan saja.'], 422);
        }

        $code = $booking->code;
        $booking->delete();

        ActivityLogService::log('delete', "Event {$code} dihapus.");

        return response()->json(['message' => 'Event dihapus.']);
    }

    public function changeStatus(Request $request, EventBooking $booking)
    {
        $request->validate([
            'status' => ['required', 'in:confirmed,in_progress,completed,cancelled'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $target = $request->input('status');
        $transitions = [
            EventBooking::STATUS_CONFIRMED => [EventBooking::STATUS_PENDING],
            EventBooking::STATUS_IN_PROGRESS => [EventBooking::STATUS_CONFIRMED],
            EventBooking::STATUS_COMPLETED => [EventBooking::STATUS_CONFIRMED, EventBooking::STATUS_IN_PROGRESS],
            EventBooking::STATUS_CANCELLED => [EventBooking::STATUS_PENDING, EventBooking::STATUS_CONFIRMED, EventBooking::STATUS_IN_PROGRESS],
        ];

        if ($booking->status === $target) {
            return response()->json(['message' => 'Status event sudah '.$target.'.'], 422);
        }

        if (! in_array($booking->status, $transitions[$target], true)) {
            return response()->json(['message' => 'Status tidak dapat diubah dari "'.$booking->status.'" ke "'.$target.'".'], 422);
        }

        $booking->status = $target;
        if ($target === EventBooking::STATUS_COMPLETED) {
            $booking->completed_at = now();
            $booking->cancellation_reason = null;
        }
        if ($target === EventBooking::STATUS_CANCELLED) {
            $booking->cancelled_at = now();
            $booking->cancellation_reason = $request->input('reason');
        }
        $booking->save();

        ActivityLogService::log('status', "Event {$booking->code} → {$target}.", $booking);

        return response()->json(['message' => 'Status event diperbarui.', 'data' => $this->shape($booking->load(['venue', 'aycePackage', 'assignedUser']))]);
    }

    public function storePayment(Request $request, EventBooking $booking)
    {
        if (in_array($booking->status, [EventBooking::STATUS_COMPLETED, EventBooking::STATUS_CANCELLED])) {
            return response()->json(['message' => 'Pembayaran tidak dapat dicatat pada event selesai/dibatalkan.'], 422);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,credit_card,debit_card,bank_transfer,e_wallet,qr'],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = DB::transaction(function () use ($data, $booking, $request) {
            $payment = $booking->payments()->create([
                'user_id' => auth()->id(),
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => $request->date('paid_at') ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $booking->paid_amount = round((float) $booking->paid_amount + (float) $data['amount'], 2);
            $booking->refreshPaymentStatus();
            $booking->save();

            ActivityLogService::log('payment', "Pembayaran event {$booking->code} sebesar {$data['amount']} tercatat.", $booking);

            return $payment;
        });

        return response()->json([
            'message' => 'Pembayaran event berhasil dicatat.',
            'data' => $this->paymentStatusOf($booking->fresh()),
            'payment' => $this->shapePayment($payment->load('user')),
        ], 201);
    }

    public function destroyPayment(EventPayment $payment)
    {
        $booking = $payment->booking;

        if (in_array($booking->status, [EventBooking::STATUS_COMPLETED, EventBooking::STATUS_CANCELLED])) {
            return response()->json(['message' => 'Pembayaran pada event selesai/dibatalkan tidak dapat dihapus.'], 422);
        }

        DB::transaction(function () use ($payment, $booking) {
            $booking->paid_amount = round(max(0, (float) $booking->paid_amount - (float) $payment->amount), 2);
            $booking->refreshPaymentStatus();
            $booking->save();
            $payment->delete();

            ActivityLogService::log('delete', "Pembayaran {$payment->amount} untuk event {$booking->code} dihapus.", $booking);
        });

        return response()->json(['message' => 'Pembayaran dihapus.', 'data' => $this->paymentStatusOf($booking->fresh())]);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'event_type' => ['required', 'in:'.implode(',', array_keys(EventBooking::EVENT_TYPES))],
            'title' => ['required', 'string', 'max:200'],
            'venue_id' => ['nullable', 'required_unless:event_type,ayce', 'exists:event_venues,id'],
            'ayce_package_id' => ['nullable', 'required_if:event_type,ayce', 'exists:ayce_packages,id'],
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pax' => ['required', 'integer', 'min:1'],
            'venue_rate' => ['nullable', 'numeric', 'min:0'],
            'price_per_pax' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'addons' => ['nullable', 'array'],
            'addons.*.name' => ['required', 'string', 'max:150'],
            'addons.*.amount' => ['required', 'numeric', 'min:0'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'setup_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    protected function mapped(array $data): array
    {
        $isAyce = ($data['event_type'] ?? null) === EventBooking::TYPE_AYCE;

        if ($isAyce && $data['ayce_package_id'] ?? null) {
            $package = \App\Models\AycePackage::find($data['ayce_package_id']);
            $pricePerPax = (float) ($data['price_per_pax'] ?? 0) > 0
                ? (float) $data['price_per_pax']
                : (float) ($package?->price_per_pax ?? 0);
        } else {
            $pricePerPax = (float) ($data['price_per_pax'] ?? 0);
        }

        $venue = $data['venue_id'] ?? null ? EventVenue::find($data['venue_id']) : null;
        $venueRate = (float) ($data['venue_rate'] ?? 0) > 0
            ? (float) $data['venue_rate']
            : (float) ($venue?->base_rate ?? 0);

        return [
            'event_type' => $data['event_type'],
            'title' => $data['title'],
            'venue_id' => $data['venue_id'] ?? null,
            'ayce_package_id' => ($isAyce && ($data['ayce_package_id'] ?? null)) ? $data['ayce_package_id'] : null,
            'contact_name' => $data['contact_name'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'pax' => (int) $data['pax'],
            'venue_rate' => $venueRate,
            'price_per_pax' => $pricePerPax,
            'addons' => $data['addons'] ?? [],
            'discount' => (float) ($data['discount'] ?? 0),
            'tax_rate' => (float) ($data['tax_rate'] ?? \App\Models\Setting::get('tax_rate', 10)),
            'setup_at' => $data['setup_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    protected function assertVenueCapacity(EventVenue $venue, int $pax): void
    {
        if ($venue->capacity_seated > 0 && $pax > $venue->capacity_seated) {
            throw ValidationException::withMessages([
                'pax' => ["Jumlah tamu ({$pax}) melebihi kapasitas duduk venue ({$venue->capacity_seated})."],
            ]);
        }
    }

    protected function assertVenueAvailable(int $venueId, \Carbon\Carbon $start, \Carbon\Carbon $end, ?int $excludeId = null): void
    {
        $conflict = EventBooking::where('venue_id', $venueId)
            ->whereIn('status', [EventBooking::STATUS_PENDING, EventBooking::STATUS_CONFIRMED, EventBooking::STATUS_IN_PROGRESS])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'venue_id' => ['Venue sudah dipesan pada rentang tanggal tersebut.'],
            ]);
        }
    }

    protected function shape(EventBooking $b, bool $withTransactions = false): array
    {
        $data = [
            'id' => $b->id,
            'code' => $b->code,
            'event_type' => $b->event_type,
            'event_type_label' => EventBooking::EVENT_TYPES[$b->event_type] ?? $b->event_type,
            'title' => $b->title,
            'venue' => $b->venue ? [
                'id' => $b->venue->id,
                'name' => $b->venue->name,
                'capacity_seated' => (int) $b->venue->capacity_seated,
                'capacity_standing' => (int) $b->venue->capacity_standing,
                'base_rate' => (float) $b->venue->base_rate,
            ] : null,
            'ayce_package' => $b->aycePackage ? [
                'id' => $b->aycePackage->id,
                'name' => $b->aycePackage->name,
                'price_per_pax' => (float) $b->aycePackage->price_per_pax,
                'duration_minutes' => (int) $b->aycePackage->duration_minutes,
            ] : null,
            'contact_name' => $b->contact_name,
            'contact_phone' => $b->contact_phone,
            'contact_email' => $b->contact_email,
            'start_date' => $b->start_date?->format('Y-m-d'),
            'end_date' => $b->end_date?->format('Y-m-d'),
            'days' => (int) $b->days,
            'pax' => (int) $b->pax,
            'venue_rate' => (float) $b->venue_rate,
            'price_per_pax' => (float) $b->price_per_pax,
            'addons' => $b->addons ?? [],
            'subtotal' => (float) $b->subtotal,
            'discount' => (float) $b->discount,
            'tax_rate' => (float) $b->tax_rate,
            'tax_amount' => (float) $b->tax_amount,
            'total_amount' => (float) $b->total_amount,
            'paid_amount' => (float) $b->paid_amount,
            'balance' => $b->balance,
            'payment_status' => $b->payment_status,
            'status' => $b->status,
            'assigned_user' => $b->assignedUser ? ['id' => $b->assignedUser->id, 'name' => $b->assignedUser->name] : null,
            'setup_at' => $b->setup_at?->format('Y-m-d H:i'),
            'notes' => $b->notes,
            'completed_at' => $b->completed_at?->format('d M Y H:i'),
            'cancelled_at' => $b->cancelled_at?->format('d M Y H:i'),
            'cancellation_reason' => $b->cancellation_reason,
            'created_at' => $b->created_at?->format('d M Y H:i'),
        ];

        if ($withTransactions) {
            $data['payments'] = $b->payments->map(fn (EventPayment $p) => $this->shapePayment($p))->all();
        }

        return $data;
    }

    protected function shapePayment(EventPayment $p): array
    {
        return [
            'id' => $p->id,
            'amount' => (float) $p->amount,
            'method' => $p->method,
            'reference' => $p->reference,
            'paid_at' => $p->paid_at->format('d M Y H:i'),
            'user' => $p->user?->name,
            'notes' => $p->notes,
        ];
    }

    protected function paymentStatusOf(EventBooking $b): array
    {
        return [
            'id' => $b->id,
            'paid_amount' => (float) $b->paid_amount,
            'total_amount' => (float) $b->total_amount,
            'balance' => $b->balance,
            'payment_status' => $b->payment_status,
        ];
    }
}