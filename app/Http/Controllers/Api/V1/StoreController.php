<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Store;
use App\Services\Authorization\OrganizationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
    public function index(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'catalog.products.view');

        return response()->json([
            'data' => Store::query()->where('organization_id', $organization->id)->orderBy('name')->paginate(25),
        ]);
    }

    public function store(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'catalog.products.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:stores,slug'],
            'mode' => ['sometimes', Rule::in(['catalog', 'marketplace', 'hybrid'])],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'settings' => ['nullable', 'array'],
        ]);

        $store = Store::query()->create([
            ...$validated,
            'organization_id' => $organization->id,
            'mode' => $validated['mode'] ?? 'marketplace',
            'currency' => $validated['currency'] ?? 'YER',
            'is_active' => true,
        ]);

        return response()->json(['data' => $store], 201);
    }
}
