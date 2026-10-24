<?php

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InventoryService
{
    /**
     * Apply a stock adjustment atomically and append an inventory movement.
     *
     * The caller must already have verified that the actor may manage the
     * selected organization, warehouse, and SKU.
     */
    public function adjust(
        int $warehouseId,
        int $variantId,
        int $quantityDelta,
        string $movementType,
        ?int $userId = null,
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
            $warehouseId,
            $variantId,
            $quantityDelta,
            $movementType,
            $userId,
            $idempotencyKey,
            $metadata,
        ): InventoryMovement {
            if ($idempotencyKey !== null) {
                $existing = InventoryMovement::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    $sameOperation = (int) $existing->warehouse_id === $warehouseId
                        && (int) $existing->product_variant_id === $variantId
                        && (int) $existing->quantity_delta === $quantityDelta
                        && $existing->movement_type === $movementType;

                    if (! $sameOperation) {
                        throw new RuntimeException('Idempotency key was already used for a different inventory operation.');
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

            $nextOnHand = $balance->on_hand + $quantityDelta;

            if ($nextOnHand < 0) {
                throw new RuntimeException('The adjustment would make on-hand inventory negative.');
            }

            if ($nextOnHand < $balance->reserved) {
                throw new RuntimeException('The adjustment would reduce on-hand inventory below the reserved quantity.');
            }

            $balance->on_hand = $nextOnHand;
            $balance->save();

            return InventoryMovement::query()->create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'movement_type' => $movementType,
                'quantity_delta' => $quantityDelta,
                'idempotency_key' => $idempotencyKey,
                'metadata' => $metadata ?: null,
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        }, attempts: 3);
    }
}
