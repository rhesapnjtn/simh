<?php

namespace App\Http\Controllers;

use App\Models\EventVenue;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class EventVenueController extends Controller
{
    public function index(Request $request)
    {
        $query = EventVenue::query()->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL));
        }

        return response()->json($query->paginate(12)->through(fn (EventVenue $v) => $this->shape($v)));
    }

    public function all()
    {
        return response()->json(
            EventVenue::where('is_active', true)->orderBy('name')->get()->map(fn (EventVenue $v) => $this->shape($v))
        );
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $venue = EventVenue::create($data);

        ActivityLogService::log('create', "Venue event bertambah: {$venue->name}.", $venue);

        return response()->json(['message' => 'Venue berhasil dibuat.', 'data' => $this->shape($venue)], 201);
    }

    public function update(Request $request, EventVenue $venue)
    {
        $data = $this->validateData($request);

        $venue->update($data);

        ActivityLogService::log('update', "Venue event diperbarui: {$venue->name}.", $venue);

        return response()->json(['message' => 'Venue berhasil diperbarui.', 'data' => $this->shape($venue)]);
    }

    public function destroy(EventVenue $venue)
    {
        if ($venue->bookings()->exists()) {
            return response()->json(['message' => 'Venue memiliki booking event dan tidak dapat dihapus.'], 422);
        }

        $name = $venue->name;
        $venue->delete();

        ActivityLogService::log('delete', "Venue event dihapus: {$name}.");

        return response()->json(['message' => 'Venue dihapus.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'capacity_standing' => ['nullable', 'integer', 'min:0'],
            'capacity_seated' => ['nullable', 'integer', 'min:0'],
            'base_rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    protected function shape(EventVenue $v): array
    {
        return [
            'id' => $v->id,
            'name' => $v->name,
            'capacity_standing' => (int) $v->capacity_standing,
            'capacity_seated' => (int) $v->capacity_seated,
            'base_rate' => (float) $v->base_rate,
            'description' => $v->description,
            'facilities' => $v->facilities ?? [],
            'is_active' => (bool) $v->is_active,
            'created_at' => $v->created_at?->format('d M Y H:i'),
        ];
    }
}