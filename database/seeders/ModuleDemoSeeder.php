<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\FoodItem;
use App\Models\FoodOrder;
use App\Models\FoodOrderItem;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\MaintenanceRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ModuleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $supervisor = User::where('role', 'supervisor')->first() ?? User::where('role', 'fb_staff')->first() ?? User::first();

        // ── Menu F&B ────────────────────────────────────────────
        $menus = [
            ['Nasi Goreng Spesial', 'Makanan', 45000],
            ['Mie Goreng Seafood', 'Makanan', 40000],
            ['Ayam Betutu', 'Makanan', 65000],
            ['Sate Ayam (10 tusuk)', 'Makanan', 50000],
            ['Cap Cay', 'Makanan', 38000],
            ['Es Teh Manis', 'Minuman', 10000],
            ['Jus Alpukat', 'Minuman', 20000],
            ['Kopi Susu', 'Minuman', 25000],
            ['Kopi Tarik', 'Minuman', 28000],
            ['Roti Bakar Coklat', 'Snack', 22000],
            ['Pisang Goreng', 'Snack', 15000],
            ['Kentang Goreng', 'Snack', 18000],
        ];

        foreach ($menus as [$name, $cat, $price]) {
            FoodItem::updateOrCreate(['name' => $name], ['category' => $cat, 'price' => $price, 'description' => null, 'is_active' => true]);
        }

        // ── Supplier ────────────────────────────────────────────
        $suppliers = [
            ['PT Sumber Segar', 'Bambang', '021-555-0101', 'sumbersegara@example.com', 'Food & Beverage', 'Jl. Raya Pasar Minggu No. 12, Jakarta'],
            ['CV Berkah Laundry', 'Ratna', '021-555-0102', 'berkahlaundry@example.com', 'Laundry', 'Jl. Melati No. 8, Jakarta'],
            ['PT Alat Hotelindo', 'Hendra', '021-555-0103', 'hotelindo@example.com', 'Perlengkapan Kamar', 'Jl. Sudirman No. 45, Jakarta'],
            ['UD Mekar Jaya', 'Sukarno', '021-555-0104', 'mekarjaya@example.com', 'Furniture', 'Jl. Industri No. 3, Tangerang'],
            ['Toko Listrik Prima', 'Yulianti', '021-555-0105', 'listrikprima@example.com', 'Elektrikal', 'Jl. Palmerah No. 21, Jakarta'],
        ];

        foreach ($suppliers as [$name, $cp, $phone, $email, $cat, $addr]) {
            Supplier::updateOrCreate(['name' => $name], [
                'contact_person' => $cp,
                'phone' => $phone,
                'email' => $email,
                'category' => $cat,
                'address' => $addr,
                'notes' => null,
                'is_active' => true,
            ]);
        }

        // ── Inventory ───────────────────────────────────────────
        $stocks = [
            ['Shampoo', 'KAM-001', 'Amenities', 'pcs', 120, 40, 3500, 'Gudang A'],
            ['Sabun Mandi', 'KAM-002', 'Amenities', 'pcs', 80, 40, 2800, 'Gudang A'],
            ['Handuk Putih', 'KAM-003', 'Linen', 'pcs', 60, 30, 65000, 'Gudang B'],
            ['Handuk Kaki', 'KAM-004', 'Linen', 'pcs', 35, 30, 28000, 'Gudang B'],
            ['Bed Sheet King', 'KAM-005', 'Linen', 'pcs', 45, 25, 120000, 'Gudang B'],
            ['Detergen 5kg', 'KAM-006', 'Kimia', 'kg', 15, 10, 45000, 'Gudang A'],
            ['Gelas Minum', 'F&B-001', 'F&B', 'pcs', 150, 60, 12000, 'Gudang Dapur'],
            ['Piring Makan', 'F&B-002', 'F&B', 'pcs', 20, 60, 18000, 'Gudang Dapur'],
            ['Bohlam LED', 'ENG-001', 'Elektrikal', 'pcs', 8, 20, 15000, 'Gudang Engineering'],
        ];

        foreach ($stocks as [$name, $sku, $cat, $unit, $qty, $min, $cost, $loc]) {
            InventoryItem::updateOrCreate(['sku' => $sku], [
                'name' => $name,
                'category' => $cat,
                'unit' => $unit,
                'quantity' => $qty,
                'min_stock' => $min,
                'cost_price' => $cost,
                'location' => $loc,
                'notes' => null,
                'is_active' => true,
            ]);
        }

        InventoryTransaction::create([
            'inventory_item_id' => InventoryItem::where('sku', 'ENG-001')->value('id'),
            'user_id' => $supervisor->id,
            'type' => 'out',
            'quantity' => 2,
            'unit_cost' => null,
            'reference' => 'MNT-260915',
            'notes' => 'Pemakaian perbaikan lampu kamar 205.',
            'transaction_at' => now(),
        ]);

        // ── Purchase Order ──────────────────────────────────────
        $supplier = Supplier::where('category', 'Perlengkapan Kamar')->first();
        $po = PurchaseOrder::create([
            'code' => 'PO-'.now()->format('ymd').'-DEM0',
            'supplier_id' => $supplier->id,
            'created_by' => $supervisor->id,
            'order_date' => Carbon::today(),
            'expected_date' => Carbon::today()->addDays(5),
            'status' => 'submitted',
            'total_amount' => 0,
            'notes' => 'Stok rutin bulanan.',
        ]);

        $poItems = [
            ['item_name' => 'Handuk Putih', 'quantity' => 20, 'unit' => 'pcs', 'unit_price' => 60000],
            ['item_name' => 'Bed Sheet King', 'quantity' => 15, 'unit' => 'pcs', 'unit_price' => 115000],
            ['item_name' => 'Sabun Mandi', 'quantity' => 100, 'unit' => 'pcs', 'unit_price' => 2600],
        ];
        $total = 0;
        foreach ($poItems as $i) {
            $amount = round($i['quantity'] * $i['unit_price'], 2);
            $total += $amount;
            $po->items()->create($i + ['amount' => $amount]);
        }
        $po->update(['total_amount' => $total]);

        // ── Maintenance ─────────────────────────────────────────
        MaintenanceRequest::create([
            'room_id' => \App\Models\Room::where('status', '!=', 'maintenance')->first()?->id,
            'title' => 'AC kamar tidak dingin',
            'category' => 'hvac',
            'priority' => 'high',
            'status' => 'pending',
            'description' => 'Tamu mengeluhkan AC tidak dingin sejak malam.',
            'requested_by' => $supervisor->id,
            'assigned_to' => User::where('role', 'engineering')->value('id'),
            'cost' => 0,
            'notes' => null,
        ]);

        MaintenanceRequest::create([
            'room_id' => null,
            'title' => 'Lampu koridor lantai 2 padam',
            'category' => 'electrical',
            'priority' => 'medium',
            'status' => 'in_progress',
            'description' => 'Dua bohlam di koridor lantai 2 perlu diganti.',
            'requested_by' => $supervisor->id,
            'assigned_to' => User::where('role', 'engineering')->value('id'),
            'cost' => 0,
            'notes' => null,
        ]);

        // ── Karyawan ────────────────────────────────────────────
        $employees = [
            ['Siti Rahayu', 'siti@simh.test', '0813-1000-002', 'Front Office Supervisor', 'front_office', 'active'],
            ['Agus Pratama', 'agus@simh.test', '0814-1000-003', 'Housekeeping', 'housekeeping', 'active'],
            ['Dewi Lestari', 'dewi@simh.test', '0815-1000-004', 'Accountant', 'finance', 'active'],
            ['Rizky Hidayat', 'rizky@simh.test', '0816-1000-005', 'Chef', 'fb', 'active'],
            ['Putri Maharani', 'putri@simh.test', '0817-1000-006', 'Purchasing Staff', 'purchasing', 'active'],
            ['Andi Kurniawan', 'andi@simh.test', '0818-1000-007', 'Engineer', 'engineering', 'active'],
            ['Maya Anggraini', 'maya@simh.test', '0819-1000-008', 'HR Generalist', 'hrd', 'on_leave'],
        ];

        foreach ($employees as [$name, $email, $phone, $pos, $dept, $status]) {
            Employee::updateOrCreate(['email' => $email], [
                'user_id' => User::where('email', $email)->value('id'),
                'name' => $name,
                'phone' => $phone,
                'position' => $pos,
                'department' => $dept,
                'join_date' => Carbon::today()->subMonths(6),
                'salary' => 4500000 + random_int(0, 8) * 500000,
                'status' => $status,
                'notes' => null,
            ]);
        }

        // ── Order F&B demo ──────────────────────────────────────
        $order = FoodOrder::create([
            'code' => 'FB-'.now()->format('ymd').'-DEM0',
            'user_id' => $supervisor->id,
            'guest_id' => null,
            'room_id' => \App\Models\Reservation::where('status', 'checked_in')->first()?->room_id,
            'table_no' => null,
            'order_type' => 'delivery',
            'status' => 'served',
            'subtotal' => 0,
            'tax' => 0,
            'total_amount' => 0,
            'notes' => 'Room service.',
            'ordered_at' => now(),
        ]);

        $orderItems = [
            ['Nasi Goreng Spesial', 45000, 2],
            ['Jus Alpukat', 20000, 2],
        ];
        $total = 0;
        foreach ($orderItems as [$name, $price, $qty]) {
            $amount = $price * $qty;
            $total += $amount;
            FoodOrderItem::create([
                'food_order_id' => $order->id,
                'food_item_id' => FoodItem::where('name', $name)->value('id'),
                'item_name' => $name,
                'quantity' => $qty,
                'unit_price' => $price,
                'amount' => $amount,
            ]);
        }
        $tax = round($total * 0.10, 2);
        $order->update(['subtotal' => $total, 'tax' => $tax, 'total_amount' => round($total + $tax, 2)]);
    }
}