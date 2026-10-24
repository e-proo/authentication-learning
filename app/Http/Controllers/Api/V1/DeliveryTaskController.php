<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryTask;
use App\Models\Organization;
use App\Services\Authorization\OrganizationAccess;
use App\Services\Logistics\DeliveryTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryTaskController extends Controller
{
    public function index(Request $request, Organization $organization, OrganizationAccess $access): JsonResponse
    {
        $access->requirePermission($request->user(), $organization, 'delivery_tasks.view');

        return response()->json([
            'data' => DeliveryTask::query()
                ->where('organization_id', $organization->id)
                ->with(['shipment', 'returnCase', 'driver', 'trip'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function store(
        Request $request,
        Organization $organization,
        OrganizationAccess $access,
        DeliveryTaskService $tasks,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'delivery_tasks.manage');

        $validated = $request->validate([
            'task_type' => ['required', Rule::in(['collection', 'hub_delivery', 'last_mile', 'return_pickup'])],
            'shipment_id' => [
                'nullable', 'integer', 'required_if:task_type,last_mile,hub_delivery',
                'prohibited_if:task_type,return_pickup',
                Rule::exists('shipments', 'id')->where('organization_id', $organization->id),
            ],
            'return_case_id' => [
                'nullable', 'integer', 'required_if:task_type,return_pickup',
                'prohibited_unless:task_type,return_pickup',
                Rule::exists('return_cases', 'id')->where('organization_id', $organization->id),
            ],
            'sequence_number' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'location' => ['required', 'array:name,city,country_code,address_line1,address_line2,postal_code,label,latitude,longitude'],
            'location.name' => ['required', 'string', 'max:180'],
            'location.city' => ['required', 'string', 'max:120'],
            'location.country_code' => ['required', 'string', 'size:2'],
            'location.address_line1' => ['required', 'string', 'max:500'],
            'location.address_line2' => ['nullable', 'string', 'max:500'],
            'location.postal_code' => ['nullable', 'string', 'max:30'],
            'location.label' => ['nullable', 'string', 'max:180'],
            'location.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'location.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'recipient_name' => ['nullable', 'string', 'max:180'],
            'recipient_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array:client_reference,route_note'],
            'metadata.client_reference' => ['nullable', 'string', 'max:180'],
            'metadata.route_note' => ['nullable', 'string', 'max:500'],
        ]);

        $task = $tasks->create($organization, $request->user(), $validated);

        return response()->json(['data' => $task], 201);
    }

    public function show(
        Request $request,
        Organization $organization,
        string $task,
        OrganizationAccess $access,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'delivery_tasks.view');

        $record = DeliveryTask::query()
            ->where('organization_id', $organization->id)
            ->whereKey($task)
            ->with(['shipment', 'returnCase', 'trip.driver', 'trip.vehicle', 'driver', 'events.actor'])
            ->firstOrFail();

        return response()->json(['data' => $record]);
    }

    public function assign(
        Request $request,
        Organization $organization,
        string $task,
        OrganizationAccess $access,
        DeliveryTaskService $tasks,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'delivery_tasks.manage');

        $validated = $request->validate([
            'driver_id' => [
                'required', 'integer',
                Rule::exists('fleet_drivers', 'id')->where('organization_id', $organization->id),
            ],
            'transport_trip_id' => [
                'nullable', 'integer',
                Rule::exists('transport_trips', 'id')->where('organization_id', $organization->id),
            ],
            'sequence_number' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = DeliveryTask::query()->where('organization_id', $organization->id)->whereKey($task)->firstOrFail();

        return response()->json([
            'data' => $tasks->assign(
                $organization,
                $record,
                $request->user(),
                (int) $validated['driver_id'],
                isset($validated['transport_trip_id']) ? (int) $validated['transport_trip_id'] : null,
                isset($validated['sequence_number']) ? (int) $validated['sequence_number'] : null,
                $validated['notes'] ?? null,
            ),
        ]);
    }

    public function updateStatus(
        Request $request,
        Organization $organization,
        string $task,
        OrganizationAccess $access,
        DeliveryTaskService $tasks,
    ): JsonResponse {
        $access->requirePermission($request->user(), $organization, 'delivery_tasks.manage');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['en_route', 'arrived', 'completed', 'failed', 'cancelled'])],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'failure_reason' => ['nullable', 'string', 'max:500'],
            'proof_reference' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:180'],
            'recipient_phone' => ['nullable', 'string', 'max:40'],
            'metadata' => ['nullable', 'array:event_code,attempt,provider,provider_event_id'],
            'metadata.event_code' => ['nullable', 'string', 'max:80'],
            'metadata.attempt' => ['nullable', 'integer', 'min:1', 'max:100'],
            'metadata.provider' => ['nullable', 'string', 'max:80'],
            'metadata.provider_event_id' => ['nullable', 'string', 'max:180'],
        ]);

        $record = DeliveryTask::query()->where('organization_id', $organization->id)->whereKey($task)->firstOrFail();

        return response()->json([
            'data' => $tasks->transition(
                $organization,
                $record,
                $request->user(),
                $validated['status'],
                $validated,
            ),
        ]);
    }
}
