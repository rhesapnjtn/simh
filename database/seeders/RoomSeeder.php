<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $types = RoomType::all();
        $standard = $types->where('slug', 'standard-room')->first();
        $deluxe = $types->where('slug', 'deluxe-room')->first();
        $suite = $types->where('slug', 'executive-suite')->first();
        $family = $types->where('slug', 'family-suite')->first();

        $layout = [
            'Standard Room' => ['floor' => 1, 'numbers' => ['101', '102', '103', '104', '105']],
            'Deluxe Room' => ['floor' => 2, 'numbers' => ['201', '202', '203', '204', '205', '206']],
            'Executive Suite' => ['floor' => 3, 'numbers' => ['301', '302', '303']],
            'Family Suite' => ['floor' => 4, 'numbers' => ['401', '402', '403']],
        ];

        foreach ($layout as $typeName => $config) {
            $type = match ($typeName) {
                'Standard Room' => $standard,
                'Deluxe Room' => $deluxe,
                'Executive Suite' => $suite,
                'Family Suite' => $family,
            };

            if (! $type) {
                continue;
            }

            foreach ($config['numbers'] as $number) {
                Room::updateOrCreate(['room_number' => $number], [
                    'floor' => $config['floor'],
                    'room_type_id' => $type->id,
                    'status' => Room::STATUS_AVAILABLE,
                ]);
            }
        }

        if (Room::where('room_number', '405')->doesntExist()) {
            Room::create(['room_number' => '405', 'floor' => 4, 'room_type_id' => $family->id, 'status' => Room::STATUS_MAINTENANCE]);
        }
    }
}