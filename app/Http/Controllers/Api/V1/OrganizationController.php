<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizations = $request->user()
            ->memberships()
            ->where('memberships.status', 'active')
            ->whereHas('organization', fn ($query) => $query->where('is_active', true))
            ->with(['organization:id,name,slug,kind,commerce_mode', 'branch:id,organization_id,name,code'])
            ->get()
            ->map(fn ($membership) => [
                'membership_id' => $membership->id,
                'organization' => $membership->organization,
                'branch' => $membership->branch,
                'is_default' => $membership->is_default,
                'roles' => $membership->roles()->pluck('slug'),
            ])
            ->values();

        return response()->json(['data' => $organizations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => [
                'required', 'string', 'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:organizations,slug',
            ],
            'kind' => ['sometimes', Rule::in(['business', 'merchant', 'carrier', 'logistics_provider', 'manufacturer', 'supplier'])],
            'commerce_mode' => ['sometimes', Rule::in(['catalog', 'marketplace', 'hybrid'])],
        ]);

        $organization = DB::transaction(function () use ($request, $validated): Organization {
            $organization = Organization::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'kind' => $validated['kind'] ?? 'business',
                'commerce_mode' => $validated['commerce_mode'] ?? 'marketplace',
                'is_active' => true,
            ]);

            $membership = Membership::create([
                'user_id' => $request->user()->id,
                'organization_id' => $organization->id,
                'branch_id' => null,
                'status' => 'active',
                'is_default' => ! $request->user()->memberships()->where('is_default', true)->exists(),
            ]);

            $permissionDefinitions = [
                'organizations.view' => 'View organization',
                'organizations.manage' => 'Manage organization settings',
                'catalog.products.view' => 'View products',
                'catalog.products.create' => 'Create products',
                'catalog.products.manage' => 'Manage products',
                'inventory.view' => 'View inventory',
                'inventory.adjust' => 'Adjust inventory',
                'orders.view' => 'View orders',
                'orders.manage' => 'Manage orders',
                'shipments.manage' => 'Manage shipments',
                'finance.view' => 'View financial records',
                'marketing.manage' => 'Manage marketing campaigns',
                'ai.use' => 'Use approved AI assistant tools',
            ];

            $permissions = collect($permissionDefinitions)->map(
                fn (string $description, string $slug) => Permission::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => str($slug)->replace('.', ' ')->headline()->toString(), 'description' => $description],
                )
            );

            $ownerRole = Role::query()->firstOrCreate(
                ['slug' => 'organization-owner'],
                ['name' => 'Organization Owner', 'description' => 'Full access within an organization', 'is_system' => true],
            );

            $ownerRole->permissions()->syncWithoutDetaching($permissions->modelKeys());
            $membership->roles()->syncWithoutDetaching([$ownerRole->id]);

            return $organization;
        });

        return response()->json(['data' => $organization], 201);
    }
}
