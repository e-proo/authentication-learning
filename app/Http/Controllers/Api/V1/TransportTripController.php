<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\TransportTrip;
use App\Services\Authorization\OrganizationAccess;
use App\Services\Logistics\TransportTripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransportTripController extends Controller
{
    public function index(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'transport.manage');

        return response()->json([
            'data' => TransportTrip::query()
                ->where('organization_id', $organization->id)
                ->with(['driver', 'vehicle', 'branch'])
                ->withCount('tasks')
                ->latest()
                ->paginate(25),
        ]);
    }

    public function store(
        Request $request,
        Organization $organization,
        OrganizationAccess $access,
        TransportTripService $trips,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'transport.manage');

        $validated = $request->validate([
            'branch_id' => [
                'nullable', 'integer',
                Rule::exists('branches', 'id')->where('organization_id', $organization->id),
            ],
            'trip_type' => ['required', Rule::in(['collection', 'hub_transfer', 'linehaul', 'last_mile', 'return_pickup'])],
            'origin' => ['required', 'array:name,city,country_code,address_line1,label,latitude,longitude'],
            'origin.name' => ['required', 'string', 'max:180'],
            'origin.city' => ['required', 'string', 'max:120'],
            'origin.country_code' => ['required', 'string', 'size:2'],
            'origin.address_line1' => ['required', 'string', 'max:500'],
            'origin.label' => ['nullable', 'string', 'max:180'],
            'origin.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'origin.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'destination' => ['required', 'array:name,city,country_code,address_line1,label,latitude,longitude'],
            'destination.name' => ['required', 'string', 'max:180'],
            'destination.city' => ['required', 'string', 'max:120'],
            'destination.country_code' => ['required', 'string', 'size:2'],
            'destination.address_line1' => ['required', 'string', 'max:500'],
            'destination.label' => ['nullable', 'string', 'max:180'],
            'destination.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'destination.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'planned_departure_at' => ['nullable', 'date'],
            'planned_arrival_at' => ['nullable', 'date', 'after_or_equal:planned_departure_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array:route_reference,planned_stops_count'],
            'metadata.route_reference' => ['nullable', 'string', 'max:180'],
            'metadata.planned_stops_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $trip = $trips->create($organization, $request->user(), $validated);

        return response()->json(['data' => $trip], 201);
    }

    public function show(
        Request $request,
        Organization $organization,
        string $trip,
        OrganizationAccess $access,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'transport.manage');

        $record = TransportTrip::query()
            ->where('organization_id', $organization->id)
            ->whereKey($trip)
            ->with(['driver', 'vehicle', 'branch', 'tasks.shipment', 'tasks.driver', 'events.actor'])
            ->firstOrFail();

        return response()->json(['data' => $record]);
    }

    public function assign(
        Request $request,
        Organization $organization,
        string $trip,
        OrganizationAccess $access,
        TransportTripService $trips,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'transport.manage');

        $validated = $request->validate([
            'driver_id' => [
                'required', 'integer',
                Rule::exists('fleet_drivers', 'id')->where('organization_id', $organization->id),
            ],
            'vehicle_id' => [
                'nullable', 'integer',
                Rule::exists('fleet_vehicles', 'id')->where('organization_id', $organization->id),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = TransportTrip::query()
            ->where('organization_id', $organization->id)
            ->whereKey($trip)
            ->firstOrFail();

        return response()->json([
            'data' => $trips->assign(
                $organization,
                $record,
                $request->user(),
                (int) $validated['driver_id'],
                isset($validated['vehicle_id']) ? (int) $validated['vehicle_id'] : null,
                $validated['notes'] ?? null,
            ),
        ]);
    }

    public function depart(Request $request, Organization $organization, string $trip, OrganizationAccess $access, TransportTripService $trips): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'transport.manage');
        $record = TransportTrip::query()->where('organization_id', $organization->id)->whereKey($trip)->firstOrFail();
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        return response()->json(['data' => $trips->depart($organization, $record, $request->user(), $validated['notes'] ?? null)]);
    }

    public function complete(Request $request, Organization $organization, string $trip, OrganizationAccess $access, TransportTripService $trips): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'transport.manage');
        $record = TransportTrip::query()->where('organization_id', $organization->id)->whereKey($trip)->firstOrFail();
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        return response()->json(['data' => $trips->complete($organization, $record, $request->user(), $validated['notes'] ?? null)]);
    }

    public function cancel(Request $request, Organization $organization, string $trip, OrganizationAccess $access, TransportTripService $trips): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'transport.manage');
        $record = TransportTrip::query()->where('organization_id', $organization->id)->whereKey($trip)->firstOrFail();
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        return response()->json(['data' => $trips->cancel($organization, $record, $request->user(), $validated['notes'] ?? null)]);
    }
}
