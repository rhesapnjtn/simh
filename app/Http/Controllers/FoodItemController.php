<?php

namespace App\Http\Controllers;

use App\Models\FoodItem;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class FoodItemController extends Controller
{
    public function index(Request $request)
    {
        $query = FoodItem::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json($query->orderBy('category')->orderBy('name')->paginate(12)->through(fn (FoodItem $f) => $this->shape($f)));
    }

    public function all()
    {
        return response()->json(FoodItem::where('is_active', true)->orderBy('name')->get()->map(fn ($f) => $this->shape($f)));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $item = FoodItem::create($data);

        ActivityLogService::log('create', "Menu F&B {$item->name} ditambahkan.", $item);

        return response()->json(['message' => 'Menu berhasil ditambahkan.', 'data' => $this->shape($item)], 201);
    }

    public function update(Request $request, FoodItem $item)
    {
        $data = $this->validateData($request);
        $item->update($data);

        ActivityLogService::log('update', "Menu F&B {$item->name} diperbarui.", $item);

        return response()->json(['message' => 'Menu berhasil diperbarui.', 'data' => $this->shape($item)]);
    }

    public function destroy(FoodItem $item)
    {
        $name = $item->name;
        $item->delete();

        ActivityLogService::log('delete', "Menu F&B {$name} dihapus.");

        return response()->json(['message' => 'Menu dihapus.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    protected function shape(FoodItem $f): array
    {
        return [
            'id' => $f->id,
            'name' => $f->name,
            'category' => $f->category,
            'price' => (float) $f->price,
            'description' => $f->description,
            'is_active' => (bool) $f->is_active,
        ];
    }
}