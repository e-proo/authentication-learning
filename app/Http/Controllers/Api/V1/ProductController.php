<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Product;
use App\Services\Authorization\OrganizationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'catalog.products.view');

        return response()->json(Product::query()
            ->where('organization_id', $organization->id)
            ->with('variants:id,product_id,sku,name,attributes,is_active')
            ->latest()
            ->paginate(25));
    }

    public function store(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'catalog.products.manage');

        $validated = $request->validate([
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')->where('organization_id', $organization->id)],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->where('store_id', $request->input('store_id'))],
            'description' => ['nullable', 'string', 'max:20000'],
            'product_type' => ['sometimes', Rule::in(['physical', 'digital', 'service'])],
            'metadata' => ['nullable', 'array'],
        ]);

        $product = Product::query()->create([
            ...$validated,
            'organization_id' => $organization->id,
            'status' => 'draft',
        ]);

        return response()->json(['data' => $product], 201);
    }

    public function storeVariant(Request $request, Organization $organization, string $product, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'catalog.products.manage');

        $productRecord = Product::query()
            ->where('organization_id', $organization->id)
            ->whereKey($product)
            ->firstOrFail();

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100', Rule::unique('product_variants', 'sku')->where('product_id', $productRecord->id)],
            'name' => ['nullable', 'string', 'max:180'],
            'attributes' => ['nullable', 'array'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        return response()->json([
            'data' => $productRecord->variants()->create([...$validated, 'is_active' => true]),
        ], 201);
    }

    public function publish(Request $request, Organization $organization, string $product, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'catalog.products.manage');

        $record = Product::query()
            ->where('organization_id', $organization->id)
            ->with('store')
            ->whereKey($product)
            ->firstOrFail();

        if (! $record->store || ! $record->store->is_active) {
            return response()->json(['message' => 'The store is inactive.'], 409);
        }

        if (! $record->variants()->where('is_active', true)->exists()) {
            return response()->json(['message' => 'Add at least one active SKU before publishing.'], 422);
        }

        $record->status = 'published';
        $record->save();

        return response()->json(['data' => $record->fresh('variants')]);
    }
}
