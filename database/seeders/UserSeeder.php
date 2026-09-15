<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Super Admin', 'email' => 'superadmin@simh.test', 'phone' => '0812-0000-0000', 'role' => 'super_admin'],
            ['name' => 'Admin Utama', 'email' => 'admin@simh.test', 'phone' => '0812-0000-0001', 'role' => 'admin'],
            ['name' => 'General Manager', 'email' => 'gm@simh.test', 'phone' => '0812-0000-0002', 'role' => 'general_manager'],
            ['name' => 'Manajer Hotel', 'email' => 'manager@simh.test', 'phone' => '0812-0000-0003', 'role' => 'manager'],
            ['name' => 'Front Office', 'email' => 'frontoffice@simh.test', 'phone' => '0812-0000-0004', 'role' => 'front_office'],
            ['name' => 'Resepsionis', 'email' => 'reception@simh.test', 'phone' => '0812-0000-0005', 'role' => 'receptionist'],
            ['name' => 'Reservation Staff', 'email' => 'reservation@simh.test', 'phone' => '0812-0000-0006', 'role' => 'reservation_staff'],
            ['name' => 'Housekeeping', 'email' => 'housekeeping@simh.test', 'phone' => '0812-0000-0007', 'role' => 'housekeeping'],
            ['name' => 'Finance / Accounting', 'email' => 'finance@simh.test', 'phone' => '0812-0000-0008', 'role' => 'finance'],
            ['name' => 'F&B Staff', 'email' => 'fbstaff@simh.test', 'phone' => '0812-0000-0009', 'role' => 'fb_staff'],
            ['name' => 'F&B Manager', 'email' => 'fbmanager@simh.test', 'phone' => '0812-0000-0010', 'role' => 'fb_manager'],
            ['name' => 'Purchasing', 'email' => 'purchasing@simh.test', 'phone' => '0812-0000-0011', 'role' => 'purchasing'],
            ['name' => 'Storekeeper', 'email' => 'storekeeper@simh.test', 'phone' => '0812-0000-0012', 'role' => 'inventory'],
            ['name' => 'Engineering', 'email' => 'engineering@simh.test', 'phone' => '0812-0000-0013', 'role' => 'engineering'],
            ['name' => 'HRD', 'email' => 'hrd@simh.test', 'phone' => '0812-0000-0014', 'role' => 'hrd'],
            ['name' => 'Supervisor', 'email' => 'supervisor@simh.test', 'phone' => '0812-0000-0015', 'role' => 'supervisor'],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(['email' => $u['email']], [
                'name' => $u['name'],
                'phone' => $u['phone'],
                'role' => $u['role'],
                'password' => 'password',
                'is_active' => true,
            ]);
        }

        User::updateOrCreate(['email' => 'guest@simh.test'], [
            'name' => 'Tamu Demo',
            'phone' => '0812-0000-0016',
            'role' => 'guest',
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}