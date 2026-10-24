<?php

namespace App\Services\Logistics;

use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\Organization;
use App\Models\TransportTrip;
use App\Models\TransportTripEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Illuminate\Validation\ValidationException;

class TransportTripService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function create(Organization $organization, User $actor, array $data): TransportTrip
    {
        return DB::transaction(function () use ($organization, $actor, $data): TransportTrip {
            $trip = TransportTrip::query()->create([
                'organization_id' => $organization->id,
                'branch_id' => $data['branch_id'] ?? null,
                'trip_number' => 'TRP-'.Str::ulid(),
                'trip_type' => $data['trip_type'],
                'status' => 'planned',
                'origin_snapshot' => $data['origin'],
                'destination_snapshot' => $data['destination'],
                'planned_departure_at' => $data['planned_departure_at'] ?? null,
                'planned_arrival_at' => $data['planned_arrival_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->event($organization, $trip, $actor, 'trip_created', null, 'planned', $data['notes'] ?? null);
            $this->audit->record($actor, $organization, 'transport_trip.created', $trip, [
                'trip_number' => $trip->trip_number,
                'trip_type' => $trip->trip_type,
                'branch_id' => $trip->branch_id,
            ]);

            return $trip->load(['driver', 'vehicle', 'branch', 'events']);
        }, attempts: 3);
    }

    public function assign(
        Organization $organization,
        TransportTrip $trip,
        User $actor,
        int $driverId,
        ?int $vehicleId = null,
        ?string $notes = null,
    ): TransportTrip {
        return DB::transaction(function () use (
            $organization, $trip, $actor, $driverId, $vehicleId, $notes
        ): TransportTrip {
            $locked = TransportTrip::query()
                ->where('organization_id', $organization->id)
                ->whereKey($trip->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, ['planned', 'assigned'], true)) {
                throw new ConflictHttpException('Only planned or assigned trips can be assigned or reassigned.');
            }

            $driver = FleetDriver::query()
                ->where('organization_id', $organization->id)
                ->whereKey($driverId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($driver->status !== 'active') {
                throw new ConflictHttpException('The selected driver is not active.');
            }

            if ($locked->branch_id !== null && $driver->branch_id !== null && $locked->branch_id !== $driver->branch_id) {
                throw new ConflictHttpException('The driver belongs to another branch.');
            }

            $busyDriver = TransportTrip::query()
                ->where('organization_id', $organization->id)
                ->where('driver_id', $driver->id)
                ->whereKeyNot($locked->id)
                ->whereIn('status', ['assigned', 'departed'])
                ->exists();

            if ($busyDriver) {
                throw new ConflictHttpException('The driver is already assigned to an active trip.');
            }

            $vehicle = null;
            if ($vehicleId !== null) {
                $vehicle = FleetVehicle::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($vehicleId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($vehicle->status !== 'active') {
                    throw new ConflictHttpException('The selected vehicle is not active.');
                }

                if ($locked->branch_id !== null && $vehicle->branch_id !== null && $locked->branch_id !== $vehicle->branch_id) {
                    throw new ConflictHttpException('The vehicle belongs to another branch.');
                }

                $busyVehicle = TransportTrip::query()
                    ->where('organization_id', $organization->id)
                    ->where('vehicle_id', $vehicle->id)
                    ->whereKeyNot($locked->id)
                    ->whereIn('status', ['assigned', 'departed'])
                    ->exists();

                if ($busyVehicle) {
                    throw new ConflictHttpException('The vehicle is already assigned to an active trip.');
                }
            }

            $previous = $locked->status;
            $locked->driver_id = $driver->id;
            $locked->vehicle_id = $vehicle?->id;
            $locked->status = 'assigned';
            $locked->save();

            $this->event($organization, $locked, $actor, 'trip_assigned', $previous, 'assigned', $notes, [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle?->id,
            ]);
            $this->audit->record($actor, $organization, 'transport_trip.assigned', $locked, [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle?->id,
                'previous_status' => $previous,
            ]);

            return $locked->load(['driver', 'vehicle', 'branch', 'events']);
        }, attempts: 3);
    }

    public function depart(Organization $organization, TransportTrip $trip, User $actor, ?string $notes = null): TransportTrip
    {
        return $this->transition($organization, $trip, $actor, 'departed', $notes);
    }

    public function complete(Organization $organization, TransportTrip $trip, User $actor, ?string $notes = null): TransportTrip
    {
        return $this->transition($organization, $trip, $actor, 'completed', $notes);
    }

    public function cancel(Organization $organization, TransportTrip $trip, User $actor, ?string $notes = null): TransportTrip
    {
        return $this->transition($organization, $trip, $actor, 'cancelled', $notes);
    }

    private function transition(
        Organization $organization,
        TransportTrip $trip,
        User $actor,
        string $next,
        ?string $notes,
    ): TransportTrip {
        return DB::transaction(function () use ($organization, $trip, $actor, $next, $notes): TransportTrip {
            $locked = TransportTrip::query()
                ->where('organization_id', $organization->id)
                ->whereKey($trip->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === $next) {
                return $locked->load(['driver', 'vehicle', 'branch', 'events']);
            }

            if ($next === 'departed') {
                if ($locked->status !== 'assigned' || $locked->driver_id === null) {
                    throw new ConflictHttpException('Only an assigned trip with a driver can depart.');
                }
                $locked->departed_at = now();
            } elseif ($next === 'completed') {
                if ($locked->status !== 'departed') {
                    throw new ConflictHttpException('Only a departed trip can be completed.');
                }

                if ($locked->tasks()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
                    throw new ConflictHttpException('All delivery tasks must be completed or cancelled before closing this trip.');
                }

                $locked->arrived_at = now();
            } elseif ($next === 'cancelled') {
                if (! in_array($locked->status, ['planned', 'assigned'], true)) {
                    throw new ConflictHttpException('Only a planned or assigned trip can be cancelled.');
                }

                if ($locked->tasks()->whereNotIn('status', ['queued', 'cancelled'])->exists()) {
                    throw new ConflictHttpException('A trip with active delivery tasks cannot be cancelled.');
                }
            } else {
                throw ValidationException::withMessages(['status' => ['Unsupported trip status.']]);
            }

            $previous = $locked->status;
            $locked->status = $next;
            $locked->save();

            $this->event($organization, $locked, $actor, 'trip_status_changed', $previous, $next, $notes);
            $this->audit->record($actor, $organization, 'transport_trip.'.$next, $locked, [
                'previous_status' => $previous,
                'status' => $next,
                'driver_id' => $locked->driver_id,
                'vehicle_id' => $locked->vehicle_id,
            ]);

            return $locked->load(['driver', 'vehicle', 'branch', 'events']);
        }, attempts: 3);
    }

    private function event(
        Organization $organization,
        TransportTrip $trip,
        User $actor,
        string $eventType,
        ?string $previousStatus,
        string $status,
        ?string $notes = null,
        array $metadata = [],
    ): TransportTripEvent {
        return TransportTripEvent::query()->create([
            'organization_id' => $organization->id,
            'transport_trip_id' => $trip->id,
            'event_type' => $eventType,
            'previous_status' => $previousStatus,
            'status' => $status,
            'actor_user_id' => $actor->id,
            'notes' => $notes,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }
}
