<?php

namespace Database\Seeders;

use App\Models\AycePackage;
use App\Models\EventBooking;
use App\Models\EventPayment;
use App\Models\EventVenue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class EventDemoSeeder extends Seeder
{
    public function run(): void
    {
        $gm = User::where('role', 'general_manager')->first() ?? User::first();
        $finance = User::where('role', 'finance')->first() ?? $gm;

        // ── Venue ───────────────────────────────────────────────
        $grandHall = EventVenue::create([
            'name' => 'Grand Ballroom',
            'capacity_standing' => 800,
            'capacity_seated' => 500,
            'base_rate' => 15_000_000,
            'description' => 'Ballroom utama dengan panggung permanen dan sound system.',
            'facilities' => ['AC', 'Sound System', 'Panggung', 'Dekorasi', 'Meja & Kursi', 'Parkir'],
            'is_active' => true,
        ]);

        $garden = EventVenue::create([
            'name' => 'Rooftop Garden',
            'capacity_standing' => 250,
            'capacity_seated' => 150,
            'base_rate' => 6_500_000,
            'description' => 'Rooftop terbuka dengan pemandangan kota, cocok untuk acara privat.',
            'facilities' => ['AC', 'Sound System', 'Proyektor', 'Parkir'],
            'is_active' => true,
        ]);

        EventVenue::create([
            'name' => 'Meeting Room Pandan 1',
            'capacity_standing' => 60,
            'capacity_seated' => 40,
            'base_rate' => 2_500_000,
            'description' => 'Ruangan meeting kapasitas kecil dengan layout fleksibel.',
            'facilities' => ['AC', 'Proyektor', 'Meja & Kursi', 'Parkir'],
            'is_active' => true,
        ]);

        // ── Paket AYCE ──────────────────────────────────────────
        $aycePremium = AycePackage::create([
            'name' => 'AYCE Premium',
            'price_per_pax' => 185_000,
            'min_pax' => 50,
            'max_pax' => 500,
            'duration_minutes' => 120,
            'description' => 'Paket all you can eat premium untuk acara besar.',
            'includes' => 'Steak, seafood, dessert, minuman tanpa batas',
            'is_active' => true,
        ]);

        AycePackage::create([
            'name' => 'AYCE Nusantara',
            'price_per_pax' => 125_000,
            'min_pax' => 30,
            'max_pax' => 300,
            'duration_minutes' => 90,
            'description' => 'Paket AYCE dengan menu khas nusantara.',
            'includes' => 'Rendang, sate, nasi uduk, es teler',
            'is_active' => true,
        ]);

        // ── Booking demo ────────────────────────────────────────
        $booking1 = new EventBooking([
            'event_type' => 'wedding',
            'title' => 'Pernikahan Andi & Sari',
            'venue_id' => $grandHall->id,
            'ayce_package_id' => null,
            'contact_name' => 'Sari Wijaya',
            'contact_phone' => '081234560001',
            'contact_email' => 'sari@example.com',
            'start_date' => Carbon::today()->addDays(45),
            'end_date' => Carbon::today()->addDays(45),
            'pax' => 350,
            'venue_rate' => 15_000_000,
            'price_per_pax' => 0,
            'addons' => [
                ['name' => 'Dekorasi bunga premium', 'amount' => 3_000_000],
                ['name' => 'Foto & video (12 jam)', 'amount' => 4_500_000],
                ['name' => 'Kue pengantin 3 tingkat', 'amount' => 1_250_000],
            ],
            'discount' => 1_000_000,
            'tax_rate' => 10,
            'status' => 'confirmed',
        ]);
        $booking1->assigned_to = $gm->id;
        $booking1->recalculate();
        $booking1->save();

        $booking2 = new EventBooking([
            'event_type' => 'ayce',
            'title' => 'Company Gathering AYCE',
            'venue_id' => null,
            'ayce_package_id' => $aycePremium->id,
            'contact_name' => 'Budi Santoso',
            'contact_phone' => '081234560002',
            'contact_email' => 'budi@perusahaan.co.id',
            'start_date' => Carbon::today()->addDays(60),
            'end_date' => Carbon::today()->addDays(60),
            'pax' => 250,
            'venue_rate' => 0,
            'price_per_pax' => 185_000,
            'addons' => [
                ['name' => 'Dokumentasi', 'amount' => 2_000_000],
            ],
            'discount' => 0,
            'tax_rate' => 10,
            'status' => 'pending',
        ]);
        $booking2->assigned_to = $gm->id;
        $booking2->recalculate();
        $booking2->save();

        EventPayment::create([
            'event_booking_id' => $booking1->id,
            'user_id' => $finance->id,
            'amount' => 10_000_000,
            'method' => 'bank_transfer',
            'reference' => 'TRF-WED-0001',
            'paid_at' => now(),
            'notes' => 'Down payment pernikahan.',
        ]);
        $booking1->paid_amount = 10_000_000;
        $booking1->refreshPaymentStatus();
        $booking1->save();
    }
}