<?php

namespace App\Services\DriverApp;

use App\Models\DeliveryTask;
use App\Models\DriverShift;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\Organization;
use App\Models\TransportTrip;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Logistics\DeliveryTaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DriverPortalService
{
    private const CHECKLIST_KEYS = [
        'tires',
        'brakes',
        'lights',
        'mirrors',
        'safety_equipment',
        'cargo_area',
    ];

    public function __construct(
        private readonly DeliveryTaskService $tasks,
        private readonly AuditLogger $audit,
    ) {
    }

    public function linkedProfiles(User $user): Collection
    {
        return FleetDriver::query()
            ->where('user_id', $user->id)
            ->with(['organization:id,name,slug', 'branch:id,organization_id,name,code'])
            ->orderBy('organization_id')
            ->orderBy('id')
            ->get();
    }

    public function requireActiveProfile(User $user, int $profileId): FleetDriver
    {
        $profile = FleetDriver::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereKey($profileId)
            ->with(['organization:id,name,slug', 'branch:id,organization_id,name,code'])
            ->first();

        if (! $profile) {
            throw new HttpException(403, 'The selected driver profile is not active or is not linked to this account.');
        }

        return $profile;
    }

    public function activeProfiles(User $user, ?int $profileId = null): Collection
    {
        $query = FleetDriver::query()
            ->where('user_id', $user->id)
            ->where('status', 'active');

        if ($profileId !== null) {
            $query->whereKey($profileId);
        }

        $profiles = $query->get();

        if ($profiles->isEmpty()) {
            throw new HttpException(403, 'No active driver profile is linked to this account.');
        }

        return $profiles;
    }

    public function tasksQuery(User $user, ?int $profileId = null, ?string $status = null): Builder
    {
        $driverIds = $this->activeProfiles($user, $profileId)->pluck('id');

        $query = DeliveryTask::query()
            ->whereIn('driver_id', $driverIds)
            ->with([
                'shipment:id,organization_id,shipment_number,tracking_number,status',
                'returnCase:id,organization_id,status',
                'trip:id,organization_id,trip_number,status,origin_snapshot,destination_snapshot',
            ])
            ->orderByRaw("CASE WHEN status IN ('assigned', 'en_route', 'arrived') THEN 0 ELSE 1 END")
            ->orderBy('sequence_number')
            ->orderBy('id');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query;
    }

    public function findAssignedTask(User $user, int $taskId, ?int $profileId = null, bool $lock = false): DeliveryTask
    {
        $driverIds = $this->activeProfiles($user, $profileId)->pluck('id');

        $query = DeliveryTask::query()
            ->whereIn('driver_id', $driverIds)
            ->whereKey($taskId)
            ->with([
                'shipment:id,organization_id,shipment_number,tracking_number,status',
                'returnCase:id,organization_id,status',
                'trip:id,organization_id,trip_number,status,origin_snapshot,destination_snapshot',
            ]);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    public function transitionTask(
        User $user,
        int $taskId,
        string $nextStatus,
        array $data,
        ?int $profileId = null,
    ): DeliveryTask {
        return DB::transaction(function () use ($user, $taskId, $nextStatus, $data, $profileId): DeliveryTask {
            $task = $this->findAssignedTask($user, $taskId, $profileId, true);

            // Re-check the assignment while the task row is locked. A dispatcher
            // may have reassigned this task after the mobile client downloaded it.
            $driver = FleetDriver::query()
                ->whereKey($task->driver_id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (! $driver) {
                throw new HttpException(403, 'This task is no longer assigned to an active driver profile linked to your account.');
            }

            if ($nextStatus === 'completed' && ! is_string($data['proof_reference'] ?? null)) {
                throw ValidationException::withMessages([
                    'proof_reference' => ['A proof reference is required to complete a custody task.'],
                ]);
            }

            return $this->tasks->transition(
                Organization::query()->whereKey($task->organization_id)->firstOrFail(),
                $task,
                $user,
                $nextStatus,
                $data,
            );
        }, attempts: 3);
    }

    public function availableVehicles(User $user, int $profileId): Collection
    {
        $driver = $this->requireActiveProfile($user, $profileId);

        $vehicleIds = TransportTrip::query()
            ->where('organization_id', $driver->organization_id)
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['assigned', 'departed'])
            ->whereNotNull('vehicle_id')
            ->pluck('vehicle_id')
            ->unique()
            ->values();

        if ($vehicleIds->isEmpty()) {
            return collect();
        }

        $vehiclesInActiveShift = DriverShift::query()
            ->where('organization_id', $driver->organization_id)
            ->where('status', 'active')
            ->whereNotNull('vehicle_id')
            ->pluck('vehicle_id');

        return FleetVehicle::query()
            ->where('organization_id', $driver->organization_id)
            ->whereIn('id', $vehicleIds)
            ->where('status', 'active')
            ->whereNotIn('id', $vehiclesInActiveShift)
            ->orderBy('vehicle_code')
            ->get(['id', 'organization_id', 'branch_id', 'vehicle_code', 'registration_plate', 'vehicle_type', 'capacity_kg', 'make', 'model']);
    }

    public function activeShift(User $user, ?int $profileId = null): Collection
    {
        $profiles = $this->activeProfiles($user, $profileId);

        return DriverShift::query()
            ->whereIn('driver_id', $profiles->pluck('id'))
            ->where('status', 'active')
            ->with(['driver:id,organization_id,driver_code,display_name', 'vehicle:id,organization_id,vehicle_code,registration_plate,vehicle_type'])
            ->orderByDesc('started_at')
            ->get();
    }

    public function startShift(User $user, int $profileId, array $data): DriverShift
    {
        return DB::transaction(function () use ($user, $profileId, $data): DriverShift {
            $driver = FleetDriver::query()
                ->where('user_id', $user->id)
                ->whereKey($profileId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $driver) {
                throw new HttpException(403, 'The selected driver profile is not active or is not linked to this account.');
            }

            if ($driver->license_expires_at && $driver->license_expires_at->toDateString() < today()->toDateString()) {
                throw new ConflictHttpException('The driver license has expired. Contact the fleet supervisor before starting a shift.');
            }

            if (DriverShift::query()->where('organization_id', $driver->organization_id)->where('driver_id', $driver->id)->where('status', 'active')->exists()) {
                throw new ConflictHttpException('An active shift already exists for this driver.');
            }

            $vehicle = null;
            if (! empty($data['vehicle_id'])) {
                $vehicle = FleetVehicle::query()
                    ->where('organization_id', $driver->organization_id)
                    ->whereKey($data['vehicle_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($vehicle->status !== 'active') {
                    throw new ConflictHttpException('The selected vehicle is not active.');
                }

                $hasAssignedTrip = TransportTrip::query()
                    ->where('organization_id', $driver->organization_id)
                    ->where('driver_id', $driver->id)
                    ->where('vehicle_id', $vehicle->id)
                    ->whereIn('status', ['assigned', 'departed'])
                    ->exists();

                if (! $hasAssignedTrip) {
                    throw new HttpException(403, 'A vehicle can only be used when it is attached to a trip assigned to this driver.');
                }

                $this->validateChecklist($data['pre_trip_checklist'] ?? null, true);

                if (! array_key_exists('odometer_start', $data)) {
                    throw ValidationException::withMessages(['odometer_start' => ['The starting odometer is required when using a vehicle.']]);
                }

                $vehicleAlreadyUsed = DriverShift::query()
                    ->where('organization_id', $driver->organization_id)
                    ->where('vehicle_id', $vehicle->id)
                    ->where('status', 'active')
                    ->exists();

                if ($vehicleAlreadyUsed) {
                    throw new ConflictHttpException('The selected vehicle is already in use by another active shift.');
                }
            }

            $shift = DriverShift::query()->create([
                'organization_id' => $driver->organization_id,
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle?->id,
                'shift_code' => $this->newShiftCode(),
                'status' => 'active',
                'started_at' => now(),
                'odometer_start' => $vehicle ? ($data['odometer_start'] ?? null) : null,
                'pre_trip_checklist' => $vehicle ? $data['pre_trip_checklist'] : ($data['pre_trip_checklist'] ?? null),
                'start_location' => $data['location'] ?? null,
                'start_device_id' => $data['device_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => ['source' => 'driver_app'],
            ]);

            $this->audit->record($user, Organization::query()->whereKey($driver->organization_id)->firstOrFail(), 'driver_shift.started', $shift, [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle?->id,
                'shift_code' => $shift->shift_code,
            ]);

            return $shift->load(['driver:id,organization_id,driver_code,display_name', 'vehicle:id,organization_id,vehicle_code,registration_plate,vehicle_type']);
        }, attempts: 3);
    }

    public function endShift(User $user, int $shiftId, array $data): DriverShift
    {
        return DB::transaction(function () use ($user, $shiftId, $data): DriverShift {
            $shift = DriverShift::query()
                ->whereKey($shiftId)
                ->whereHas('driver', fn (Builder $query) => $query->where('user_id', $user->id)->where('status', 'active'))
                ->lockForUpdate()
                ->firstOrFail();

            if ($shift->status !== 'active') {
                throw new ConflictHttpException('This shift is already closed or requires review.');
            }

            $unresolvedTasks = DeliveryTask::query()
                ->where('organization_id', $shift->organization_id)
                ->where('driver_id', $shift->driver_id)
                ->whereIn('status', ['assigned', 'en_route', 'arrived'])
                ->exists();

            if ($unresolvedTasks) {
                throw new ConflictHttpException('Resolve or explicitly reassign every active task before ending the shift.');
            }

            $vehicle = null;
            if ($shift->vehicle_id !== null) {
                if (! array_key_exists('odometer_end', $data)) {
                    throw ValidationException::withMessages(['odometer_end' => ['The ending odometer is required for a vehicle shift.']]);
                }

                if ((int) $data['odometer_end'] < (int) $shift->odometer_start) {
                    throw ValidationException::withMessages(['odometer_end' => ['The ending odometer cannot be less than the starting odometer.']]);
                }

                $this->validateChecklist($data['post_trip_checklist'] ?? null, false);
                $vehicle = FleetVehicle::query()
                    ->where('organization_id', $shift->organization_id)
                    ->whereKey($shift->vehicle_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $checklistHasDefect = $shift->vehicle_id !== null
                && collect($data['post_trip_checklist'] ?? [])->contains(fn ($passed): bool => $passed === false);
            $criticalDefect = (bool) ($data['critical_defect'] ?? false);

            if ($criticalDefect && trim((string) ($data['defect_notes'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'defect_notes' => ['Describe the critical defect before ending the shift.'],
                ]);
            }

            $needsReview = $checklistHasDefect || $criticalDefect;

            $shift->forceFill([
                'status' => $needsReview ? 'needs_review' : 'ended',
                'ended_at' => now(),
                'odometer_end' => $shift->vehicle_id !== null ? $data['odometer_end'] : null,
                'post_trip_checklist' => $data['post_trip_checklist'] ?? null,
                'end_location' => $data['location'] ?? null,
                'end_device_id' => $data['device_id'] ?? null,
                'notes' => $data['notes'] ?? $data['defect_notes'] ?? $shift->notes,
                'metadata' => array_merge($shift->metadata ?? [], [
                    'critical_defect' => $criticalDefect,
                    'defect_notes' => $data['defect_notes'] ?? null,
                ]),
            ])->save();

            $organization = Organization::query()->whereKey($shift->organization_id)->firstOrFail();

            if ($criticalDefect && $vehicle) {
                $previousVehicleStatus = $vehicle->status;
                $vehicle->forceFill(['status' => 'maintenance'])->save();
                $this->audit->record($user, $organization, 'fleet.vehicle.critical_defect_reported', $vehicle, [
                    'driver_id' => $shift->driver_id,
                    'shift_id' => $shift->id,
                    'previous_status' => $previousVehicleStatus,
                    'status' => 'maintenance',
                ]);
            }

            $this->audit->record($user, $organization, $needsReview ? 'driver_shift.ended_needs_review' : 'driver_shift.ended', $shift, [
                'driver_id' => $shift->driver_id,
                'vehicle_id' => $shift->vehicle_id,
                'shift_code' => $shift->shift_code,
                'status' => $shift->status,
                'odometer_start' => $shift->odometer_start,
                'odometer_end' => $shift->odometer_end,
            ]);

            return $shift->refresh()->load(['driver:id,organization_id,driver_code,display_name', 'vehicle:id,organization_id,vehicle_code,registration_plate,vehicle_type,status']);
        }, attempts: 3);
    }

    public function presentTask(DeliveryTask $task): array
    {
        return [
            'id' => $task->id,
            'task_number' => $task->task_number,
            'task_type' => $task->task_type,
            'status' => $task->status,
            'sequence_number' => $task->sequence_number,
            'location' => $task->location_snapshot,
            'recipient' => [
                'name' => $task->recipient_name,
                'phone' => $task->recipient_phone,
            ],
            'proof_reference' => $task->proof_reference,
            'failure_reason' => $task->failure_reason,
            'attempt_number' => $task->attempt_number,
            'assigned_at' => $task->assigned_at,
            'started_at' => $task->started_at,
            'arrived_at' => $task->arrived_at,
            'completed_at' => $task->completed_at,
            'failed_at' => $task->failed_at,
            'notes' => $task->notes,
            'shipment' => $task->shipment ? [
                'id' => $task->shipment->id,
                'shipment_number' => $task->shipment->shipment_number,
                'tracking_number' => $task->shipment->tracking_number,
                'status' => $task->shipment->status,
            ] : null,
            'return_case' => $task->returnCase ? [
                'id' => $task->returnCase->id,
                'status' => $task->returnCase->status,
            ] : null,
            'trip' => $task->trip ? [
                'id' => $task->trip->id,
                'trip_number' => $task->trip->trip_number,
                'status' => $task->trip->status,
                'origin' => $task->trip->origin_snapshot,
                'destination' => $task->trip->destination_snapshot,
            ] : null,
        ];
    }

    public function presentDriver(FleetDriver $driver): array
    {
        return [
            'id' => $driver->id,
            'organization_id' => $driver->organization_id,
            'organization' => $driver->organization ? [
                'id' => $driver->organization->id,
                'name' => $driver->organization->name,
                'slug' => $driver->organization->slug,
            ] : null,
            'branch' => $driver->branch ? [
                'id' => $driver->branch->id,
                'name' => $driver->branch->name,
                'code' => $driver->branch->code,
            ] : null,
            'driver_code' => $driver->driver_code,
            'display_name' => $driver->display_name,
            'phone' => $driver->phone,
            'driver_type' => $driver->driver_type,
            'status' => $driver->status,
            'license_expires_at' => $driver->license_expires_at?->toDateString(),
            'license_expired' => $driver->license_expires_at
                ? $driver->license_expires_at->toDateString() < today()->toDateString()
                : false,
        ];
    }

    private function validateChecklist(?array $checklist, bool $requireAllPassed): void
    {
        if ($checklist === null) {
            throw ValidationException::withMessages(['checklist' => ['A vehicle inspection checklist is required.']]);
        }

        foreach (self::CHECKLIST_KEYS as $key) {
            if (! array_key_exists($key, $checklist) || ! is_bool($checklist[$key])) {
                throw ValidationException::withMessages([
                    'checklist' => ['All required vehicle inspection items must be recorded.'],
                ]);
            }

            if ($requireAllPassed && $checklist[$key] !== true) {
                throw ValidationException::withMessages([
                    'checklist' => ['All pre-trip safety items must pass before starting a vehicle shift. Report defects to the fleet supervisor.'],
                ]);
            }
        }
    }

    private function newShiftCode(): string
    {
        do {
            $code = 'SHIFT-' . Str::upper(Str::random(10));
        } while (DriverShift::query()->where('shift_code', $code)->exists());

        return $code;
    }
}
