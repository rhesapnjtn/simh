<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('email', 'like', '%'.$request->input('search').'%');
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $users = $query->orderBy('role')->orderBy('name')->paginate(10);

        return response()->json($users->through(fn (User $u) => $this->shape($u)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_keys(config('permissions.roles')))],
            'password' => ['required', 'string', 'min:6'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::create($data);

        ActivityLogService::log('create', "Pengguna {$user->name} ditambahkan.", $user);

        return response()->json(['message' => 'Pengguna berhasil ditambahkan.', 'data' => $this->shape($user)], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_keys(config('permissions.roles')))],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($user->id === auth()->id() && array_key_exists('is_active', $data) && ! $data['is_active']) {
            return response()->json(['message' => 'Anda tidak dapat menonaktifkan akun sendiri.'], 422);
        }

        $isSuperRole = in_array($user->role, ['admin', 'super_admin']);
        if ($isSuperRole && ! in_array($data['role'], ['admin', 'super_admin'])) {
            $hasOtherSuper = User::whereIn('role', ['admin', 'super_admin'])->where('id', '!=', $user->id)->exists();
            if (! $hasOtherSuper) {
                return response()->json(['message' => 'Minimal harus ada satu akun Super Admin aktif.'], 422);
            }
        }

        $user->update($data);

        ActivityLogService::log('update', "Data pengguna {$user->name} diperbarui.", $user);

        return response()->json(['message' => 'Data pengguna berhasil diperbarui.', 'data' => $this->shape($user)]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Anda tidak dapat menghapus akun sendiri.'], 422);
        }

        if (in_array($user->role, ['admin', 'super_admin']) && User::whereIn('role', ['admin', 'super_admin'])->where('id', '!=', $user->id)->count() === 0) {
            return response()->json(['message' => 'Tidak dapat menghapus Super Admin terakhir.'], 422);
        }

        $name = $user->name;
        $user->delete();

        ActivityLogService::log('delete', "Pengguna {$name} dihapus.");

        return response()->json(['message' => 'Pengguna berhasil dihapus.']);
    }

    protected function shape(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'role' => $u->role,
            'role_label' => $u->role_label,
            'is_active' => (bool) $u->is_active,
            'created_at' => $u->created_at?->format('d M Y'),
        ];
    }
}