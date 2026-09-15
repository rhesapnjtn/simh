<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryItem::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('sku', 'like', '%'.$request->input('search').'%');
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('stock_level')) {
            match ($request->input('stock_level')) {
                'low' => $query->where('quantity', '>', 0)->whereColumn('min_stock', '>=', 'quantity'),
                'out' => $query->where('quantity', '<=', 0),
                'in' => $query->where('quantity', '>', 0)->whereColumn('min_stock', '<', 'quantity'),
                default => null,
            };
        }

        return response()->json($query->orderBy('name')->paginate(12)->through(fn (InventoryItem $i) => $this->shape($i)));
    }

    public function summaries()
    {
        $items = InventoryItem::all();

        return response()->json([
            'total_items' => $items->count(),
            'low_stock' => $items->filter(fn ($i) => $i->isLowStock())->values(),
            'out_of_stock' => $items->filter(fn ($i) => $i->isOutOfStock())->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $item = InventoryItem::create($data);

        ActivityLogService::log('create', "Item stok {$item->name} ditambahkan.", $item);

        return response()->json(['message' => 'Item stok berhasil ditambahkan.', 'data' => $this->shape($item)], 201);
    }

    public function update(Request $request, InventoryItem $item)
    {
        $data = $this->validateData($request);
        $item->update($data);

        ActivityLogService::log('update', "Item stok {$item->name} diperbarui.", $item);

        return response()->json(['message' => 'Item stok berhasil diperbarui.', 'data' => $this->shape($item)]);
    }

    public function destroy(InventoryItem $item)
    {
        $name = $item->name;
        $item->transactions()->delete();
        $item->delete();

        ActivityLogService::log('delete', "Item stok {$name} dihapus.");

        return response()->json(['message' => 'Item stok dihapus.']);
    }

    public function transactions(Request $request, InventoryItem $item)
    {
        $query = $item->transactions()->with('user')->orderByDesc('transaction_at');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        return response()->json($query->paginate(10)->through(fn (InventoryTransaction $t) => [
            'id' => $t->id,
            'type' => $t->type,
            'quantity' => (float) $t->quantity,
            'unit_cost' => $t->unit_cost !== null ? (float) $t->unit_cost : null,
            'reference' => $t->reference,
            'notes' => $t->notes,
            'user' => $t->user?->name,
            'transaction_at' => $t->transaction_at?->format('d M Y H:i'),
        ]));
    }

    public function adjust(Request $request, InventoryItem $item)
    {
        $data = $request->validate([
            'type' => ['required', 'in:in,out,adjust'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['type'] === 'out' && $item->quantity < $data['quantity']) {
            return response()->json(['message' => 'Stok tidak mencukupi untuk pengeluaran item ini.'], 422);
        }

        $delta = match ($data['type']) {
            'in' => (float) $data['quantity'],
            'out' => -1 * (float) $data['quantity'],
            'adjust' => (float) $data['quantity'] - (float) $item->quantity,
        };

        $item->update(['quantity' => round((float) $item->quantity + $delta, 2)]);

        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'user_id' => auth()->id(),
            'type' => $data['type'],
            'quantity' => (float) $data['quantity'],
            'unit_cost' => $data['unit_cost'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'transaction_at' => now(),
        ]);

        ActivityLogService::log('stock', "Stok {$item->name} {$data['type']} {$data['quantity']} {$item->unit}.", $item);

        return response()->json(['message' => 'Transaksi stok berhasil.', 'data' => $this->shape($item->fresh())]);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:30'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    protected function shape(InventoryItem $i): array
    {
        return [
            'id' => $i->id,
            'name' => $i->name,
            'sku' => $i->sku,
            'category' => $i->category,
            'unit' => $i->unit,
            'quantity' => (float) $i->quantity,
            'min_stock' => (float) $i->min_stock,
            'cost_price' => (float) $i->cost_price,
            'location' => $i->location,
            'notes' => $i->notes,
            'is_active' => (bool) $i->is_active,
            'stock_status' => $i->isOutOfStock() ? 'out' : ($i->isLowStock() ? 'low' : 'ok'),
        ];
    }
}