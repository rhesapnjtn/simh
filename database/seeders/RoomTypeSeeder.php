<?php

namespace Database\Seeders;

use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Standard Room',
                'base_rate' => 450000,
                'extra_person_rate' => 150000,
                'capacity' => 2,
                'description' => 'Kamar nyaman dengan fasilitas dasar untuk kebutuhan istirahat Anda.',
                'amenities' => ['AC', 'Kamar Mandi Dalam', 'WiFi', 'Televisi', 'Air Panas'],
            ],
            [
                'name' => 'Deluxe Room',
                'base_rate' => 750000,
                'extra_person_rate' => 200000,
                'capacity' => 2,
                'description' => 'Kamar lebih luas dengan pemandangan kota dan fasilitas unggulan.',
                'amenities' => ['AC', 'Kamar Mandi Dalam', 'WiFi', 'Smart TV', 'Minibar', 'Air Panas', 'Coffee Maker'],
            ],
            [
                'name' => 'Executive Suite',
                'base_rate' => 1200000,
                'extra_person_rate' => 300000,
                'capacity' => 3,
                'description' => 'Suite eksklusif dengan ruang tamu terpisah dan fasilitas premium.',
                'amenities' => ['AC', 'Kamar Mandi Dalam', 'WiFi', 'Smart TV', 'Minibar', 'Bathup', 'Ruang Tamu', 'Coffee Maker'],
            ],
            [
                'name' => 'Family Suite',
                'base_rate' => 1500000,
                'extra_person_rate' => 350000,
                'capacity' => 5,
                'description' => 'Suite keluarga dengan dua kamar tidur dan ruang keluarga luas.',
                'amenities' => ['AC', 'Kamar Mandi Dalam x2', 'WiFi', 'Smart TV', 'Minibar', 'Bathup', 'Ruang Keluarga', 'Kitchenette'],
            ],
        ];

        foreach ($types as $type) {
            RoomType::updateOrCreate(['slug' => \Illuminate\Support\Str::slug($type['name'])], $type);
        }
    }
}