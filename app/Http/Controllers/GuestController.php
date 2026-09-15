<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Reservation;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function index(Request $request)
    {
        $query = Guest::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('id_number', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('is_vip')) {
            $query->where('is_vip', $request->boolean('is_vip'));
        }

        $guests = $query->withCount('reservations')->orderBy('first_name')->paginate(10);

        return response()->json($guests->through(fn (Guest $g) => $this->shape($g)));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $guest = Guest::create($data);

        ActivityLogService::log('create', "Tamu {$guest->full_name} ditambahkan.", $guest);

        return response()->json(['message' => 'Tamu berhasil ditambahkan.', 'data' => $this->shape($guest)], 201);
    }

    public function show(Guest $guest)
    {
        return response()->json($this->shape($guest, true));
    }

    public function update(Request $request, Guest $guest)
    {
        $data = $this->validateData($request);
        $guest->update($data);

        ActivityLogService::log('update', "Data tamu {$guest->full_name} diperbarui.", $guest);

        return response()->json(['message' => 'Data tamu berhasil diperbarui.', 'data' => $this->shape($guest)]);
    }

    public function destroy(Guest $guest)
    {
        if ($guest->reservations()->exists()) {
            return response()->json(['message' => 'Tamu memiliki riwayat reservasi dan tidak dapat dihapus.'], 422);
        }

        $name = $guest->full_name;
        $guest->delete();

        ActivityLogService::log('delete', "Tamu {$name} dihapus.", $guest);

        return response()->json(['message' => 'Tamu berhasil dihapus.']);
    }

    public function reservations(Guest $guest)
    {
        $reservations = $guest->reservations()
            ->with(['room.roomType', 'user'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (Reservation $r) {
                return [
                    'id' => $r->id,
                    'code' => $r->code,
                    'status' => $r->status,
                    'room' => $r->room?->room_number,
                    'check_in_date' => $r->check_in_date?->format('Y-m-d'),
                    'check_out_date' => $r->check_out_date?->format('Y-m-d'),
                    'total_amount' => (float) $r->total_amount,
                    'payment_status' => $r->payment_status,
                    'created_at' => $r->created_at->format('d M Y H:i'),
                ];
            });

        return response()->json($reservations);
    }

    public function search(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'min:2']]);
        $q = $request->input('q');

        $guests = Guest::where(function ($query) use ($q) {
            $query->where('first_name', 'like', '%'.$q.'%')
                ->orWhere('last_name', 'like', '%'.$q.'%')
                ->orWhere('email', 'like', '%'.$q.'%')
                ->orWhere('phone', 'like', '%'.$q.'%')
                ->orWhere('id_number', 'like', '%'.$q.'%');
        })->limit(10)->get()->map(fn (Guest $g) => $this->shape($g));

        return response()->json($guests);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'id_type' => ['nullable', 'string', 'max:30'],
            'id_number' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'is_vip' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    protected function shape(Guest $guest, bool $withCount = false): array
    {
        $data = [
            'id' => $guest->id,
            'first_name' => $guest->first_name,
            'last_name' => $guest->last_name,
            'name' => $guest->full_name,
            'email' => $guest->email,
            'phone' => $guest->phone,
            'id_type' => $guest->id_type,
            'id_number' => $guest->id_number,
            'address' => $guest->address,
            'nationality' => $guest->nationality,
            'is_vip' => (bool) $guest->is_vip,
            'notes' => $guest->notes,
            'created_at' => $guest->created_at?->format('d M Y'),
        ];

        if ($withCount) {
            $data['reservations_count'] = (int) $guest->reservations_count;
        } else {
            $data['reservations_count'] = (int) $guest->reservations->count();
        }

        return $data;
    }
}