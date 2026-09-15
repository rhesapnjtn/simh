<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use App\Models\Room;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $query = MaintenanceRequest::query()
            ->with(['room', 'requester', 'assignee'])
            ->orderByRaw("FIELD(status, 'pending', 'in_progress', 'on_hold', 'completed', 'cancelled'), FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->input('room_id'));
        }

        if ($request->filled('assignee')) {
            $query->where('assigned_to', $request->input('assignee'));
        }

        return response()->json($query->paginate(12)->through(fn (MaintenanceRequest $m) => $this->shape($m)));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['requested_by'] = auth()->id();
        $m = MaintenanceRequest::create($data);

        ActivityLogService::log('create', "Permintaan maintenance {$m->title} dibuat.", $m);

        return response()->json(['message' => 'Permintaan maintenance berhasil dibuat.', 'data' => $this->shape($m->load('room', 'requester', 'assignee'))], 201);
    }

    public function update(Request $request, MaintenanceRequest $m)
    {
        $data = $this->validateData($request);
        $m->update($data);

        ActivityLogService::log('update', "Permintaan maintenance {$m->title} diperbarui.", $m);

        return response()->json(['message' => 'Permintaan maintenance berhasil diperbarui.', 'data' => $this->shape($m->load('room', 'requester', 'assignee'))]);
    }

    public function changeStatus(Request $request, MaintenanceRequest $m)
    {
        $request->validate([
            'status' => ['required', 'in:pending,in_progress,on_hold,completed,cancelled'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $status = $request->input('status');

        if ($status === 'completed' && $m->status !== 'completed') {
            $m->completed_at = now();
            $m->completed_by = auth()->id();
            if ($request->filled('cost')) {
                $m->cost = $request->input('cost');
            }
            if ($m->room && $m->room->status === Room::STATUS_MAINTENANCE) {
                $m->room->update(['status' => Room::STATUS_HOUSEKEEPING, 'notes' => 'Selesai perbaikan, menunggu dibersihkan.']);
            }
        } else {
            $m->completed_at = null;
            if ($request->filled('cost')) {
                $m->cost = $request->input('cost');
            }
        }

        $m->status = $status;
        $m->save();

        ActivityLogService::log('status', "Permintaan maintenance {$m->title} → {$status}.", $m);

        return response()->json(['message' => 'Status maintenance diperbarui.', 'data' => $this->shape($m->load('room', 'requester', 'assignee'))]);
    }

    public function destroy(MaintenanceRequest $m)
    {
        $title = $m->title;
        $m->delete();

        ActivityLogService::log('delete', "Permintaan maintenance {$title} dihapus.");

        return response()->json(['message' => 'Permintaan maintenance dihapus.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'room_id' => ['nullable', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'in:plumbing,electrical,hvac,mechanical,furniture,other'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'status' => ['nullable', 'in:pending,in_progress,on_hold,completed,cancelled'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    protected function shape(MaintenanceRequest $m): array
    {
        return [
            'id' => $m->id,
            'room_id' => $m->room_id,
            'room' => $m->room?->room_number,
            'title' => $m->title,
            'category' => $m->category,
            'priority' => $m->priority,
            'status' => $m->status,
            'description' => $m->description,
            'requester' => $m->requester?->name,
            'assigned_to' => $m->assigned_to,
            'assignee' => $m->assignee?->name,
            'cost' => (float) $m->cost,
            'completed_at' => $m->completed_at?->format('d M Y H:i'),
            'completed_by' => $m->completedBy?->name,
            'notes' => $m->notes,
            'created_at' => $m->created_at?->format('d M Y H:i'),
        ];
    }
}