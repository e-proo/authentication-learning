<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id');
            $table->string('return_number')->unique();
            $table->string('status')->default('requested')->index();
            $table->string('reason')->index();
            $table->text('request_notes')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'order_id'])
                ->references(['organization_id', 'id'])->on('orders')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status', 'created_at']);
        });

        Schema::create('return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('return_case_id');
            $table->foreignId('order_item_id');
            $table->unsignedInteger('requested_quantity');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'return_case_id'])
                ->references(['organization_id', 'id'])->on('return_cases')->cascadeOnDelete();
            $table->foreign(['organization_id', 'order_item_id'])
                ->references(['organization_id', 'id'])->on('order_items')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->unique(['return_case_id', 'order_item_id']);
        });

        Schema::create('return_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('return_item_id')->unique();
            $table->foreignId('warehouse_id')->nullable();
            $table->unsignedInteger('quantity_received');
            $table->string('disposition')->index();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();
            $table->foreign(['organization_id', 'return_item_id'])
                ->references(['organization_id', 'id'])->on('return_items')->cascadeOnDelete();
            $table->foreign(['organization_id', 'warehouse_id'])
                ->references(['organization_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->index(['organization_id', 'disposition', 'received_at']);
        });

        Schema::create('return_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('return_case_id');
            $table->string('previous_status')->nullable();
            $table->string('status')->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['organization_id', 'return_case_id'])
                ->references(['organization_id', 'id'])->on('return_cases')->cascadeOnDelete();
            $table->index(['return_case_id', 'created_at']);
        });

        DB::statement("ALTER TABLE return_cases ADD CONSTRAINT return_cases_status_valid CHECK (status IN ('requested', 'approved', 'rejected', 'received', 'closed'))");
        DB::statement('ALTER TABLE return_items ADD CONSTRAINT return_items_quantity_positive CHECK (requested_quantity > 0)');
        DB::statement("ALTER TABLE return_receipts ADD CONSTRAINT return_receipts_disposition_valid CHECK (disposition IN ('restock', 'damaged', 'discard'))");
        DB::statement('ALTER TABLE return_receipts ADD CONSTRAINT return_receipts_quantity_positive CHECK (quantity_received > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('return_status_histories');
        Schema::dropIfExists('return_receipts');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('return_cases');
    }
};
