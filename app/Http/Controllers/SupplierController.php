<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('category', 'like', '%'.$request->input('search').'%');
            });
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json($query->orderBy('name')->paginate(12)->through(fn (Supplier $s) => $this->shape($s)));
    }

    public function all()
    {
        return response()->json(Supplier::where('is_active', true)->orderBy('name')->get()->map(fn ($s) => $this->shape($s)));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $supplier = Supplier::create($data);

        ActivityLogService::log('create', "Supplier {$supplier->name} ditambahkan.", $supplier);

        return response()->json(['message' => 'Supplier berhasil ditambahkan.', 'data' => $this->shape($supplier)], 201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validateData($request);
        $supplier->update($data);

        ActivityLogService::log('update', "Supplier {$supplier->name} diperbarui.", $supplier);

        return response()->json(['message' => 'Supplier berhasil diperbarui.', 'data' => $this->shape($supplier)]);
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseOrders()->exists()) {
            return response()->json(['message' => 'Supplier memiliki purchase order dan tidak dapat dihapus.'], 422);
        }

        $name = $supplier->name;
        $supplier->delete();

        ActivityLogService::log('delete', "Supplier {$name} dihapus.");

        return response()->json(['message' => 'Supplier dihapus.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    protected function shape(Supplier $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'contact_person' => $s->contact_person,
            'phone' => $s->phone,
            'email' => $s->email,
            'address' => $s->address,
            'category' => $s->category,
            'notes' => $s->notes,
            'is_active' => (bool) $s->is_active,
        ];
    }
}