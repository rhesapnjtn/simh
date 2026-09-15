<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\Housekeeping;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class HousekeepingController extends Controller
{
    public function index(Request $request)
    {
        $query = Housekeeping::query()
            ->with(['room.roomType', 'assignedUser'])
            ->orderByRaw("FIELD(status, 'pending', 'in_progress', 'completed', 'cancelled')")
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->input('room_id'));
        }

        if ($request->filled('assignee')) {
            $query->where('assigned_to', $request->input('assignee'));
        }

        $tasks = $query->paginate(15);

        return response()->json($tasks->through(fn (Housekeeping $h) => $this->shape($h)));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $task = Housekeeping::create($data);

        ActivityLogService::log('create', "Tugas housekeeping untuk kamar {$task->room->room_number} dibuat.", $task);

        return response()->json(['message' => 'Tugas housekeeping berhasil dibuat.', 'data' => $this->shape($task->load('room.roomType', 'assignedUser'))], 201);
    }

    public function update(Request $request, Housekeeping $task)
    {
        $data = $this->validateData($request);
        $task->update($data);

        ActivityLogService::log('update', "Tugas housekeeping kamar {$task->room->room_number} diperbarui.", $task);

        return response()->json(['message' => 'Tugas housekeeping berhasil diperbarui.', 'data' => $this->shape($task->load('room.roomType', 'assignedUser'))]);
    }

    public function changeStatus(Request $request, Housekeeping $task)
    {
        $request->validate([
            'status' => ['required', 'in:pending,in_progress,completed,cancelled'],
        ]);
        $status = $request->input('status');

        if ($status === 'completed' && $task->status !== 'completed') {
            $task->completed_at = now();
            $task->completed_by = auth()->id();

            $busy = Reservation::where('room_id', $task->room_id)
                ->where('status', Reservation::STATUS_CHECKED_IN)
                ->exists();

            if ($task->room->status === Room::STATUS_HOUSEKEEPING && ! $busy) {
                $task->room->update(['status' => Room::STATUS_AVAILABLE, 'notes' => null]);
            }
        } else {
            $task->completed_at = null;
        }

        $task->status = $status;
        $task->save();

        ActivityLogService::log('status', "Tugas housekeeping kamar {$task->room->room_number} → {$status}.", $task);

        return response()->json(['message' => 'Status tugas diperbarui.', 'data' => $this->shape($task->load('room.roomType', 'assignedUser'))]);
    }

    public function destroy(Housekeeping $task)
    {
        $room = $task->room->room_number;
        $task->delete();

        ActivityLogService::log('delete', "Tugas housekeeping kamar {$room} dihapus.");

        return response()->json(['message' => 'Tugas housekeeping dihapus.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'task_type' => ['required', 'in:cleaning,deep_clean,amenities,repair'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'status' => ['nullable', 'in:pending,in_progress,completed,cancelled'],
            'notes' => ['nullable', 'string'],
            'scheduled_date' => ['nullable', 'date'],
        ]);
    }

    protected function shape(Housekeeping $h): array
    {
        return [
            'id' => $h->id,
            'room_id' => $h->room_id,
            'room' => $h->room ? [
                'id' => $h->room->id,
                'room_number' => $h->room->room_number,
                'floor' => (int) $h->room->floor,
                'status' => $h->room->status,
                'room_type' => $h->room->roomType?->name,
            ] : null,
            'assigned_to' => $h->assigned_to,
            'assignee' => $h->assignedUser?->name,
            'task_type' => $h->task_type,
            'priority' => $h->priority,
            'status' => $h->status,
            'notes' => $h->notes,
            'scheduled_date' => $h->scheduled_date?->format('Y-m-d'),
            'completed_at' => $h->completed_at?->format('Y-m-d H:i'),
            'completed_by' => $h->completedBy?->name,
            'created_at' => $h->created_at?->format('d M Y H:i'),
        ];
    }
}