<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class RoomTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomType::query()->withCount('rooms');

        if ($request->has('search') && $request->input('search') !== '') {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        $roomTypes = $query->orderBy('name')->paginate(10);

        return response()->json($roomTypes->through(fn (RoomType $rt) => [
            'id' => $rt->id,
            'name' => $rt->name,
            'slug' => $rt->slug,
            'description' => $rt->description,
            'base_rate' => (float) $rt->base_rate,
            'extra_person_rate' => (float) $rt->extra_person_rate,
            'capacity' => $rt->capacity,
            'amenities' => $rt->amenities ?? [],
            'image' => $rt->image,
            'is_active' => $rt->is_active,
            'rooms_count' => (int) $rt->rooms_count,
        ]));
    }

    public function all()
    {
        return response()->json(RoomType::active()->orderBy('name')->get()->map(fn ($rt) => $this->shape($rt)));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['amenities'] = $request->input('amenities', []);
        $roomType = RoomType::create($data);

        ActivityLogService::log('create', "Tipe kamar {$roomType->name} dibuat.", $roomType);

        return response()->json(['message' => 'Tipe kamar berhasil dibuat.', 'data' => $this->shape($roomType)], 201);
    }

    public function show(RoomType $roomType)
    {
        return response()->json($this->shape($roomType));
    }

    public function update(Request $request, RoomType $roomType)
    {
        $data = $this->validateData($request);
        $data['amenities'] = $request->input('amenities', $roomType->amenities ?? []);
        $roomType->update($data);

        ActivityLogService::log('update', "Tipe kamar {$roomType->name} diperbarui.", $roomType);

        return response()->json(['message' => 'Tipe kamar berhasil diperbarui.', 'data' => $this->shape($roomType)]);
    }

    public function destroy(RoomType $roomType)
    {
        if ($roomType->rooms()->exists()) {
            return response()->json(['message' => 'Tidak dapat menghapus tipe kamar yang masih memiliki kamar.'], 422);
        }

        $name = $roomType->name;
        $roomType->delete();

        ActivityLogService::log('delete', "Tipe kamar {$name} dihapus.");

        return response()->json(['message' => 'Tipe kamar berhasil dihapus.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'base_rate' => ['required', 'numeric', 'min:0'],
            'extra_person_rate' => ['nullable', 'numeric', 'min:0'],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'image' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    protected function shape(RoomType $rt): array
    {
        return [
            'id' => $rt->id,
            'name' => $rt->name,
            'slug' => $rt->slug,
            'description' => $rt->description,
            'base_rate' => (float) $rt->base_rate,
            'extra_person_rate' => (float) $rt->extra_person_rate,
            'capacity' => (int) $rt->capacity,
            'amenities' => $rt->amenities ?? [],
            'image' => $rt->image,
            'is_active' => (bool) $rt->is_active,
        ];
    }
}