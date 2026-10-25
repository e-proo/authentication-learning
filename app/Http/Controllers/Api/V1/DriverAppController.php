<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DriverShift;
use App\Models\User;
use App\Services\DriverApp\DriverPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DriverAppController extends Controller
{
    public function me(Request $request, DriverPortalService $drivers): JsonResponse
    {
        $user = $this->user($request);
        $profiles = $drivers->linkedProfiles($user);

        if ($profiles->isEmpty()) {
            throw new HttpException(403, 'No driver profile is linked to this account. Ask an organization administrator to link the correct driver record.');
        }

        $activeShifts = DriverShift::query()
            ->whereIn('driver_id', $profiles->pluck('id'))
            ->where('status', 'active')
            ->with(['vehicle:id,organization_id,vehicle_code,registration_plate,vehicle_type,status'])
            ->get()
            ->keyBy('driver_id');

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'driver_profiles' => $profiles->map(fn ($profile): array => [
                    ...$drivers->presentDriver($profile),
                    'active_shift' => isset($activeShifts[$profile->id])
                        ? $this->presentShift($activeShifts[$profile->id])
                        : null,
                ])->values(),
            ],
        ]);
    }

    public function vehicles(Request $request, DriverPortalService $drivers): JsonResponse
    {
        $user = $this->user($request);
        $validated = $request->validate([
            'driver_profile_id' => ['required', 'integer'],
        ]);

        $records = $drivers->availableVehicles($user, (int) $validated['driver_profile_id']);

        return response()->json([
            'data' => $records->map(fn ($vehicle): array => [
                'id' => $vehicle->id,
                'organization_id' => $vehicle->organization_id,
                'branch_id' => $vehicle->branch_id,
                'vehicle_code' => $vehicle->vehicle_code,
                'registration_plate' => $vehicle->registration_plate,
                'vehicle_type' => $vehicle->vehicle_type,
                'capacity_kg' => $vehicle->capacity_kg,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
            ])->values(),
        ]);
    }

    public function taskSummary(Request $request, DriverPortalService $drivers): JsonResponse
    {
        $validated = $request->validate([
            'driver_profile_id' => ['nullable', 'integer'],
        ]);

        $summary = $drivers->taskSummary(
            $this->user($request),
            isset($validated['driver_profile_id']) ? (int) $validated['driver_profile_id'] : null,
        );

        return response()->json(['data' => $summary]);
    }

    public function tasks(Request $request, DriverPortalService $drivers): JsonResponse
    {
        $user = $this->user($request);
        $validated = $request->validate([
            'driver_profile_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['queued', 'assigned', 'en_route', 'arrived', 'completed', 'failed', 'cancelled'])],
            'task_type' => ['nullable', Rule::in(['collection', 'hub_delivery', 'last_mile', 'return_pickup'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $tasks = $drivers->tasksQuery(
            $user,
            isset($validated['driver_profile_id']) ? (int) $validated['driver_profile_id'] : null,
            $validated['status'] ?? null,
            $validated['task_type'] ?? null,
        )->paginate((int) ($validated['per_page'] ?? 50));

        return response()->json([
            'data' => $tasks->through(fn ($task): array => $drivers->presentTask($task)),
        ]);
    }

    public function showTask(Request $request, string $task, DriverPortalService $drivers): JsonResponse
    {
        $validated = $request->validate(['driver_profile_id' => ['nullable', 'integer']]);
        $record = $drivers->findAssignedTask(
            $this->user($request),
            (int) $task,
            isset($validated['driver_profile_id']) ? (int) $validated['driver_profile_id'] : null,
        );

        return response()->json(['data' => $drivers->presentTask($record)]);
    }

    public function taskEvents(Request $request, string $task, DriverPortalService $drivers): JsonResponse
    {
        $validated = $request->validate([
            'driver_profile_id' => ['nullable', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $record = $drivers->findAssignedTask(
            $this->user($request),
            (int) $task,
            isset($validated['driver_profile_id']) ? (int) $validated['driver_profile_id'] : null,
        );

        $events = $record->events()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 30));

        return response()->json([
            'data' => $events->through(fn ($event): array => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'previous_status' => $event->previous_status,
                'status' => $event->status,
                'location' => $event->location,
                'notes' => $event->notes,
                'metadata' => $event->metadata,
                'occurred_at' => $event->occurred_at,
            ]),
        ]);
    }

    public function updateTask(
        Request $request,
        string $task,
        DriverPortalService $drivers,
    ): JsonResponse {
        $user = $this->user($request);
        $profileInput = $request->validate(['driver_profile_id' => ['nullable', 'integer']]);
        $profileId = isset($profileInput['driver_profile_id']) ? (int) $profileInput['driver_profile_id'] : null;

        // Scope before validating details so an unassigned driver cannot use this
        // endpoint to discover the existence or shape of another driver's task.
        $record = $drivers->findAssignedTask($user, (int) $task, $profileId);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['en_route', 'arrived', 'completed', 'failed'])],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'failure_reason' => ['nullable', 'string', 'max:500'],
            'proof_reference' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:180'],
            'recipient_phone' => ['nullable', 'string', 'max:40'],
            'metadata' => ['nullable', 'array:device_id,client_event_id,sync_event_id,source'],
            'metadata.device_id' => ['nullable', 'string', 'max:180'],
            'metadata.client_event_id' => ['nullable', 'uuid'],
            'metadata.sync_event_id' => ['nullable', 'uuid'],
            'metadata.source' => ['nullable', Rule::in(['driver_app', 'offline_sync'])],
        ]);

        if ($validated['status'] === 'completed') {
            if (trim((string) ($validated['proof_reference'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'proof_reference' => ['A proof reference is required to complete a custody handoff.'],
                ]);
            }

            if ($record->task_type === 'last_mile' && trim((string) ($validated['recipient_name'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'recipient_name' => ['The recipient name is required for last-mile delivery.'],
                ]);
            }
        }

        $updated = $drivers->transitionTask(
            $user,
            (int) $task,
            $validated['status'],
            $validated,
            $profileId,
        );

        return response()->json(['data' => $drivers->presentTask($updated)]);
    }

    public function currentShift(Request $request, DriverPortalService $drivers): JsonResponse
    {
        $validated = $request->validate(['driver_profile_id' => ['nullable', 'integer']]);
        $records = $drivers->activeShift(
            $this->user($request),
            isset($validated['driver_profile_id']) ? (int) $validated['driver_profile_id'] : null,
        );

        return response()->json([
            'data' => $records->map(fn (DriverShift $shift): array => $this->presentShift($shift))->values(),
        ]);
    }

    public function startShift(Request $request, DriverPortalService $drivers): JsonResponse
    {
        $validated = $request->validate([
            'driver_profile_id' => ['required', 'integer'],
            'vehicle_id' => ['nullable', 'integer'],
            'odometer_start' => ['nullable', 'integer', 'min:0', 'max:9000000000000000'],
            'pre_trip_checklist' => ['nullable', 'array:tires,brakes,lights,mirrors,safety_equipment,cargo_area'],
            'pre_trip_checklist.tires' => ['sometimes', 'boolean'],
            'pre_trip_checklist.brakes' => ['sometimes', 'boolean'],
            'pre_trip_checklist.lights' => ['sometimes', 'boolean'],
            'pre_trip_checklist.mirrors' => ['sometimes', 'boolean'],
            'pre_trip_checklist.safety_equipment' => ['sometimes', 'boolean'],
            'pre_trip_checklist.cargo_area' => ['sometimes', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $shift = $drivers->startShift($this->user($request), (int) $validated['driver_profile_id'], $validated);

        return response()->json(['data' => $this->presentShift($shift)], 201);
    }

    public function endShift(Request $request, string $shift, DriverPortalService $drivers): JsonResponse
    {
        $validated = $request->validate([
            'odometer_end' => ['nullable', 'integer', 'min:0', 'max:9000000000000000'],
            'post_trip_checklist' => ['nullable', 'array:tires,brakes,lights,mirrors,safety_equipment,cargo_area'],
            'post_trip_checklist.tires' => ['sometimes', 'boolean'],
            'post_trip_checklist.brakes' => ['sometimes', 'boolean'],
            'post_trip_checklist.lights' => ['sometimes', 'boolean'],
            'post_trip_checklist.mirrors' => ['sometimes', 'boolean'],
            'post_trip_checklist.safety_equipment' => ['sometimes', 'boolean'],
            'post_trip_checklist.cargo_area' => ['sometimes', 'boolean'],
            'critical_defect' => ['sometimes', 'boolean'],
            'defect_notes' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = $drivers->endShift($this->user($request), (int) $shift, $validated);

        return response()->json(['data' => $this->presentShift($record)]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_active) {
            throw new HttpException(403, 'An active authenticated user is required.');
        }

        return $user;
    }

    private function presentShift(DriverShift $shift): array
    {
        return [
            'id' => $shift->id,
            'shift_code' => $shift->shift_code,
            'status' => $shift->status,
            'started_at' => $shift->started_at,
            'ended_at' => $shift->ended_at,
            'odometer_start' => $shift->odometer_start,
            'odometer_end' => $shift->odometer_end,
            'pre_trip_checklist' => $shift->pre_trip_checklist,
            'post_trip_checklist' => $shift->post_trip_checklist,
            'driver' => $shift->driver ? [
                'id' => $shift->driver->id,
                'driver_code' => $shift->driver->driver_code,
                'display_name' => $shift->driver->display_name,
            ] : null,
            'vehicle' => $shift->vehicle ? [
                'id' => $shift->vehicle->id,
                'vehicle_code' => $shift->vehicle->vehicle_code,
                'registration_plate' => $shift->vehicle->registration_plate,
                'vehicle_type' => $shift->vehicle->vehicle_type,
                'status' => $shift->vehicle->status,
            ] : null,
            'notes' => $shift->notes,
        ];
    }
}
