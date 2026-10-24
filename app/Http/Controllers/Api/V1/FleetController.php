<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\Organization;
use App\Services\Authorization\OrganizationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FleetController extends Controller
{
    public function drivers(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'transport.manage');

        return response()->json([
            'data' => FleetDriver::query()
                ->where('organization_id', $organization->id)
                ->with('branch:id,organization_id,name,code')
                ->orderBy('display_name')
                ->paginate(25),
        ]);
    }

    public function storeDriver(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'transport.manage');

        $validated = $request->validate([
            'driver_code' => [
                'required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('fleet_drivers', 'driver_code')->where('organization_id', $organization->id),
            ],
            'display_name' => ['required', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'user_id' => [
                'nullable', 'integer', 'exists:users,id',
                Rule::unique('fleet_drivers', 'user_id')->where('organization_id', $organization->id),
            ],
            'branch_id' => [
                'nullable', 'integer',
                Rule::exists('branches', 'id')->where('organization_id', $organization->id),
            ],
            'driver_type' => ['sometimes', Rule::in(['employee', 'contractor', 'partner'])],
            'license_number' => ['nullable', 'string', 'max:120'],
            'license_expires_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array:external_reference,notes'],
            'metadata.external_reference' => ['nullable', 'string', 'max:180'],
            'metadata.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $driver = FleetDriver::query()->create([
            ...$validated,
            'organization_id' => $organization->id,
            'driver_type' => $validated['driver_type'] ?? 'employee',
            'status' => 'active',
        ]);

        return response()->json(['data' => $driver->load('branch')], 201);
    }

    public function vehicles(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'transport.manage');

        return response()->json([
            'data' => FleetVehicle::query()
                ->where('organization_id', $organization->id)
                ->with('branch:id,organization_id,name,code')
                ->orderBy('vehicle_code')
                ->paginate(25),
        ]);
    }

    public function storeVehicle(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'transport.manage');

        $validated = $request->validate([
            'vehicle_code' => [
                'required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('fleet_vehicles', 'vehicle_code')->where('organization_id', $organization->id),
            ],
            'registration_plate' => [
                'nullable', 'string', 'max:80',
                Rule::unique('fleet_vehicles', 'registration_plate')->where('organization_id', $organization->id),
            ],
            'branch_id' => [
                'nullable', 'integer',
                Rule::exists('branches', 'id')->where('organization_id', $organization->id),
            ],
            'vehicle_type' => ['sometimes', Rule::in(['motorcycle', 'car', 'van', 'truck', 'heavy_truck', 'other'])],
            'capacity_kg' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'make' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array:external_reference,notes'],
            'metadata.external_reference' => ['nullable', 'string', 'max:180'],
            'metadata.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $vehicle = FleetVehicle::query()->create([
            ...$validated,
            'organization_id' => $organization->id,
            'vehicle_type' => $validated['vehicle_type'] ?? 'van',
            'status' => 'active',
        ]);

        return response()->json(['data' => $vehicle->load('branch')], 201);
    }
}
