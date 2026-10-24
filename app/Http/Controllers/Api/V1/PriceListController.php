<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\ProductVariant;
use App\Services\Authorization\OrganizationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PriceListController extends Controller
{
    public function index(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'catalog.products.view');

        return response()->json([
            'data' => PriceList::query()->where('organization_id', $organization->id)
                ->withCount('items')->orderBy('name')->paginate(25),
        ]);
    }

    public function store(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'catalog.products.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('price_lists', 'code')->where('organization_id', $organization->id)],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'customer_type' => ['sometimes', Rule::in(['all', 'B2B', 'B2C'])],
            'is_default' => ['sometimes', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'settings' => ['nullable', 'array'],
        ]);

        $priceList = DB::transaction(function () use ($organization, $validated): PriceList {
            $isDefault = (bool) ($validated['is_default'] ?? false);
            if ($isDefault) {
                PriceList::query()->where('organization_id', $organization->id)->update(['is_default' => false]);
            }

            return PriceList::query()->create([
                ...$validated,
                'organization_id' => $organization->id,
                'customer_type' => $validated['customer_type'] ?? 'all',
                'is_default' => $isDefault,
                'is_active' => true,
            ]);
        });

        return response()->json(['data' => $priceList], 201);
    }

    public function upsertItem(Request $request, Organization $organization, string $priceList, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'catalog.products.manage');

        $list = PriceList::query()->where('organization_id', $organization->id)
            ->whereKey($priceList)->where('is_active', true)->firstOrFail();

        $validated = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'unit_price_minor' => ['required', 'integer', 'min:0', 'max:9000000000000000'],
            'compare_at_price_minor' => ['nullable', 'integer', 'min:0', 'max:9000000000000000'],
            'minimum_quantity' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
        ]);

        $variant = ProductVariant::query()->whereKey($validated['product_variant_id'])
            ->whereHas('product', fn ($query) => $query->where('organization_id', $organization->id))
            ->first();

        if (! $variant) {
            return response()->json(['message' => 'The SKU does not belong to this organization.'], 422);
        }

        $item = PriceListItem::query()->updateOrCreate(
            ['price_list_id' => $list->id, 'product_variant_id' => $variant->id],
            [
                'unit_price_minor' => $validated['unit_price_minor'],
                'compare_at_price_minor' => $validated['compare_at_price_minor'] ?? null,
                'minimum_quantity' => $validated['minimum_quantity'] ?? 1,
            ],
        );

        return response()->json(['data' => $item]);
    }
}
