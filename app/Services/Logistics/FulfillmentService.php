<?php

namespace App\Services\Logistics;

use App\Models\Fulfillment;
use App\Services\Audit\AuditLogger;
use App\Models\FulfillmentItem;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FulfillmentService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * Create one fulfillment for each warehouse used by the confirmed order.
     *
     * @return list<Fulfillment>
     */
    public function createForOrder(Organization $organization, Order $order, User $actor): array
    {
        return DB::transaction(function () use ($organization, $order, $actor): array {
            $lockedOrder = Order::query()
                ->where('organization_id', $organization->id)
                ->whereKey($order->id)
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();

            $existing = Fulfillment::query()
                ->where('organization_id', $organization->id)
                ->where('order_id', $lockedOrder->id)
                ->with(['items.orderItem', 'warehouse', 'shipment'])
                ->get();

            if ($existing->isNotEmpty()) {
                return $existing->all();
            }

            if ($lockedOrder->status !== 'confirmed') {
                throw new ConflictHttpException('Only confirmed orders can enter fulfillment.');
            }

            if ($lockedOrder->items->isEmpty()) {
                throw new ConflictHttpException('An order without items cannot be fulfilled.');
            }

            $created = [];
            foreach ($lockedOrder->items->groupBy('warehouse_id') as $warehouseId => $items) {
                $fulfillment = Fulfillment::query()->create([
                    'organization_id' => $organization->id,
                    'order_id' => $lockedOrder->id,
                    'warehouse_id' => (int) $warehouseId,
                    'fulfillment_number' => 'FUL-'.Str::ulid(),
                    'status' => 'picking',
                ]);

                foreach ($items as $item) {
                    FulfillmentItem::query()->create([
                        'organization_id' => $organization->id,
                        'fulfillment_id' => $fulfillment->id,
                        'order_item_id' => $item->id,
                        'quantity' => $item->quantity,
                    ]);
                }

                $this->audit->record($actor, $organization, 'fulfillment.created', $fulfillment, [
                    'order_id' => $lockedOrder->id,
                    'warehouse_id' => (int) $warehouseId,
                    'item_count' => $items->count(),
                ]);

                $created[] = $fulfillment;
            }

            $previous = $lockedOrder->status;
            $lockedOrder->status = 'processing';
            $lockedOrder->save();

            OrderStatusHistory::query()->create([
                'organization_id' => $organization->id,
                'order_id' => $lockedOrder->id,
                'previous_status' => $previous,
                'status' => 'processing',
                'actor_user_id' => $actor->id,
                'note' => 'Fulfillment created for all warehouse groups.',
                'created_at' => now(),
            ]);

            return array_map(
                fn (Fulfillment $fulfillment) => $fulfillment->load(['items.orderItem', 'warehouse']),
                $created,
            );
        }, attempts: 3);
    }

    public function pack(Organization $organization, Fulfillment $fulfillment, User $actor): Fulfillment
    {
        return DB::transaction(function () use ($organization, $fulfillment, $actor): Fulfillment {
            $locked = Fulfillment::query()
                ->where('organization_id', $organization->id)
                ->whereKey($fulfillment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'packed') {
                return $locked->load(['items.orderItem', 'warehouse']);
            }

            if ($locked->status !== 'picking') {
                throw new ConflictHttpException('Only a fulfillment in picking status can be packed.');
            }

            $locked->status = 'packed';
            $locked->packed_at = now();
            $locked->save();

            $this->audit->record($actor, $organization, 'fulfillment.packed', $locked, [
                'order_id' => $locked->order_id,
                'warehouse_id' => $locked->warehouse_id,
            ]);

            return $locked->load(['items.orderItem', 'warehouse']);
        }, attempts: 3);
    }
}
