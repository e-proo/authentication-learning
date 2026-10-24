<?php

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * Apply a stock adjustment atomically and append an inventory movement.
     *
     * The caller must already have verified that the actor may manage the
     * selected organization, warehouse, and SKU.
     */
    public function adjust(
        Organization $organization,
        int $warehouseId,
        int $variantId,
        int $quantityDelta,
        string $movementType,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): InventoryMovement {
        if ($quantityDelta === 0) {
            throw new InvalidArgumentException('Inventory adjustments must not be zero.');
        }

        if (trim($movementType) === '') {
            throw new InvalidArgumentException('A movement type is required.');
        }

        return DB::transaction(function () use (
            $organization,
            $warehouseId,
            $variantId,
            $quantityDelta,
            $movementType,
            $actor,
            $idempotencyKey,
            $metadata,
        ): InventoryMovement {
            $warehouse = Warehouse::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->whereKey($warehouseId)
                ->firstOrFail();

            $variant = ProductVariant::query()
                ->whereKey($variantId)
                ->where('is_active', true)
                ->whereHas('product', fn ($query) => $query
                    ->where('organization_id', $organization->id))
                ->firstOrFail();

            if ($idempotencyKey !== null) {
                $existing = InventoryMovement::query()
                    ->where('warehouse_id', $warehouseId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    $sameOperation = (int) $existing->warehouse_id === $warehouseId
                        && (int) $existing->product_variant_id === $variantId
                        && (int) $existing->quantity_delta === $quantityDelta
                        && $existing->movement_type === $movementType;

                    if (! $sameOperation) {
                        throw new ConflictHttpException('Idempotency key was already used for a different inventory operation.');
                    }

                    return $existing;
                }
            }

            InventoryBalance::query()->firstOrCreate(
                ['warehouse_id' => $warehouseId, 'product_variant_id' => $variantId],
                ['on_hand' => 0, 'reserved' => 0, 'reorder_level' => 0],
            );

            $balance = InventoryBalance::query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->firstOrFail();

            // Re-check after waiting for the row lock so concurrent retries return the original movement.
            if ($idempotencyKey !== null) {
                $concurrentRetry = InventoryMovement::query()
                    ->where('warehouse_id', $warehouseId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($concurrentRetry) {
                    $sameOperation = (int) $concurrentRetry->warehouse_id === $warehouseId
                        && (int) $concurrentRetry->product_variant_id === $variantId
                        && (int) $concurrentRetry->quantity_delta === $quantityDelta
                        && $concurrentRetry->movement_type === $movementType;

                    if (! $sameOperation) {
                        throw new ConflictHttpException('Idempotency key was already used for a different inventory operation.');
                    }

                    return $concurrentRetry;
                }
            }

            $nextOnHand = $balance->on_hand + $quantityDelta;

            if ($nextOnHand < 0) {
                throw ValidationException::withMessages(['quantity_delta' => ['The adjustment would make on-hand inventory negative.']]);
            }

            if ($nextOnHand < $balance->reserved) {
                throw ValidationException::withMessages(['quantity_delta' => ['The adjustment would reduce on-hand inventory below the reserved quantity.']]);
            }

            $balance->on_hand = $nextOnHand;
            $balance->save();

            $movement = InventoryMovement::query()->create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'movement_type' => $movementType,
                'quantity_delta' => $quantityDelta,
                'idempotency_key' => $idempotencyKey,
                'metadata' => $metadata ?: null,
                'created_by' => $actor?->id,
                'created_at' => now(),
            ]);

            $this->audit->record($actor, $organization, 'inventory.stock_adjusted', $movement, [
                'warehouse_id' => $warehouse->id,
                'product_variant_id' => $variant->id,
                'movement_type' => $movementType,
                'quantity_delta' => $quantityDelta,
                'balance_on_hand' => $balance->on_hand,
                'balance_reserved' => $balance->reserved,
            ]);

            return $movement;
        }, attempts: 3);
    }
}
