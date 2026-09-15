<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AycePackageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EventBookingController;
use App\Http\Controllers\EventVenueController;
use App\Http\Controllers\FoodItemController;
use App\Http\Controllers\FoodOrderController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\HousekeepingController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\ServiceChargeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth')->group(function () {
    Route::middleware(\App\Http\Middleware\ServeSpaForHtml::class)->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view');

    Route::get('/room-types/all', [RoomTypeController::class, 'all'])->middleware('permission:room_types.view');
    Route::get('/room-types', [RoomTypeController::class, 'index'])->middleware('permission:room_types.view');
    Route::post('/room-types', [RoomTypeController::class, 'store'])->middleware('permission:room_types.create');
    Route::put('/room-types/{roomType}', [RoomTypeController::class, 'update'])->middleware('permission:room_types.update');
    Route::delete('/room-types/{roomType}', [RoomTypeController::class, 'destroy'])->middleware('permission:room_types.delete');

    Route::get('/rooms/available', [RoomController::class, 'available'])->middleware('permission:rooms.view');
    Route::get('/rooms/occupancy', [RoomController::class, 'occupancy'])->middleware('permission:rooms.view');
    Route::get('/rooms', [RoomController::class, 'index'])->middleware('permission:rooms.view');
    Route::post('/rooms', [RoomController::class, 'store'])->middleware('permission:rooms.create');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->middleware('permission:rooms.update');
    Route::post('/rooms/{room}/status', [RoomController::class, 'changeStatus'])->middleware('permission:rooms.status');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->middleware('permission:rooms.delete');

    Route::get('/guests/search', [GuestController::class, 'search'])->middleware('permission:guests.view');
    Route::get('/guests/{guest}/reservations', [GuestController::class, 'reservations'])->middleware('permission:guests.view');
    Route::get('/guests', [GuestController::class, 'index'])->middleware('permission:guests.view');
    Route::post('/guests', [GuestController::class, 'store'])->middleware('permission:guests.create');
    Route::put('/guests/{guest}', [GuestController::class, 'update'])->middleware('permission:guests.update');
    Route::delete('/guests/{guest}', [GuestController::class, 'destroy'])->middleware('permission:guests.delete');

    Route::get('/reservations/pricing', [ReservationController::class, 'pricing'])->middleware('permission:reservations.view');
    Route::get('/reservations', [ReservationController::class, 'index'])->middleware('permission:reservations.view,reservations.own');
    Route::post('/reservations', [ReservationController::class, 'store'])->middleware('permission:reservations.create');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->middleware('permission:reservations.view,reservations.own');
    Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->middleware('permission:reservations.update');
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->middleware('permission:reservations.delete');
    Route::post('/reservations/{reservation}/assign-room', [ReservationController::class, 'assignRoom'])->middleware('permission:reservations.assign');
    Route::post('/reservations/{reservation}/confirm', [ReservationController::class, 'confirm'])->middleware('permission:reservations.confirm');
    Route::post('/reservations/{reservation}/check-in', [ReservationController::class, 'checkIn'])->middleware('permission:reservations.checkin');
    Route::post('/reservations/{reservation}/check-out', [ReservationController::class, 'checkOut'])->middleware('permission:reservations.checkout');
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->middleware('permission:reservations.cancel');
    Route::post('/reservations/{reservation}/no-show', [ReservationController::class, 'noShow'])->middleware('permission:reservations.no_show');

    Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:payments.view');
    Route::post('/reservations/{reservation}/payments', [PaymentController::class, 'store'])->middleware('permission:payments.create');
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->middleware('permission:payments.delete');

    Route::get('/reservations/{reservation}/charges', [ServiceChargeController::class, 'index'])->middleware('permission:charges.view');
    Route::post('/reservations/{reservation}/charges', [ServiceChargeController::class, 'store'])->middleware('permission:charges.create');
    Route::post('/charges/{charge}/void', [ServiceChargeController::class, 'void'])->middleware('permission:charges.void');

    Route::get('/housekeeping', [HousekeepingController::class, 'index'])->middleware('permission:housekeeping.view');
    Route::post('/housekeeping', [HousekeepingController::class, 'store'])->middleware('permission:housekeeping.create');
    Route::put('/housekeeping/{task}', [HousekeepingController::class, 'update'])->middleware('permission:housekeeping.update');
    Route::post('/housekeeping/{task}/status', [HousekeepingController::class, 'changeStatus'])->middleware('permission:housekeeping.status');
    Route::delete('/housekeeping/{task}', [HousekeepingController::class, 'destroy'])->middleware('permission:housekeeping.delete');

    Route::get('/reports/revenue', [ReportController::class, 'revenue'])->middleware('permission:reports.view');
    Route::get('/reports/occupancy', [ReportController::class, 'occupancy'])->middleware('permission:reports.view');

    Route::get('/logs', [ActivityLogController::class, 'index'])->middleware('permission:logs.view');

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings', [SettingsController::class, 'update']);
    });

    // ── Modul F&B ─────────────────────────────────────────────
    Route::get('/food-items', [FoodItemController::class, 'index'])->middleware('permission:fb.view');
    Route::get('/food-items/all', [FoodItemController::class, 'all'])->middleware('permission:fb.view');
    Route::post('/food-items', [FoodItemController::class, 'store'])->middleware('permission:fb.create');
    Route::put('/food-items/{item}', [FoodItemController::class, 'update'])->middleware('permission:fb.update');
    Route::delete('/food-items/{item}', [FoodItemController::class, 'destroy'])->middleware('permission:fb.delete');

    Route::get('/food-orders', [FoodOrderController::class, 'index'])->middleware('permission:fb.view');
    Route::post('/food-orders', [FoodOrderController::class, 'store'])->middleware('permission:fb.create');
    Route::put('/food-orders/{order}', [FoodOrderController::class, 'update'])->middleware('permission:fb.update');
    Route::post('/food-orders/{order}/status', [FoodOrderController::class, 'changeStatus'])->middleware('permission:fb.status');
    Route::delete('/food-orders/{order}', [FoodOrderController::class, 'destroy'])->middleware('permission:fb.delete');

    // ── Modul Purchasing ──────────────────────────────────────
    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view');
    Route::get('/suppliers/all', [SupplierController::class, 'all'])->middleware('permission:suppliers.view');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('permission:suppliers.manage');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.manage');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:suppliers.manage');

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:purchasing.view');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:purchasing.create');
    Route::put('/purchase-orders/{po}', [PurchaseOrderController::class, 'update'])->middleware('permission:purchasing.update');
    Route::post('/purchase-orders/{po}/status', [PurchaseOrderController::class, 'changeStatus'])->middleware('permission:purchasing.status');
    Route::delete('/purchase-orders/{po}', [PurchaseOrderController::class, 'destroy'])->middleware('permission:purchasing.delete');

    // ── Modul Inventory ───────────────────────────────────────
    Route::get('/inventory-items', [InventoryItemController::class, 'index'])->middleware('permission:inventory.view');
    Route::get('/inventory-items/summaries', [InventoryItemController::class, 'summaries'])->middleware('permission:inventory.view');
    Route::post('/inventory-items', [InventoryItemController::class, 'store'])->middleware('permission:inventory.create');
    Route::put('/inventory-items/{item}', [InventoryItemController::class, 'update'])->middleware('permission:inventory.update');
    Route::get('/inventory-items/{item}/transactions', [InventoryItemController::class, 'transactions'])->middleware('permission:inventory.view');
    Route::post('/inventory-items/{item}/adjust', [InventoryItemController::class, 'adjust'])->middleware('permission:inventory.transfer');
    Route::delete('/inventory-items/{item}', [InventoryItemController::class, 'destroy'])->middleware('permission:inventory.delete');

    // ── Modul Engineering / Maintenance ───────────────────────
    Route::get('/maintenance', [MaintenanceController::class, 'index'])->middleware('permission:engineering.view');
    Route::post('/maintenance', [MaintenanceController::class, 'store'])->middleware('permission:engineering.create');
    Route::put('/maintenance/{m}', [MaintenanceController::class, 'update'])->middleware('permission:engineering.update');
    Route::post('/maintenance/{m}/status', [MaintenanceController::class, 'changeStatus'])->middleware('permission:engineering.status');
    Route::delete('/maintenance/{m}', [MaintenanceController::class, 'destroy'])->middleware('permission:engineering.delete');

    // ── Modul HR / HRD ────────────────────────────────────────
    Route::get('/employees', [EmployeeController::class, 'index'])->middleware('permission:hr.view');
    Route::get('/employees/all', [EmployeeController::class, 'all'])->middleware('permission:hr.view');
    Route::post('/employees', [EmployeeController::class, 'store'])->middleware('permission:hr.create');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:hr.update');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:hr.delete');

    // ── Modul Event & Venue ────────────────────────────────────
    Route::get('/event-venues', [EventVenueController::class, 'index'])->middleware('permission:venues.manage');
    Route::get('/event-venues/all', [EventVenueController::class, 'all'])->middleware('permission:events.view');
    Route::post('/event-venues', [EventVenueController::class, 'store'])->middleware('permission:venues.manage');
    Route::put('/event-venues/{venue}', [EventVenueController::class, 'update'])->middleware('permission:venues.manage');
    Route::delete('/event-venues/{venue}', [EventVenueController::class, 'destroy'])->middleware('permission:venues.manage');

    Route::get('/ayce-packages', [AycePackageController::class, 'index'])->middleware('permission:ayce.manage');
    Route::get('/ayce-packages/all', [AycePackageController::class, 'all'])->middleware('permission:events.view');
    Route::post('/ayce-packages', [AycePackageController::class, 'store'])->middleware('permission:ayce.manage');
    Route::put('/ayce-packages/{package}', [AycePackageController::class, 'update'])->middleware('permission:ayce.manage');
    Route::delete('/ayce-packages/{package}', [AycePackageController::class, 'destroy'])->middleware('permission:ayce.manage');

    Route::get('/events', [EventBookingController::class, 'index'])->middleware('permission:events.view');
    Route::post('/events', [EventBookingController::class, 'store'])->middleware('permission:events.create');
    Route::get('/events/{booking}', [EventBookingController::class, 'show'])->middleware('permission:events.view');
    Route::put('/events/{booking}', [EventBookingController::class, 'update'])->middleware('permission:events.update');
    Route::delete('/events/{booking}', [EventBookingController::class, 'destroy'])->middleware('permission:events.delete');
    Route::post('/events/{booking}/status', [EventBookingController::class, 'changeStatus'])->middleware('permission:events.status');
    Route::post('/events/{booking}/payments', [EventBookingController::class, 'storePayment'])->middleware('permission:events.payment');
    Route::delete('/event-payments/{payment}', [EventBookingController::class, 'destroyPayment'])->middleware('permission:events.payment');
    });
});

Route::get('/reports/export', [ReportController::class, 'export'])
    ->middleware(['auth', 'permission:reports.export']);

Route::get('/{any}', fn () => view('app'))->where('any', '.*');