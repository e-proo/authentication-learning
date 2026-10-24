<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->ensureMembership($request, $organization, 'catalog.products.view');

        $products = Product::query()
            ->where('organization_id', $organization->id)
            ->with('variants:id,product_id,sku,name,attributes,is_active')
            ->latest()
            ->paginate(25);

        return response()->json($products);
    }

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $this->ensureMembership($request, $organization, 'catalog.products.create');

        $validated = $request->validate([
            'store_id' => [
                'required',
                'integer',
                Rule::exists('stores', 'id')->where('organization_id', $organization->id),
            ],
            'name' => ['required', 'string', 'max:180'],
            'slug' => [
                'required',
                'string',
                'max:200',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->where('store_id', $request->input('store_id')),
            ],
            'description' => ['nullable', 'string', 'max:20000'],
            'product_type' => ['sometimes', Rule::in(['physical', 'digital', 'service'])],
            'metadata' => ['nullable', 'array'],
        ]);

        $product = Product::create([
            ...$validated,
            'organization_id' => $organization->id,
            'status' => 'draft',
        ]);

        return response()->json(['data' => $product], 201);
    }

    private function ensureMembership(Request $request, Organization $organization, string $permission): void
    {
        $membership = $request->user()
            ->memberships()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->with('roles.permissions')
            ->first();

        if (! $membership || ! $organization->is_active) {
            throw new AuthorizationException('You are not allowed to access this organization.');
        }

        $allowed = $membership->roles
            ->flatMap(fn ($role) => $role->permissions)
            ->contains('slug', $permission);

        if (! $allowed) {
            throw new AuthorizationException('You do not have the required permission.');
        }
    }
}
