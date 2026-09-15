<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::query()
            ->with(['supplier', 'creator', 'items'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('search')) {
            $query->where('code', 'like', '%'.$request->input('search').'%');
        }

        return response()->json($query->paginate(12)->through(fn (PurchaseOrder $po) => $this->shape($po)));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $items = collect($data['items'])->map(fn ($i) => [
            'item_name' => $i['item_name'],
            'quantity' => (float) $i['quantity'],
            'unit' => $i['unit'] ?? null,
            'unit_price' => (float) $i['unit_price'],
            'amount' => round((float) $i['quantity'] * (float) $i['unit_price'], 2),
        ]);

        $po = PurchaseOrder::create([
            'code' => $this->generateCode(),
            'supplier_id' => $data['supplier_id'],
            'created_by' => auth()->id(),
            'order_date' => $data['order_date'] ?? now(),
            'expected_date' => $data['expected_date'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'total_amount' => round($items->sum('amount'), 2),
            'notes' => $data['notes'] ?? null,
        ]);

        $po->items()->createMany($items->all());

        ActivityLogService::log('create', "Purchase order {$po->code} dibuat.", $po);

        return response()->json(['message' => 'Purchase order berhasil dibuat.', 'data' => $this->shape($po->load(['supplier', 'creator', 'items']))], 201);
    }

    public function update(Request $request, PurchaseOrder $po)
    {
        if (in_array($po->status, ['received', 'cancelled'])) {
            return response()->json(['message' => 'Purchase order yang sudah diterima/dibatalkan tidak dapat diubah.'], 422);
        }

        $data = $this->validateData($request);

        $items = collect($data['items'])->map(fn ($i) => [
            'item_name' => $i['item_name'],
            'quantity' => (float) $i['quantity'],
            'unit' => $i['unit'] ?? null,
            'unit_price' => (float) $i['unit_price'],
            'amount' => round((float) $i['quantity'] * (float) $i['unit_price'], 2),
        ]);

        $po->update([
            'supplier_id' => $data['supplier_id'],
            'order_date' => $data['order_date'] ?? $po->order_date,
            'expected_date' => $data['expected_date'] ?? null,
            'status' => $data['status'] ?? $po->status,
            'total_amount' => round($items->sum('amount'), 2),
            'notes' => $data['notes'] ?? null,
        ]);

        $po->items()->delete();
        $po->items()->createMany($items->all());

        ActivityLogService::log('update', "Purchase order {$po->code} diperbarui.", $po);

        return response()->json(['message' => 'Purchase order berhasil diperbarui.', 'data' => $this->shape($po->load(['supplier', 'creator', 'items']))]);
    }

    public function changeStatus(Request $request, PurchaseOrder $po)
    {
        $request->validate(['status' => ['required', 'in:draft,submitted,approved,received,cancelled']]);
        $status = $request->input('status');

        if ($status === 'received' && $po->status !== 'received') {
            $po->received_at = now();
        }

        $po->status = $status;
        $po->save();

        ActivityLogService::log('status', "Purchase order {$po->code} → {$status}.", $po);

        return response()->json(['message' => 'Status purchase order diperbarui.', 'data' => $this->shape($po->load(['supplier', 'creator', 'items']))]);
    }

    public function destroy(PurchaseOrder $po)
    {
        if ($po->status === 'received') {
            return response()->json(['message' => 'Purchase order yang sudah diterima tidak dapat dihapus.'], 422);
        }

        $code = $po->code;
        $po->delete();

        ActivityLogService::log('delete', "Purchase order {$code} dihapus.");

        return response()->json(['message' => 'Purchase order dihapus.']);
    }

    protected function generateCode(): string
    {
        return 'PO-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'order_date' => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,submitted,approved,received,cancelled'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:150'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    protected function shape(PurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'code' => $po->code,
            'supplier_id' => $po->supplier_id,
            'supplier' => $po->supplier?->name,
            'creator' => $po->creator?->name,
            'order_date' => $po->order_date?->format('Y-m-d'),
            'expected_date' => $po->expected_date?->format('Y-m-d'),
            'status' => $po->status,
            'total_amount' => (float) $po->total_amount,
            'notes' => $po->notes,
            'received_at' => $po->received_at?->format('d M Y H:i'),
            'items' => $po->items->map(fn ($i) => [
                'id' => $i->id,
                'item_name' => $i->item_name,
                'quantity' => (float) $i->quantity,
                'unit' => $i->unit,
                'unit_price' => (float) $i->unit_price,
                'amount' => (float) $i->amount,
            ])->all(),
            'created_at' => $po->created_at?->format('d M Y H:i'),
        ];
    }
}