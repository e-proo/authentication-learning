<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('order_id');
            $table->string('invoice_number')->unique();
            $table->string('status')->default('issued')->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('shipping_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('paid_total_minor')->default(0);
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'order_id'])
                ->references(['organization_id', 'id'])->on('orders')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'order_id']);


        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('invoice_id');
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->string('description_snapshot');
            $table->string('sku_snapshot');
            $table->char('currency', 3);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
            $table->foreign(['organization_id', 'invoice_id'])
                ->references(['organization_id', 'id'])->on('invoices')->cascadeOnDelete();
            $table->unique(['invoice_id', 'order_item_id']);


        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('invoice_id');
            $table->foreignId('order_id');
            $table->string('payment_method')->index();
            $table->string('provider')->nullable();
            $table->string('reference')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('idempotency_key')->nullable();
            $table->char('request_fingerprint', 64)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'invoice_id'])
                ->references(['organization_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->foreign(['organization_id', 'order_id'])
                ->references(['organization_id', 'id'])->on('orders')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'status', 'created_at']);

        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('payment_id');
            $table->string('event_type')->index();
            $table->string('previous_status')->nullable();
            $table->string('status')->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['organization_id', 'payment_id'])
                ->references(['organization_id', 'id'])->on('payments')->cascadeOnDelete();
            $table->index(['payment_id', 'created_at']);
        });

        // PostgreSQL CHECK constraints are expressed as SQL statements because Blueprint has no portable check() command.
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_total_reconciles CHECK (total_minor = subtotal_minor - discount_minor + shipping_minor + tax_minor)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_paid_lte_total CHECK (paid_total_minor <= total_minor)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_amounts_nonnegative CHECK (subtotal_minor >= 0 AND discount_minor >= 0 AND discount_minor <= subtotal_minor AND shipping_minor >= 0 AND tax_minor >= 0 AND total_minor >= 0 AND paid_total_minor >= 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT invoice_items_amounts_nonnegative CHECK (unit_price_minor >= 0 AND discount_minor >= 0 AND tax_minor >= 0 AND line_total_minor >= 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT invoice_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT invoice_items_line_total_reconciles CHECK (line_total_minor = unit_price_minor * quantity - discount_minor + tax_minor)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount_minor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
