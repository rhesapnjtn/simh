<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $query = Room::query()->with('roomType');

        if ($request->filled('search')) {
            $query->where('room_number', 'like', '%'.$request->input('search').'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->input('room_type_id'));
        }

        $rooms = $query->orderByRaw('CAST(room_number AS UNSIGNED)')->paginate(12);

        return response()->json($rooms->through(fn (Room $r) => $this->shape($r)));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $room = Room::create($data);

        ActivityLogService::log('create', "Kamar {$room->room_number} dibuat.", $room);

        return response()->json(['message' => 'Kamar berhasil dibuat.', 'data' => $this->shape($room->load('roomType'))], 201);
    }

    public function show(Room $room)
    {
        return response()->json($this->shape($room->load('roomType')));
    }

    public function update(Request $request, Room $room)
    {
        $data = $this->validateData($request);
        $room->update($data);

        ActivityLogService::log('update', "Kamar {$room->room_number} diperbarui.", $room);

        return response()->json(['message' => 'Kamar berhasil diperbarui.', 'data' => $this->shape($room->load('roomType'))]);
    }

    public function destroy(Room $room)
    {
        $hasActive = Reservation::active()->where('room_id', $room->id)->exists();
        if ($hasActive) {
            return response()->json(['message' => 'Tidak dapat menghapus kamar yang sedang memiliki reservasi aktif.'], 422);
        }

        $number = $room->room_number;
        $room->delete();

        ActivityLogService::log('delete', "Kamar {$number} dihapus.");

        return response()->json(['message' => 'Kamar berhasil dihapus.']);
    }

    public function changeStatus(Request $request, Room $room)
    {
        $request->validate([
            'status' => ['required', 'in:available,occupied,housekeeping,maintenance'],
            'notes' => ['nullable', 'string'],
        ]);

        $room->update([
            'status' => $request->input('status'),
            'notes' => $request->input('notes', $room->notes),
        ]);

        ActivityLogService::log('status', "Status kamar {$room->room_number} menjadi {$request->input('status')}.", $room);

        return response()->json(['message' => 'Status kamar berhasil diubah.', 'data' => $this->shape($room->load('roomType'))]);
    }

    public function available(Request $request)
    {
        $request->validate([
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ]);

        $checkIn = $request->date('check_in');
        $checkOut = $request->date('check_out');

        $query = Room::query()
            ->with('roomType')
            ->where('status', '!=', Room::STATUS_OCCUPIED)
            ->where('status', '!=', Room::STATUS_MAINTENANCE)
            ->whereDoesntHave('reservations', function ($q) use ($checkIn, $checkOut) {
                $q->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])
                    ->whereDate('check_in_date', '<', $checkOut->toDateString())
                    ->whereDate('check_out_date', '>', $checkIn->toDateString());
            });

        if ($request->filled('exclude')) {
            $query->where('id', '!=', $request->input('exclude'));
        }

        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->input('room_type_id'));
        }

        $rooms = $query->orderByRaw('CAST(room_number AS UNSIGNED)')->get();

        return response()->json($rooms->map(fn ($r) => $this->shape($r)));
    }

    public function occupancy(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = $request->date('date');

        $occupied = Reservation::where('status', Reservation::STATUS_CHECKED_IN)
            ->whereDate('check_in_at', '<=', $date)
            ->whereDate('check_out_date', '>=', $date)
            ->pluck('room_id')
            ->filter()
            ->all();

        return response()->json(Room::with('roomType')->orderByRaw('CAST(room_number AS UNSIGNED)')->get()->map(function (Room $r) use ($occupied) {
            $shape = $this->shape($r);
            $shape['is_occupied'] = in_array($r->id, $occupied);

            return $shape;
        }));
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'room_number' => ['required', 'string', 'max:20'],
            'floor' => ['required', 'integer', 'min:0', 'max:99'],
            'room_type_id' => ['required', 'exists:room_types,id'],
            'status' => ['required', 'in:available,occupied,housekeeping,maintenance'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }

    protected function shape(Room $room): array
    {
        return [
            'id' => $room->id,
            'room_number' => $room->room_number,
            'floor' => (int) $room->floor,
            'room_type_id' => $room->room_type_id,
            'room_type' => $room->roomType ? [
                'id' => $room->roomType->id,
                'name' => $room->roomType->name,
                'base_rate' => (float) $room->roomType->base_rate,
                'extra_person_rate' => (float) $room->roomType->extra_person_rate,
                'capacity' => (int) $room->roomType->capacity,
            ] : null,
            'status' => $room->status,
            'notes' => $room->notes,
        ];
    }
}