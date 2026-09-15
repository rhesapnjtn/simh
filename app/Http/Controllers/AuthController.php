<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! $user->is_active) {
            ActivityLogService::log('login_failed', "Percobaan login gagal untuk {$credentials['email']}: akun tidak ditemukan atau nonaktif.");

            throw ValidationException::withMessages([
                'email' => ['Akun tidak ditemukan atau sudah dinonaktifkan.'],
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            ActivityLogService::log('login_failed', "Percobaan login gagal untuk {$credentials['email']}: kata sandi salah.", null, $user->id);

            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $request->session()->regenerate();

        $this->revokeOtherSessions($user);

        ActivityLogService::log('login', "Pengguna {$user->name} berhasil login.", $user);

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => $user,
            'permissions' => $user->permissions(),
        ]);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        ActivityLogService::log('logout', "Pengguna {$user?->name} logout.", null, $user?->id);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me()
    {
        $user = Auth::user();

        return response()->json([
            'user' => $user,
            'permissions' => $user->permissions(),
        ]);
    }

    protected function revokeOtherSessions(User $user): void
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', session()->getId())
            ->delete();
    }
}