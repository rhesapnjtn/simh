<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'hotel_name' => 'SIMH Grand Hotel',
            'hotel_address' => 'Jl. Jenderal Sudirman No. 1, Jakarta Pusat',
            'hotel_phone' => '(021) 555-0100',
            'hotel_email' => 'info@simh-hotel.test',
            'currency' => 'Rp',
            'tax_rate' => '10',
            'default_check_in' => '14:00',
            'default_check_out' => '12:00',
            'footer_text' => '© '.now()->year.' SIMH Hotel Management System',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}