<?php

namespace App\Services\Logistics;

use App\Models\DeliveryTask;
use App\Models\DeliveryTaskEvent;
use App\Models\FleetDriver;
use App\Models\Organization;
use App\Models\ReturnCase;
use App\Models\Shipment;
use App\Models\TransportTrip;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DeliveryTaskService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ShipmentService $shipments,
    ) {
    }

    public function create(Organization $organization, User $actor, array $data): DeliveryTask
    {
        return DB::transaction(function () use ($organization, $actor, $data): DeliveryTask {
            $shipment = null;
            if (! empty($data['shipment_id'])) {
                $shipment = Shipment::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($data['shipment_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (in_array($shipment->status, ['delivered', 'returned'], true)) {
                    throw new ConflictHttpException('A delivered or returned shipment cannot receive a new delivery task.');
                }
            }

            $returnCase = null;
            if (! empty($data['return_case_id'])) {
                $returnCase = ReturnCase::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($data['return_case_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($returnCase->status !== 'approved') {
                    throw new ConflictHttpException('A return-pickup task requires an approved return case.');
                }
            }

            if ($data['task_type'] === 'last_mile' && ! $shipment) {
                throw ValidationException::withMessages([
                    'shipment_id' => ['A last-mile task must be linked to a shipment.'],
                ]);
            }

            if ($data['task_type'] === 'return_pickup' && ! $returnCase) {
                throw ValidationException::withMessages([
                    'return_case_id' => ['A return-pickup task must be linked to an approved return case.'],
                ]);
            }

            $task = DeliveryTask::query()->create([
                'organization_id' => $organization->id,
                'shipment_id' => $shipment?->id,
                'return_case_id' => $returnCase?->id,
                'task_number' => 'DLV-'.Str::ulid(),
                'task_type' => $data['task_type'],
                'status' => 'queued',
                'sequence_number' => $data['sequence_number'] ?? null,
                'location_snapshot' => $data['location'],
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'created_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->event($organization, $task, $actor, 'task_created', null, 'queued', $data['notes'] ?? null);
            $this->audit->record($actor, $organization, 'delivery_task.created', $task, [
                'task_number' => $task->task_number,
                'task_type' => $task->task_type,
                'shipment_id' => $task->shipment_id,
                'return_case_id' => $task->return_case_id,
            ]);

            return $task->load(['shipment', 'returnCase', 'driver', 'trip', 'events']);
        }, attempts: 3);
    }

    public function assign(
        Organization $organization,
        DeliveryTask $task,
        User $actor,
        int $driverId,
        ?int $tripId = null,
        ?int $sequenceNumber = null,
        ?string $notes = null,
    ): DeliveryTask {
        return DB::transaction(function () use (
            $organization, $task, $actor, $driverId, $tripId, $sequenceNumber, $notes
        ): DeliveryTask {
            $locked = DeliveryTask::query()
                ->where('organization_id', $organization->id)
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, ['queued', 'failed'], true)) {
                throw new ConflictHttpException('Only queued or failed delivery tasks can be assigned.');
            }

            $driver = FleetDriver::query()
                ->where('organization_id', $organization->id)
                ->whereKey($driverId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($driver->status !== 'active') {
                throw new ConflictHttpException('The selected driver is not active.');
            }

            $trip = null;
            if ($tripId !== null) {
                $trip = TransportTrip::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($tripId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($trip->status, ['assigned'], true)) {
                    throw new ConflictHttpException('A delivery task can only be added to an assigned trip.');
                }

                if ($trip->driver_id !== $driver->id) {
                    throw new ConflictHttpException('The task driver must match the assigned trip driver.');
                }

                if ($trip->branch_id !== null && $driver->branch_id !== null && $trip->branch_id !== $driver->branch_id) {
                    throw new ConflictHttpException('The driver belongs to another branch.');
                }
            }

            $previous = $locked->status;
            $locked->driver_id = $driver->id;
            $locked->transport_trip_id = $trip?->id;
            $locked->sequence_number = $sequenceNumber ?? $locked->sequence_number;
            $locked->status = 'assigned';
            $locked->assigned_at = now();
            $locked->failed_at = null;
            $locked->failure_reason = null;
            if ($previous === 'failed') {
                $locked->attempt_number++;
            }
            $locked->save();

            $this->event($organization, $locked, $actor, 'task_assigned', $previous, 'assigned', $notes, [
                'driver_id' => $driver->id,
                'transport_trip_id' => $trip?->id,
                'sequence_number' => $locked->sequence_number,
            ]);
            $this->audit->record($actor, $organization, 'delivery_task.assigned', $locked, [
                'previous_status' => $previous,
                'driver_id' => $driver->id,
                'transport_trip_id' => $trip?->id,
            ]);

            return $locked->load(['shipment', 'returnCase', 'driver', 'trip', 'events']);
        }, attempts: 3);
    }

    public function transition(
        Organization $organization,
        DeliveryTask $task,
        User $actor,
        string $nextStatus,
        array $data = [],
    ): DeliveryTask {
        $transitions = [
            'queued' => ['cancelled'],
            'assigned' => ['en_route', 'cancelled'],
            'en_route' => ['arrived', 'failed'],
            'arrived' => ['completed', 'failed'],
            'failed' => [],
            'completed' => [],
            'cancelled' => [],
        ];

        return DB::transaction(function () use (
            $organization, $task, $actor, $nextStatus, $data, $transitions
        ): DeliveryTask {
            $locked = DeliveryTask::query()
                ->where('organization_id', $organization->id)
                ->whereKey($task->id)
                ->with('shipment')
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === $nextStatus) {
                return $locked->load(['shipment', 'returnCase', 'driver', 'trip', 'events']);
            }

            if (! in_array($nextStatus, $transitions[$locked->status] ?? [], true)) {
                throw new ConflictHttpException(
                    "Delivery task status cannot change from {$locked->status} to {$nextStatus}."
                );
            }

            if ($nextStatus === 'failed' && trim((string) ($data['failure_reason'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'failure_reason' => ['A failure reason is required.'],
                ]);
            }

            if ($nextStatus === 'completed' && $locked->task_type === 'last_mile') {
                if (trim((string) ($data['proof_reference'] ?? $locked->proof_reference ?? '')) === ''
                    || trim((string) ($data['recipient_name'] ?? $locked->recipient_name ?? '')) === '') {
                    throw ValidationException::withMessages([
                        'proof_reference' => ['A proof reference and recipient name are required to complete a last-mile delivery.'],
                    ]);
                }

                if (! $locked->shipment) {
                    throw new ConflictHttpException('The last-mile task has no shipment.');
                }

                $shipment = Shipment::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($locked->shipment_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($shipment->status !== 'delivered') {
                    if (! in_array($shipment->status, ['picked_up', 'in_transit', 'out_for_delivery'], true)) {
                        throw new ConflictHttpException('The shipment must be picked up and in transit before the delivery task can complete.');
                    }

                    $this->shipments->transition(
                        $organization,
                        $shipment,
                        $actor,
                        'delivered',
                        $data['notes'] ?? null,
                        $data['location'] ?? null,
                        $data['metadata'] ?? [],
                    );
                }
            }

            if ($nextStatus === 'en_route' && $locked->task_type === 'last_mile') {
                if (! $locked->shipment) {
                    throw new ConflictHttpException('The last-mile task has no shipment.');
                }

                $shipment = Shipment::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($locked->shipment_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($shipment->status === 'created' || $shipment->status === 'label_created') {
                    $shipment = $this->shipments->transition(
                        $organization, $shipment, $actor, 'picked_up',
                        'Picked up for last-mile delivery.', $data['location'] ?? null, $data['metadata'] ?? [],
                    );
                }

                if (in_array($shipment->status, ['picked_up', 'in_transit', 'failed'], true)) {
                    $this->shipments->transition(
                        $organization, $shipment, $actor, 'out_for_delivery',
                        'Last-mile delivery started.', $data['location'] ?? null, $data['metadata'] ?? [],
                    );
                } elseif ($shipment->status !== 'out_for_delivery') {
                    throw new ConflictHttpException('The shipment is not in a state that can be delivered.');
                }
            }

            if ($nextStatus === 'failed' && $locked->task_type === 'last_mile' && $locked->shipment) {
                $shipment = Shipment::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($locked->shipment_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (in_array($shipment->status, ['picked_up', 'in_transit', 'out_for_delivery'], true)) {
                    $this->shipments->transition(
                        $organization,
                        $shipment,
                        $actor,
                        'failed',
                        $data['failure_reason'],
                        $data['location'] ?? null,
                        $data['metadata'] ?? [],
                    );
                }
            }

            $previous = $locked->status;
            $locked->status = $nextStatus;

            if ($nextStatus === 'en_route') {
                $locked->started_at = now();
            } elseif ($nextStatus === 'arrived') {
                $locked->arrived_at = now();
            } elseif ($nextStatus === 'completed') {
                $locked->completed_at = now();
                $locked->proof_reference = $data['proof_reference'] ?? $locked->proof_reference;
                $locked->recipient_name = $data['recipient_name'] ?? $locked->recipient_name;
                $locked->recipient_phone = $data['recipient_phone'] ?? $locked->recipient_phone;
            } elseif ($nextStatus === 'failed') {
                $locked->failed_at = now();
                $locked->failure_reason = $data['failure_reason'] ?? null;
            } elseif ($nextStatus === 'cancelled') {
                $locked->cancelled_at = now();
            }

            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }

            $locked->save();

            $this->event($organization, $locked, $actor, 'task_status_changed', $previous, $nextStatus, $data['notes'] ?? null, [
                'location' => $data['location'] ?? null,
                'failure_reason' => $locked->failure_reason,
                'proof_reference' => $locked->proof_reference,
                'recipient_name' => $locked->recipient_name,
            ]);

            $this->audit->record($actor, $organization, 'delivery_task.'.$nextStatus, $locked, [
                'previous_status' => $previous,
                'status' => $nextStatus,
                'shipment_id' => $locked->shipment_id,
                'transport_trip_id' => $locked->transport_trip_id,
                'attempt_number' => $locked->attempt_number,
            ]);

            return $locked->load(['shipment', 'returnCase', 'driver', 'trip', 'events']);
        }, attempts: 3);
    }

    private function event(
        Organization $organization,
        DeliveryTask $task,
        User $actor,
        string $eventType,
        ?string $previous,
        string $status,
        ?string $notes = null,
        array $metadata = [],
    ): DeliveryTaskEvent {
        return DeliveryTaskEvent::query()->create([
            'organization_id' => $organization->id,
            'delivery_task_id' => $task->id,
            'event_type' => $eventType,
            'previous_status' => $previous,
            'status' => $status,
            'actor_user_id' => $actor->id,
            'notes' => $notes,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }
}
