<?php

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\Housekeeping;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\ServiceCharge;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = Room::with('roomType')->get();
        $receptionist = User::where('role', 'receptionist')->first() ?? User::first();

        $guests = [
            ['Budi', 'Santoso', 'budi@example.com', '0812-1000-001', '007-882-991'],
            ['Siti', 'Rahayu', 'siti@example.com', '0813-1000-002', '008-773-882'],
            ['Agus', 'Pratama', 'agus@example.com', '0814-1000-003', '009-664-773'],
            ['Dewi', 'Lestari', 'dewi@example.com', '0815-1000-004', '010-555-664'],
            ['Rizky', 'Hidayat', 'rizky@example.com', '0816-1000-005', '011-446-555'],
            ['Putri', 'Maharani', 'putri@example.com', '0817-1000-006', '012-337-446'],
            ['Andi', 'Kurniawan', 'andi@example.com', '0818-1000-007', '013-228-337'],
            ['Maya', 'Anggraini', 'maya@example.com', '0819-1000-008', '014-119-228'],
            ['Joko', 'Widodo', 'joko@example.com', '0820-1000-009', '015-001-119'],
            ['Lina', 'Saputri', 'lina@example.com', '0821-1000-010', '016-902-001'],
            ['Fajar', 'Nugroho', 'fajar@example.com', '0822-1000-011', '017-893-902'],
            ['Ratna', 'Sari', 'ratna@example.com', '0823-1000-012', '018-784-893'],
        ];

        $guestModels = collect($guests)->map(fn ($g, $i) => Guest::updateOrCreate(
            ['email' => $g[2]],
            [
                'first_name' => $g[0],
                'last_name' => $g[1],
                'phone' => $g[3],
                'id_type' => 'KTP',
                'id_number' => $g[4],
                'address' => 'Jl. Contoh No. '.($i + 1).', Jakarta',
                'nationality' => 'Indonesia',
                'is_vip' => $i % 5 === 0,
            ]
        ));

        $today = Carbon::today();
        $reservations = [];

        // Checked-in (in house) — past check-in, upcoming check-out
        $this->makeReservation($guestModels[0], $rooms[1], $today->copy()->subDays(1), $today->copy()->addDays(2), Reservation::STATUS_CHECKED_IN, $receptionist, $reservations);
        $this->makeReservation($guestModels[1], $rooms[2], $today->copy()->subDays(2), $today->copy()->addDays(1), Reservation::STATUS_CHECKED_IN, $receptionist, $reservations);
        $this->makeReservation($guestModels[2], $rooms[3], $today->copy()->subDays(3), $today->copy()->addDays(2), Reservation::STATUS_CHECKED_IN, $receptionist, $reservations);
        $this->makeReservation($guestModels[3], $rooms[8], $today->copy()->subDays(1), $today->copy()->addDays(3), Reservation::STATUS_CHECKED_IN, $receptionist, $reservations);

        // Confirmed future arrivals
        $this->makeReservation($guestModels[4], $rooms[4], $today->copy()->addDays(1), $today->copy()->addDays(3), Reservation::STATUS_CONFIRMED, $receptionist, $reservations);
        $this->makeReservation($guestModels[5], $rooms[6], $today->copy()->addDays(2), $today->copy()->addDays(4), Reservation::STATUS_CONFIRMED, $receptionist, $reservations);
        $this->makeReservation($guestModels[6], $rooms[9], $today->copy()->addDays(3), $today->copy()->addDays(5), Reservation::STATUS_CONFIRMED, $receptionist, $reservations);

        // Pending
        $this->makeReservation($guestModels[7], $rooms[7], $today->copy()->addDays(4), $today->copy()->addDays(6), Reservation::STATUS_PENDING, $receptionist, $reservations);

        // Checked-out (past)
        $this->makeReservation($guestModels[8], $rooms[0], $today->copy()->subDays(10), $today->copy()->subDays(8), Reservation::STATUS_CHECKED_OUT, $receptionist, $reservations);
        $this->makeReservation($guestModels[9], $rooms[5], $today->copy()->subDays(6), $today->copy()->subDays(4), Reservation::STATUS_CHECKED_OUT, $receptionist, $reservations);

        // Cancelled
        $this->makeReservation($guestModels[10], $rooms[12], $today->copy()->subDays(2), $today->copy()->addDays(1), Reservation::STATUS_CANCELLED, $receptionist, $reservations);

        // No-show
        $this->makeReservation($guestModels[11], $rooms[11], $today->copy()->subDays(1), $today->copy()->addDays(1), Reservation::STATUS_NO_SHOW, $receptionist, $reservations);

        // Payments for in-house and checked-out reservations
        foreach ($reservations as $index => $res) {
            if ($res->status === Reservation::STATUS_CHECKED_IN) {
                $this->addPayment($res, $receptionist, (float) $res->total_amount, 'bank_transfer');

                if ($index === 0) {
                    $this->addCharge($res, $receptionist, 'Makan Malam - Nasi Goreng Seafood', 'food_beverage', 2, 85000);
                    $this->addCharge($res, $receptionist, 'Laundry - 5 pakaian', 'laundry', 5, 20000);
                }
                if ($index === 2) {
                    $this->addCharge($res, $receptionist, 'Minibar - Coca Cola', 'minibar', 2, 15000);
                }
            }

            if ($res->status === Reservation::STATUS_CHECKED_OUT) {
                $this->addPayment($res, $receptionist, (float) $res->total_amount, 'cash');
                if ($index === 8) {
                    $this->addCharge($res, $receptionist, 'Room Service - Makan Siang', 'food_beverage', 1, 65000);
                }
            }
        }

        // Housekeeping tasks
        $pendingRooms = collect($rooms)->filter(fn ($r) => in_array($r->status, [Room::STATUS_HOUSEKEEPING, Room::STATUS_OCCUPIED]))->take(4);
        foreach ($pendingRooms as $i => $room) {
            $this->makeHousekeepingTask($room, $receptionist, $i);
        }

        // Reservasi milik akun Guest/Customer (untuk demo role guest)
        $guestUser = User::where('role', 'guest')->first();
        if ($guestUser && count($rooms) > 14) {
            $this->makeReservation($guestModels[0], $rooms[13], $today->copy()->addDays(5), $today->copy()->addDays(7), Reservation::STATUS_CONFIRMED, $guestUser, $reservations);
            $this->makeReservation($guestModels[3], $rooms[14], $today->copy()->addDays(15), $today->copy()->addDays(18), Reservation::STATUS_CONFIRMED, $guestUser, $reservations);
        }
    }

    protected function makeReservation($guest, $room, Carbon $in, Carbon $out, string $status, User $user, array &$reservations): void
    {
        $nights = max(1, $in->diffInDays($out));
        $roomRate = (float) $room->roomType->base_rate;
        $adults = min(2, $room->roomType->capacity);

        $reservation = Reservation::create([
            'guest_id' => $guest->id,
            'user_id' => $user->id,
            'room_id' => $room->id,
            'status' => $status,
            'source' => ['walk_in', 'online', 'phone'][random_int(0, 2)],
            'check_in_date' => $in,
            'check_out_date' => $out,
            'adults' => $adults,
            'children' => random_int(0, 1),
            'room_rate' => $roomRate,
            'discount' => 0,
            'tax_rate' => 10,
            'special_requests' => null,
            'notes' => null,
        ]);

        if (in_array($status, [Reservation::STATUS_CHECKED_IN, Reservation::STATUS_CHECKED_OUT])) {
            $room->update(['status' => Reservation::STATUS_CHECKED_IN === $status ? Room::STATUS_OCCUPIED : Room::STATUS_HOUSEKEEPING]);
        }

        $reservation->recalculate($room->roomType);
        $reservation->check_in_at = $in->copy()->setTime(14, 30);
        $reservation->save();

        $reservations[] = $reservation;
    }

    protected function addPayment(Reservation $reservation, User $user, float $amount, string $method): void
    {
        Payment::create([
            'reservation_id' => $reservation->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'method' => $method,
            'reference' => null,
            'paid_at' => $reservation->check_in_at ?? now(),
        ]);

        $reservation->paid_amount = $amount;
        $reservation->refreshPaymentStatus();
        $reservation->save();
    }

    protected function addCharge(Reservation $reservation, User $user, string $description, string $category, int $qty, float $unitPrice): void
    {
        ServiceCharge::create([
            'reservation_id' => $reservation->id,
            'room_id' => $reservation->room_id,
            'user_id' => $user->id,
            'description' => $description,
            'category' => $category,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'amount' => round($qty * $unitPrice, 2),
            'charged_at' => $reservation->check_in_date->copy()->addDay(),
        ]);
    }

    protected function makeHousekeepingTask(Room $room, User $user, int $i): void
    {
        Housekeeping::updateOrCreate(
            ['room_id' => $room->id, 'status' => 'pending'],
            [
                'assigned_to' => $user->id,
                'task_type' => $i % 2 === 0 ? 'cleaning' : 'amenities',
                'priority' => ['low', 'medium', 'high'][$i % 3],
                'status' => 'pending',
                'notes' => 'Bersihkan dan siapkan kamar untuk tamu berikutnya.',
                'scheduled_date' => Carbon::today(),
            ]
        );
    }
}