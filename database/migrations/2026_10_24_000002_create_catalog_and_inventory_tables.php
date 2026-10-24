<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('mode')->default('marketplace')->index();
            $table->char('currency', 3)->default('YER');
            $table->boolean('is_active')->default(true)->index();
            $table->jsonb('settings')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('product_type')->default('physical')->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'store_id'])
                ->references(['organization_id', 'id'])->on('stores')->cascadeOnDelete();
            $table->unique(['store_id', 'slug']);
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->string('name')->nullable();
            $table->jsonb('attributes')->nullable();
            $table->string('barcode')->nullable()->index();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['product_id', 'sku']);
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable();
            $table->string('name');
            $table->string('code');
            $table->string('kind')->default('warehouse')->index();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->foreign(['organization_id', 'branch_id'])
                ->references(['organization_id', 'id'])->on('branches')->restrictOnDelete();
            $table->unique(['organization_id', 'code']);
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('on_hand')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_variant_id']);
            $table->check('reserved <= on_hand');
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('movement_type')->index();
            $table->integer('quantity_delta');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->unique(['warehouse_id', 'idempotency_key']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
    }
};
