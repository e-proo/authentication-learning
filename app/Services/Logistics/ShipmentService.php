<?php

namespace App\Services\Logistics;

use App\Models\Fulfillment;
use App\Services\Audit\AuditLogger;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Organization;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShipmentItem;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ShipmentService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function createForFulfillment(
        Organization $organization,
        Fulfillment $fulfillment,
        User $actor,
        string $carrierType = 'internal',
        ?string $carrierName = null,
        ?string $trackingNumber = null,
    ): Shipment {
        return DB::transaction(function () use (
            $organization,
            $fulfillment,
            $actor,
            $carrierType,
            $carrierName,
            $trackingNumber,
        ): Shipment {
            $lockedFulfillment = Fulfillment::query()
                ->where('organization_id', $organization->id)
                ->whereKey($fulfillment->id)
                ->with(['items.orderItem', 'order'])
                ->lockForUpdate()
                ->firstOrFail();

            $existing = Shipment::query()
                ->where('organization_id', $organization->id)
                ->where('fulfillment_id', $lockedFulfillment->id)
                ->first();

            if ($existing) {
                return $existing->load(['items.orderItem', 'events']);
            }

            if ($lockedFulfillment->status !== 'packed') {
                throw new ConflictHttpException('A shipment can only be created for a packed fulfillment.');
            }

            if ($lockedFulfillment->items->isEmpty()) {
                throw new ConflictHttpException('A fulfillment without items cannot be shipped.');
            }

            $shipment = Shipment::query()->create([
                'organization_id' => $organization->id,
                'order_id' => $lockedFulfillment->order_id,
                'fulfillment_id' => $lockedFulfillment->id,
                'shipment_number' => 'SHP-'.Str::ulid(),
                'carrier_type' => $carrierType,
                'carrier_name' => $carrierName,
                'tracking_number' => $trackingNumber,
                'status' => 'created',
                'delivery_address_snapshot' => $lockedFulfillment->order->shipping_address_snapshot,
            ]);

            foreach ($lockedFulfillment->items as $fulfillmentItem) {
                ShipmentItem::query()->create([
                    'organization_id' => $organization->id,
                    'shipment_id' => $shipment->id,
                    'order_item_id' => $fulfillmentItem->order_item_id,
                    'quantity' => $fulfillmentItem->quantity,
                ]);
            }

            ShipmentEvent::query()->create([
                'organization_id' => $organization->id,
                'shipment_id' => $shipment->id,
                'status' => 'created',
                'notes' => 'Shipment record created.',
                'actor_user_id' => $actor->id,
                'occurred_at' => now(),
            ]);

            $this->audit->record($actor, $organization, 'shipment.created', $shipment, [
                'shipment_number' => $shipment->shipment_number,
                'fulfillment_id' => $lockedFulfillment->id,
                'carrier_type' => $shipment->carrier_type,
            ]);

            return $shipment->load(['items.orderItem', 'events']);
        }, attempts: 3);
    }

    public function transition(
        Organization $organization,
        Shipment $shipment,
        User $actor,
        string $nextStatus,
        ?string $notes = null,
        ?string $location = null,
        array $metadata = [],
    ): Shipment {
        $transitions = [
            'created' => ['label_created', 'picked_up'],
            'label_created' => ['picked_up'],
            'picked_up' => ['in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned'],
            'in_transit' => ['out_for_delivery', 'delivered', 'failed', 'returned'],
            'out_for_delivery' => ['delivered', 'failed', 'returned'],
            'failed' => ['in_transit', 'out_for_delivery', 'returned'],
            'delivered' => [],
            'returned' => [],
        ];

        if (! array_key_exists($nextStatus, array_flip([
            'label_created', 'picked_up', 'in_transit', 'out_for_delivery',
            'delivered', 'failed', 'returned',
        ]))) {
            throw ValidationException::withMessages([
                'status' => ['The requested shipment status is not supported.'],
            ]);
        }

        return DB::transaction(function () use (
            $organization,
            $shipment,
            $actor,
            $nextStatus,
            $notes,
            $location,
            $metadata,
            $transitions,
        ): Shipment {
            $locked = Shipment::query()
                ->where('organization_id', $organization->id)
                ->whereKey($shipment->id)
                ->with(['items', 'fulfillment'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === $nextStatus) {
                return $locked->load(['items.orderItem', 'events']);
            }

            if (! in_array($nextStatus, $transitions[$locked->status] ?? [], true)) {
                throw new ConflictHttpException(
                    "Shipment status cannot change from {$locked->status} to {$nextStatus}."
                );
            }

            $previousStatus = $locked->status;

            if ($nextStatus === 'picked_up') {
                $this->dispatchStock($organization, $locked, $actor);
                $locked->shipped_at = now();
                $locked->fulfillment->status = 'shipped';
                $locked->fulfillment->shipped_at = now();
                $locked->fulfillment->save();
            }

            if ($nextStatus === 'delivered') {
                $locked->delivered_at = now();
                $locked->fulfillment->status = 'completed';
                $locked->fulfillment->completed_at = now();
                $locked->fulfillment->save();
            }

            $locked->status = $nextStatus;
            $locked->save();

            ShipmentEvent::query()->create([
                'organization_id' => $organization->id,
                'shipment_id' => $locked->id,
                'status' => $nextStatus,
                'notes' => $notes,
                'location' => $location,
                'actor_user_id' => $actor->id,
                'metadata' => $metadata ?: null,
                'occurred_at' => now(),
            ]);

            $this->audit->record($actor, $organization, 'shipment.status_changed', $locked, [
                'previous_status' => $previousStatus,
                'status' => $nextStatus,
                'location' => $location,
            ]);

            if ($nextStatus === 'delivered') {
                $order = Order::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($locked->order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $allComplete = Fulfillment::query()
                    ->where('organization_id', $organization->id)
                    ->where('order_id', $order->id)
                    ->where('status', '!=', 'completed')
                    ->doesntExist();

                if ($allComplete && $order->status !== 'delivered') {
                    $previous = $order->status;
                    $order->status = 'delivered';
                    $order->save();

                    OrderStatusHistory::query()->create([
                        'organization_id' => $organization->id,
                        'order_id' => $order->id,
                        'previous_status' => $previous,
                        'status' => 'delivered',
                        'actor_user_id' => $actor->id,
                        'note' => 'All fulfillments have been delivered.',
                        'created_at' => now(),
                    ]);
                }
            }

            return $locked->load(['items.orderItem', 'events', 'fulfillment']);
        }, attempts: 3);
    }

    private function dispatchStock(Organization $organization, Shipment $shipment, User $actor): void
    {
        foreach ($shipment->items as $shipmentItem) {
            $reservation = StockReservation::query()
                ->where('organization_id', $organization->id)
                ->where('order_item_id', $shipmentItem->order_item_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->status === 'consumed') {
                continue;
            }

            if ($reservation->status !== 'reserved' || $reservation->quantity !== $shipmentItem->quantity) {
                throw new ConflictHttpException('The shipment does not match the active stock reservation.');
            }

            $balance = InventoryBalance::query()
                ->where('warehouse_id', $reservation->warehouse_id)
                ->where('product_variant_id', $reservation->product_variant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($balance->on_hand < $reservation->quantity || $balance->reserved < $reservation->quantity) {
                throw new \RuntimeException('Inventory reservation consistency check failed during dispatch.');
            }

            $balance->on_hand -= $reservation->quantity;
            $balance->reserved -= $reservation->quantity;
            $balance->save();

            $reservation->status = 'consumed';
            $reservation->consumed_at = now();
            $reservation->save();

            InventoryMovement::query()->firstOrCreate(
                ['warehouse_id' => $reservation->warehouse_id, 'idempotency_key' => 'shipment:'.$shipment->id.':item:'.$shipmentItem->order_item_id.':dispatch'],
                [
                    'warehouse_id' => $reservation->warehouse_id,
                    'product_variant_id' => $reservation->product_variant_id,
                    'movement_type' => 'shipment_dispatch',
                    'quantity_delta' => -$reservation->quantity,
                    'reference_type' => Shipment::class,
                    'reference_id' => (string) $shipment->id,
                    'metadata' => ['order_item_id' => $shipmentItem->order_item_id],
                    'created_by' => $actor->id,
                    'created_at' => now(),
                ],
            );
        }
    }
}
