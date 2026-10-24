<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->char('currency', 3)->default('YER');
            $table->string('customer_type')->default('all')->index();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('price_list_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('compare_at_price_minor')->nullable();
            $table->unsignedInteger('minimum_quantity')->default(1);
            $table->timestamps();
            $table->unique(['price_list_id', 'product_variant_id']);
            $table->check('minimum_quantity > 0');
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id');
            $table->foreignId('price_list_id');
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('buyer_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('confirmed')->index();
            $table->string('payment_status')->default('pending')->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('shipping_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->jsonb('shipping_address_snapshot');
            $table->jsonb('billing_address_snapshot')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->char('request_fingerprint', 64)->nullable();
            $table->text('customer_note')->nullable();
            $table->timestamp('placed_at')->useCurrent();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'store_id'])
                ->references(['organization_id', 'id'])->on('stores')->restrictOnDelete();
            $table->foreign(['organization_id', 'price_list_id'])
                ->references(['organization_id', 'id'])->on('price_lists')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->check('total_minor = subtotal_minor - discount_minor + shipping_minor + tax_minor');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('order_id');
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id');
            $table->string('product_name_snapshot');
            $table->string('sku_snapshot');
            $table->string('variant_name_snapshot')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
            $table->foreign(['organization_id', 'order_id'])
                ->references(['organization_id', 'id'])->on('orders')->cascadeOnDelete();
            $table->foreign(['organization_id', 'warehouse_id'])
                ->references(['organization_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->check('quantity > 0');
            $table->check('line_total_minor = unit_price_minor * quantity - discount_minor + tax_minor');
        });

        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('order_item_id');
            $table->foreignId('warehouse_id');
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('reserved')->index();
            $table->timestamp('reserved_at')->useCurrent();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'order_item_id'])
                ->references(['organization_id', 'id'])->on('order_items')->cascadeOnDelete();
            $table->foreign(['organization_id', 'warehouse_id'])
                ->references(['organization_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->unique('order_item_id');
            $table->check('quantity > 0');
        });

        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('order_id');
            $table->string('previous_status')->nullable();
            $table->string('status')->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['organization_id', 'order_id'])
                ->references(['organization_id', 'id'])->on('orders')->cascadeOnDelete();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('fulfillments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('order_id');
            $table->foreignId('warehouse_id');
            $table->string('fulfillment_number')->unique();
            $table->string('status')->default('picking')->index();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'order_id'])
                ->references(['organization_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['organization_id', 'warehouse_id'])
                ->references(['organization_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->unique(['order_id', 'warehouse_id']);
        });

        Schema::create('fulfillment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('fulfillment_id');
            $table->foreignId('order_item_id')->unique();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->foreign(['organization_id', 'fulfillment_id'])
                ->references(['organization_id', 'id'])->on('fulfillments')->cascadeOnDelete();
            $table->foreign(['organization_id', 'order_item_id'])
                ->references(['organization_id', 'id'])->on('order_items')->restrictOnDelete();
            $table->check('quantity > 0');
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('order_id');
            $table->foreignId('fulfillment_id');
            $table->string('shipment_number')->unique();
            $table->string('carrier_type')->default('internal')->index();
            $table->string('carrier_name')->nullable();
            $table->string('tracking_number')->nullable()->index();
            $table->string('status')->default('created')->index();
            $table->jsonb('delivery_address_snapshot');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'order_id'])
                ->references(['organization_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['organization_id', 'fulfillment_id'])
                ->references(['organization_id', 'id'])->on('fulfillments')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->unique('fulfillment_id');
        });

        Schema::create('shipment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('shipment_id');
            $table->foreignId('order_item_id');
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->foreign(['organization_id', 'shipment_id'])
                ->references(['organization_id', 'id'])->on('shipments')->cascadeOnDelete();
            $table->foreign(['organization_id', 'order_item_id'])
                ->references(['organization_id', 'id'])->on('order_items')->restrictOnDelete();
            $table->unique(['shipment_id', 'order_item_id']);
            $table->check('quantity > 0');
        });

        Schema::create('shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('shipment_id');
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->foreign(['organization_id', 'shipment_id'])
                ->references(['organization_id', 'id'])->on('shipments')->cascadeOnDelete();
            $table->index(['shipment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('fulfillment_items');
        Schema::dropIfExists('fulfillments');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};
