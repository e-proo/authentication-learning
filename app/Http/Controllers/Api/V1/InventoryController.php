<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\Organization;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\Authorization\OrganizationAccess;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function adjust(Request $request, Organization $organization, OrganizationAccess $access, InventoryService $inventory): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'inventory.adjust');

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer',
                Rule::exists('warehouses', 'id')->where('organization_id', $organization->id)],
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity_delta' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'],
            'movement_type' => ['required', Rule::in(['opening_balance', 'restock', 'correction', 'damage', 'write_off'])],
            'metadata' => ['nullable', 'array'],
        ]);

        $warehouse = Warehouse::query()->where('organization_id', $organization->id)
            ->where('is_active', true)->whereKey($validated['warehouse_id'])->firstOrFail();

        $variant = ProductVariant::query()->whereKey($validated['product_variant_id'])
            ->whereHas('product', fn ($query) => $query->where('organization_id', $organization->id))
            ->first();

        if (! $variant) {
            return response()->json(['message' => 'The SKU does not belong to this organization.'], 422);
        }

        $key = $request->header('Idempotency-Key');
        if ($key !== null && (strlen($key) < 8 || strlen($key) > 128)) {
            return response()->json(['message' => 'Idempotency-Key must contain 8–128 characters.'], 422);
        }

        $movement = $inventory->adjust(
            (int) $warehouse->id,
            (int) $variant->id,
            (int) $validated['quantity_delta'],
            $validated['movement_type'],
            (int) $request->user()->id,
            $key,
            $validated['metadata'] ?? [],
        );

        $balance = InventoryBalance::query()->where('warehouse_id', $warehouse->id)
            ->where('product_variant_id', $variant->id)->firstOrFail();

        return response()->json(['data' => [
            'movement' => $movement,
            'balance' => $balance,
            'available' => $balance->available,
        ]]);
    }
}
