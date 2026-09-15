<?php

namespace App\Http\Controllers;

use App\Models\FoodItem;
use App\Models\FoodOrder;
use App\Models\Setting;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FoodOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = FoodOrder::query()
            ->with(['user', 'guest', 'room', 'items'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('order_type')) {
            $query->where('order_type', $request->input('order_type'));
        }

        if ($request->filled('search')) {
            $query->where('code', 'like', '%'.$request->input('search').'%');
        }

        return response()->json($query->paginate(12)->through(fn (FoodOrder $fo) => $this->shape($fo)));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $items = collect($data['items'])->map(function ($i) {
            $foodItem = $i['food_item_id'] ?? null ? FoodItem::find($i['food_item_id']) : null;
            $unitPrice = (float) ($i['unit_price'] ?? $foodItem?->price ?? 0);

            return [
                'food_item_id' => $foodItem?->id,
                'item_name' => $i['item_name'] ?? $foodItem?->name,
                'quantity' => (int) $i['quantity'],
                'unit_price' => $unitPrice,
                'amount' => round((float) $i['quantity'] * $unitPrice, 2),
            ];
        });

        $subtotal = round($items->sum('amount'), 2);
        $taxRate = (float) ($data['tax_rate'] ?? Setting::get('fb_tax_rate', 10));
        $tax = round($subtotal * $taxRate / 100, 2);

        $order = FoodOrder::create([
            'code' => $this->generateCode(),
            'user_id' => auth()->id(),
            'guest_id' => $data['guest_id'] ?? null,
            'room_id' => $data['room_id'] ?? null,
            'table_no' => $data['table_no'] ?? null,
            'order_type' => $data['order_type'] ?? 'dine_in',
            'status' => $data['status'] ?? 'pending',
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total_amount' => round($subtotal + $tax, 2),
            'notes' => $data['notes'] ?? null,
        ]);

        $order->items()->createMany($items->all());

        ActivityLogService::log('create', "Order F&B {$order->code} dibuat.", $order);

        return response()->json(['message' => 'Order F&B berhasil dibuat.', 'data' => $this->shape($order->load(['user', 'guest', 'room', 'items']))], 201);
    }

    public function update(Request $request, FoodOrder $order)
    {
        if (in_array($order->status, [FoodOrder::STATUS_PAID, FoodOrder::STATUS_CANCELLED])) {
            return response()->json(['message' => 'Order yang sudah dibayar/dibatalkan tidak dapat diubah.'], 422);
        }

        $data = $this->validateData($request);

        $items = collect($data['items'])->map(function ($i) {
            $foodItem = $i['food_item_id'] ?? null ? FoodItem::find($i['food_item_id']) : null;
            $unitPrice = (float) ($i['unit_price'] ?? $foodItem?->price ?? 0);

            return [
                'food_item_id' => $foodItem?->id,
                'item_name' => $i['item_name'] ?? $foodItem?->name,
                'quantity' => (int) $i['quantity'],
                'unit_price' => $unitPrice,
                'amount' => round((float) $i['quantity'] * $unitPrice, 2),
            ];
        });

        $subtotal = round($items->sum('amount'), 2);
        $taxRate = (float) ($data['tax_rate'] ?? Setting::get('fb_tax_rate', 10));
        $tax = round($subtotal * $taxRate / 100, 2);

        $order->update([
            'guest_id' => $data['guest_id'] ?? null,
            'room_id' => $data['room_id'] ?? null,
            'table_no' => $data['table_no'] ?? null,
            'order_type' => $data['order_type'] ?? 'dine_in',
            'status' => $data['status'] ?? $order->status,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total_amount' => round($subtotal + $tax, 2),
            'notes' => $data['notes'] ?? null,
        ]);

        $order->items()->delete();
        $order->items()->createMany($items->all());

        ActivityLogService::log('update', "Order F&B {$order->code} diperbarui.", $order);

        return response()->json(['message' => 'Order F&B berhasil diperbarui.', 'data' => $this->shape($order->load(['user', 'guest', 'room', 'items']))]);
    }

    public function changeStatus(Request $request, FoodOrder $order)
    {
        $request->validate(['status' => ['required', 'in:pending,preparing,served,paid,cancelled']]);
        $order->update(['status' => $request->input('status')]);

        ActivityLogService::log('status', "Order F&B {$order->code} → {$request->input('status')}.", $order);

        return response()->json(['message' => 'Status order diperbarui.', 'data' => $this->shape($order->load(['user', 'guest', 'room', 'items']))]);
    }

    public function destroy(FoodOrder $order)
    {
        if ($order->status === FoodOrder::STATUS_PAID) {
            return response()->json(['message' => 'Order yang sudah dibayar tidak dapat dihapus.'], 422);
        }

        $code = $order->code;
        $order->delete();

        ActivityLogService::log('delete', "Order F&B {$code} dihapus.");

        return response()->json(['message' => 'Order F&B dihapus.']);
    }

    protected function generateCode(): string
    {
        return 'FB-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'guest_id' => ['nullable', 'exists:guests,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'table_no' => ['nullable', 'string', 'max:20'],
            'order_type' => ['nullable', 'in:dine_in,takeaway,delivery'],
            'status' => ['nullable', 'in:pending,preparing,served,paid,cancelled'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.food_item_id' => ['nullable', 'exists:food_items,id'],
            'items.*.item_name' => ['nullable', 'string', 'max:150'],
            'items.*.quantity' => ['required', 'integer', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    protected function shape(FoodOrder $fo): array
    {
        return [
            'id' => $fo->id,
            'code' => $fo->code,
            'user' => $fo->user?->name,
            'guest' => $fo->guest ? ['id' => $fo->guest->id, 'name' => $fo->guest->full_name] : null,
            'room' => $fo->room?->room_number,
            'table_no' => $fo->table_no,
            'order_type' => $fo->order_type,
            'status' => $fo->status,
            'subtotal' => (float) $fo->subtotal,
            'tax' => (float) $fo->tax,
            'total_amount' => (float) $fo->total_amount,
            'notes' => $fo->notes,
            'ordered_at' => $fo->ordered_at?->format('d M Y H:i'),
            'items' => $fo->items->map(fn ($i) => [
                'id' => $i->id,
                'food_item_id' => $i->food_item_id,
                'item_name' => $i->item_name,
                'quantity' => (int) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'amount' => (float) $i->amount,
            ])->all(),
            'created_at' => $fo->created_at?->format('d M Y H:i'),
        ];
    }
}