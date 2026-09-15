<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');

        $defaults = [
            'hotel_name' => 'SIMH Grand Hotel',
            'hotel_address' => 'Jl. Sudirman No. 1, Jakarta',
            'hotel_phone' => '(021) 555-0100',
            'hotel_email' => 'info@simh-hotel.test',
            'currency' => 'Rp',
            'tax_rate' => 10,
            'default_check_in' => '14:00',
            'default_check_out' => '12:00',
            'footer_text' => '© '.now()->year.' SIMH Hotel Management System',
        ];

        $merged = collect($defaults)->map(function ($default, $key) use ($settings) {
            return $settings[$key] ?? $default;
        });

        return response()->json($merged);
    }

    public function update(Request $request)
    {
        $known = [
            'hotel_name', 'hotel_address', 'hotel_phone', 'hotel_email',
            'currency', 'tax_rate', 'default_check_in', 'default_check_out', 'footer_text',
        ];

        $data = $request->validate([
            'hotel_name' => ['required', 'string', 'max:100'],
            'hotel_address' => ['nullable', 'string', 'max:255'],
            'hotel_phone' => ['nullable', 'string', 'max:30'],
            'hotel_email' => ['nullable', 'email', 'max:100'],
            'currency' => ['nullable', 'string', 'max:10'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_check_in' => ['nullable', 'string', 'max:10'],
            'default_check_out' => ['nullable', 'string', 'max:10'],
            'footer_text' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($data as $key => $value) {
            if (in_array($key, $known)) {
                Setting::set($key, $value);
            }
        }

        ActivityLogService::log('update', 'Pengaturan hotel diperbarui.');

        return response()->json(['message' => 'Pengaturan berhasil disimpan.']);
    }
}