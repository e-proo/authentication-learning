<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Warehouse;
use App\Services\Authorization\OrganizationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'inventory.view');

        return response()->json([
            'data' => Warehouse::query()->where('organization_id', $organization->id)
                ->withCount('inventoryBalances')->orderBy('name')->paginate(25),
        ]);
    }

    public function store(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'inventory.adjust');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('warehouses', 'code')->where('organization_id', $organization->id)],
            'branch_id' => ['nullable', 'integer',
                Rule::exists('branches', 'id')->where('organization_id', $organization->id)],
            'kind' => ['sometimes', Rule::in(['warehouse', 'hub', 'store_room', 'cross_dock'])],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $warehouse = Warehouse::query()->create([
            ...$validated,
            'organization_id' => $organization->id,
            'kind' => $validated['kind'] ?? 'warehouse',
            'is_active' => true,
        ]);

        return response()->json(['data' => $warehouse], 201);
    }
}
