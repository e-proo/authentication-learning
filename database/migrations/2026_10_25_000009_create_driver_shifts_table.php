<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('driver_id');
            $table->foreignId('vehicle_id')->nullable();
            $table->string('shift_code')->unique();
            $table->string('status')->default('active');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('odometer_start')->nullable();
            $table->unsignedBigInteger('odometer_end')->nullable();
            $table->jsonb('pre_trip_checklist')->nullable();
            $table->jsonb('post_trip_checklist')->nullable();
            $table->string('start_location')->nullable();
            $table->string('end_location')->nullable();
            $table->string('start_device_id', 180)->nullable();
            $table->string('end_device_id', 180)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign(['organization_id', 'driver_id'])
                ->references(['organization_id', 'id'])->on('fleet_drivers')->restrictOnDelete();
            $table->foreign(['organization_id', 'vehicle_id'])
                ->references(['organization_id', 'id'])->on('fleet_vehicles')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'driver_id', 'status']);
            $table->index(['organization_id', 'vehicle_id', 'status']);
        });

        DB::statement("ALTER TABLE driver_shifts ADD CONSTRAINT driver_shifts_status_valid CHECK (status IN ('active', 'ended', 'needs_review'))");
        DB::statement('ALTER TABLE driver_shifts ADD CONSTRAINT driver_shifts_odometer_valid CHECK (odometer_start IS NULL OR odometer_end IS NULL OR odometer_end >= odometer_start)');
        DB::statement("CREATE UNIQUE INDEX driver_shifts_one_active_per_driver_idx ON driver_shifts (organization_id, driver_id) WHERE status = 'active'");
        DB::statement("CREATE UNIQUE INDEX driver_shifts_one_active_per_vehicle_idx ON driver_shifts (organization_id, vehicle_id) WHERE status = 'active' AND vehicle_id IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS driver_shifts_one_active_per_vehicle_idx');
        DB::statement('DROP INDEX IF EXISTS driver_shifts_one_active_per_driver_idx');
        Schema::dropIfExists('driver_shifts');
    }
};
