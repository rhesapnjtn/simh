<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_venues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->unsignedInteger('capacity_standing')->default(0);
            $table->unsignedInteger('capacity_seated')->default(0);
            $table->decimal('base_rate', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->json('facilities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ayce_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->decimal('price_per_pax', 12, 2);
            $table->unsignedInteger('min_pax')->default(1);
            $table->unsignedInteger('max_pax')->nullable();
            $table->unsignedInteger('duration_minutes')->default(120);
            $table->text('description')->nullable();
            $table->text('includes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('event_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('event_type', 30);
            $table->string('title', 200);
            $table->foreignId('venue_id')->nullable()->constrained('event_venues')->nullOnDelete();
            $table->foreignId('ayce_package_id')->nullable()->constrained('ayce_packages')->nullOnDelete();
            $table->string('contact_name', 150);
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('pax')->default(1);
            $table->unsignedInteger('days')->default(1);
            $table->decimal('venue_rate', 12, 2)->default(0);
            $table->decimal('price_per_pax', 12, 2)->default(0);
            $table->json('addons')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('status', 20)->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('setup_at')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            $table->index('event_type');
            $table->index('status');
            $table->index(['venue_id', 'start_date', 'end_date']);
        });

        Schema::create('event_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->dateTime('paid_at');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_payments');
        Schema::dropIfExists('event_bookings');
        Schema::dropIfExists('ayce_packages');
        Schema::dropIfExists('event_venues');
    }
};