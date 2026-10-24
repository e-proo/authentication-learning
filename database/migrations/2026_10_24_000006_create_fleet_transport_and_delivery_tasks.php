<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_drivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('driver_code');
            $table->string('display_name');
            $table->string('phone', 40)->nullable();
            $table->string('driver_type')->default('employee')->index();
            $table->string('status')->default('active')->index();
            $table->string('license_number')->nullable();
            $table->date('license_expires_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])->on('branches')->restrictOnDelete();
            $table->unique(['organization_id', 'driver_code']);
            $table->unique(['organization_id', 'user_id']);
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('fleet_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->string('vehicle_code');
            $table->string('registration_plate')->nullable();
            $table->string('vehicle_type')->default('van')->index();
            $table->unsignedInteger('capacity_kg')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('status')->default('active')->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])->on('branches')->restrictOnDelete();
            $table->unique(['organization_id', 'vehicle_code']);
            $table->unique(['organization_id', 'registration_plate']);
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('transport_trips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('driver_id')->nullable();
            $table->foreignId('vehicle_id')->nullable();
            $table->string('trip_number')->unique();
            $table->string('trip_type')->index();
            $table->string('status')->default('planned')->index();
            $table->jsonb('origin_snapshot');
            $table->jsonb('destination_snapshot');
            $table->timestamp('planned_departure_at')->nullable();
            $table->timestamp('planned_arrival_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['organization_id', 'driver_id'])
                ->references(['organization_id', 'id'])->on('fleet_drivers')->restrictOnDelete();
            $table->foreign(['organization_id', 'vehicle_id'])
                ->references(['organization_id', 'id'])->on('fleet_vehicles')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status', 'planned_departure_at']);
        });

        Schema::create('delivery_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('shipment_id')->nullable();
            $table->foreignId('transport_trip_id')->nullable();
            $table->foreignId('driver_id')->nullable();
            $table->string('task_number')->unique();
            $table->string('task_type')->index();
            $table->string('status')->default('queued')->index();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->jsonb('location_snapshot');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 40)->nullable();
            $table->string('proof_reference')->nullable();
            $table->string('failure_reason')->nullable();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'shipment_id'])
                ->references(['organization_id', 'id'])->on('shipments')->restrictOnDelete();
            $table->foreign(['organization_id', 'transport_trip_id'])
                ->references(['organization_id', 'id'])->on('transport_trips')->restrictOnDelete();
            $table->foreign(['organization_id', 'driver_id'])
                ->references(['organization_id', 'id'])->on('fleet_drivers')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['transport_trip_id', 'sequence_number']);
        });

        Schema::create('delivery_task_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('delivery_task_id');
            $table->string('event_type')->index();
            $table->string('previous_status')->nullable();
            $table->string('status')->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->foreign(['organization_id', 'delivery_task_id'])
                ->references(['organization_id', 'id'])->on('delivery_tasks')->cascadeOnDelete();
            $table->index(['delivery_task_id', 'occurred_at']);
        });

        DB::statement("ALTER TABLE fleet_drivers ADD CONSTRAINT fleet_drivers_type_valid CHECK (driver_type IN ('employee', 'contractor', 'partner'))");
        DB::statement("ALTER TABLE fleet_drivers ADD CONSTRAINT fleet_drivers_status_valid CHECK (status IN ('active', 'inactive', 'suspended'))");
        DB::statement("ALTER TABLE fleet_vehicles ADD CONSTRAINT fleet_vehicles_type_valid CHECK (vehicle_type IN ('motorcycle', 'car', 'van', 'truck', 'heavy_truck', 'other'))");
        DB::statement("ALTER TABLE fleet_vehicles ADD CONSTRAINT fleet_vehicles_status_valid CHECK (status IN ('active', 'maintenance', 'inactive'))");
        DB::statement("ALTER TABLE transport_trips ADD CONSTRAINT transport_trips_type_valid CHECK (trip_type IN ('collection', 'hub_transfer', 'linehaul', 'last_mile', 'return_pickup'))");
        DB::statement("ALTER TABLE transport_trips ADD CONSTRAINT transport_trips_status_valid CHECK (status IN ('planned', 'assigned', 'departed', 'completed', 'cancelled'))");
        DB::statement("ALTER TABLE delivery_tasks ADD CONSTRAINT delivery_tasks_type_valid CHECK (task_type IN ('collection', 'hub_delivery', 'last_mile', 'return_pickup'))");
        DB::statement("ALTER TABLE delivery_tasks ADD CONSTRAINT delivery_tasks_status_valid CHECK (status IN ('queued', 'assigned', 'en_route', 'arrived', 'completed', 'failed', 'cancelled'))");
        DB::statement('ALTER TABLE delivery_tasks ADD CONSTRAINT delivery_tasks_attempt_positive CHECK (attempt_number > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_task_events');
        Schema::dropIfExists('delivery_tasks');
        Schema::dropIfExists('transport_trips');
        Schema::dropIfExists('fleet_vehicles');
        Schema::dropIfExists('fleet_drivers');
    }
};
