<?php

namespace App\Services\Logistics;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\ProductVariant;
use App\Models\ReturnCase;
use App\Models\ReturnItem;
use App\Models\ReturnReceipt;
use App\Models\ReturnStatusHistory;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReturnService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function requestForOrder(
        Organization $organization,
        Order $order,
        User $actor,
        array $data,
    ): ReturnCase {
        return DB::transaction(function () use ($organization, $order, $actor, $data): ReturnCase {
            $lockedOrder = Order::query()
                ->where('organization_id', $organization->id)
                ->whereKey($order->id)
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== 'delivered') {
                throw new ConflictHttpException('A return can only be requested after the order is delivered.');
            }

            $preparedItems = [];
            foreach ($data['items'] as $index => $input) {
                $orderItem = OrderItem::query()
                    ->where('organization_id', $organization->id)
                    ->where('order_id', $lockedOrder->id)
                    ->whereKey($input['order_item_id'])
                    ->first();

                if (! $orderItem) {
                    throw ValidationException::withMessages([
                        "items.$index.order_item_id" => ['The item does not belong to this order.'],
                    ]);
                }

                $deliveredQuantity = (int) ShipmentItem::query()
                    ->where('order_item_id', $orderItem->id)
                    ->whereHas('shipment', fn ($query) => $query
                        ->where('organization_id', $organization->id)
                        ->where('status', 'delivered'))
                    ->sum('quantity');

                $openRequestedQuantity = (int) ReturnItem::query()
                    ->where('organization_id', $organization->id)
                    ->where('order_item_id', $orderItem->id)
                    ->whereHas('returnCase', fn ($query) => $query
                        ->whereIn('status', ['requested', 'approved']))
                    ->sum('requested_quantity');

                $alreadyReceivedQuantity = (int) DB::table('return_items as ri')
                    ->join('return_cases as rc', 'rc.id', '=', 'ri.return_case_id')
                    ->join('return_receipts as rr', 'rr.return_item_id', '=', 'ri.id')
                    ->where('ri.organization_id', $organization->id)
                    ->where('ri.order_item_id', $orderItem->id)
                    ->whereIn('rc.status', ['received', 'closed'])
                    ->sum('rr.quantity_received');

                $requestedQuantity = (int) $input['quantity'];
                if ($deliveredQuantity < 1
                    || $openRequestedQuantity + $alreadyReceivedQuantity + $requestedQuantity > $deliveredQuantity) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => [
                            'The return quantity exceeds delivered quantity after accounting for other open or completed returns.',
                        ],
                    ]);
                }

                $preparedItems[] = [
                    'order_item' => $orderItem,
                    'quantity' => $requestedQuantity,
                    'reason' => $input['reason'] ?? null,
                ];
            }

            $returnCase = ReturnCase::query()->create([
                'organization_id' => $organization->id,
                'order_id' => $lockedOrder->id,
                'return_number' => 'RET-'.Str::ulid(),
                'status' => 'requested',
                'reason' => $data['reason'],
                'request_notes' => $data['notes'] ?? null,
                'requested_by' => $actor->id,
                'requested_at' => now(),
            ]);

            foreach ($preparedItems as $item) {
                ReturnItem::query()->create([
                    'organization_id' => $organization->id,
                    'return_case_id' => $returnCase->id,
                    'order_item_id' => $item['order_item']->id,
                    'requested_quantity' => $item['quantity'],
                    'reason' => $item['reason'],
                ]);
            }

            ReturnStatusHistory::query()->create([
                'organization_id' => $organization->id,
                'return_case_id' => $returnCase->id,
                'previous_status' => null,
                'status' => 'requested',
                'actor_user_id' => $actor->id,
                'note' => $data['notes'] ?? null,
                'metadata' => ['item_count' => count($preparedItems)],
                'created_at' => now(),
            ]);

            $this->audit->record($actor, $organization, 'return.requested', $returnCase, [
                'order_id' => $lockedOrder->id,
                'return_number' => $returnCase->return_number,
                'reason' => $returnCase->reason,
                'item_count' => count($preparedItems),
            ]);

            return $returnCase->load(['items.orderItem', 'statusHistory', 'order']);
        }, attempts: 3);
    }

    public function approve(
        Organization $organization,
        ReturnCase $returnCase,
        User $actor,
        ?string $notes = null,
    ): ReturnCase {
        return $this->decide($organization, $returnCase, $actor, 'approved', $notes);
    }

    public function reject(
        Organization $organization,
        ReturnCase $returnCase,
        User $actor,
        string $notes,
    ): ReturnCase {
        return $this->decide($organization, $returnCase, $actor, 'rejected', $notes);
    }

    private function decide(
        Organization $organization,
        ReturnCase $returnCase,
        User $actor,
        string $nextStatus,
        ?string $notes,
    ): ReturnCase {
        return DB::transaction(function () use ($organization, $returnCase, $actor, $nextStatus, $notes): ReturnCase {
            $locked = ReturnCase::query()
                ->where('organization_id', $organization->id)
                ->whereKey($returnCase->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === $nextStatus) {
                return $locked->load(['items.orderItem', 'items.receipt', 'statusHistory', 'order']);
            }

            if ($locked->status !== 'requested') {
                throw new ConflictHttpException('Only a newly requested return can be approved or rejected.');
            }

            $previous = $locked->status;
            $locked->status = $nextStatus;
            $locked->decision_notes = $notes;

            if ($nextStatus === 'approved') {
                $locked->approved_by = $actor->id;
                $locked->approved_at = now();
            } else {
                $locked->rejected_at = now();
            }

            $locked->save();

            ReturnStatusHistory::query()->create([
                'organization_id' => $organization->id,
                'return_case_id' => $locked->id,
                'previous_status' => $previous,
                'status' => $nextStatus,
                'actor_user_id' => $actor->id,
                'note' => $notes,
                'created_at' => now(),
            ]);

            $this->audit->record($actor, $organization, 'return.'.$nextStatus, $locked, [
                'return_number' => $locked->return_number,
                'order_id' => $locked->order_id,
            ]);

            return $locked->load(['items.orderItem', 'items.receipt', 'statusHistory', 'order']);
        }, attempts: 3);
    }

    public function receiveItem(
        Organization $organization,
        ReturnCase $returnCase,
        ReturnItem $returnItem,
        User $actor,
        array $data,
    ): ReturnReceipt {
        return DB::transaction(function () use ($organization, $returnCase, $returnItem, $actor, $data): ReturnReceipt {
            $lockedCase = ReturnCase::query()
                ->where('organization_id', $organization->id)
                ->whereKey($returnCase->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedItem = ReturnItem::query()
                ->where('organization_id', $organization->id)
                ->where('return_case_id', $lockedCase->id)
                ->whereKey($returnItem->id)
                ->with('orderItem')
                ->lockForUpdate()
                ->firstOrFail();

            $existing = ReturnReceipt::query()
                ->where('organization_id', $organization->id)
                ->where('return_item_id', $lockedItem->id)
                ->first();

            if ($existing) {
                $sameReceipt = (int) $existing->quantity_received === (int) $data['quantity_received']
                    && $existing->disposition === $data['disposition']
                    && (int) ($existing->warehouse_id ?? 0) === (int) ($data['warehouse_id'] ?? 0);

                if (! $sameReceipt) {
                    throw new ConflictHttpException('This return item already has a different receipt record.');
                }

                return $existing->load(['warehouse', 'receiver', 'returnItem.returnCase']);
            }

            if ($lockedCase->status !== 'approved') {
                throw new ConflictHttpException('Only an approved return can be received.');
            }

            $quantity = (int) $data['quantity_received'];
            if ($quantity !== $lockedItem->requested_quantity) {
                throw ValidationException::withMessages([
                    'quantity_received' => ['This version requires the complete requested quantity for each return line. Create a separate return case for a partial return.'],
                ]);
            }

            $warehouse = null;
            if ($data['disposition'] === 'restock') {
                if (empty($data['warehouse_id'])) {
                    throw ValidationException::withMessages([
                        'warehouse_id' => ['A warehouse is required for restock disposition.'],
                    ]);
                }

                $warehouse = Warehouse::query()
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true)
                    ->whereKey($data['warehouse_id'])
                    ->firstOrFail();

                $variant = ProductVariant::query()
                    ->whereKey($lockedItem->orderItem->product_variant_id)
                    ->where('is_active', true)
                    ->whereHas('product', fn ($query) => $query
                        ->where('organization_id', $organization->id))
                    ->first();

                if (! $variant) {
                    throw new ConflictHttpException('The returned SKU is no longer available in this organization.');
                }

                InventoryBalance::query()->firstOrCreate(
                    ['warehouse_id' => $warehouse->id, 'product_variant_id' => $variant->id],
                    ['on_hand' => 0, 'reserved' => 0, 'reorder_level' => 0],
                );

                $balance = InventoryBalance::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_variant_id', $variant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($balance->on_hand > 9000000000000000 - $quantity) {
                    throw ValidationException::withMessages([
                        'quantity_received' => ['The resulting stock balance is out of range.'],
                    ]);
                }

                $balance->on_hand += $quantity;
                $balance->save();

                InventoryMovement::query()->firstOrCreate(
                    [
                        'warehouse_id' => $warehouse->id,
                        'idempotency_key' => 'return:'.$lockedCase->id.':item:'.$lockedItem->id.':receipt',
                    ],
                    [
                        'product_variant_id' => $variant->id,
                        'movement_type' => 'return_restock',
                        'quantity_delta' => $quantity,
                        'reference_type' => ReturnCase::class,
                        'reference_id' => (string) $lockedCase->id,
                        'metadata' => ['return_item_id' => $lockedItem->id],
                        'created_by' => $actor->id,
                        'created_at' => now(),
                    ],
                );
            } elseif (! empty($data['warehouse_id'])) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['A warehouse can only be set for restock disposition.'],
                ]);
            }

            $receipt = ReturnReceipt::query()->create([
                'organization_id' => $organization->id,
                'return_item_id' => $lockedItem->id,
                'warehouse_id' => $warehouse?->id,
                'quantity_received' => $quantity,
                'disposition' => $data['disposition'],
                'notes' => $data['notes'] ?? null,
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);

            $allItemsReceived = ! ReturnItem::query()
                ->where('organization_id', $organization->id)
                ->where('return_case_id', $lockedCase->id)
                ->whereDoesntHave('receipt')
                ->exists();

            if ($allItemsReceived) {
                $previous = $lockedCase->status;
                $lockedCase->status = 'received';
                $lockedCase->received_by = $actor->id;
                $lockedCase->received_at = now();
                $lockedCase->save();

                ReturnStatusHistory::query()->create([
                    'organization_id' => $organization->id,
                    'return_case_id' => $lockedCase->id,
                    'previous_status' => $previous,
                    'status' => 'received',
                    'actor_user_id' => $actor->id,
                    'note' => 'All return lines have a receipt record.',
                    'created_at' => now(),
                ]);

                $this->audit->record($actor, $organization, 'return.received', $lockedCase, [
                    'return_number' => $lockedCase->return_number,
                ]);
            }

            $this->audit->record($actor, $organization, 'return.item_received', $receipt, [
                'return_case_id' => $lockedCase->id,
                'quantity_received' => $quantity,
                'disposition' => $receipt->disposition,
                'warehouse_id' => $warehouse?->id,
            ]);

            return $receipt->load(['warehouse', 'receiver', 'returnItem.returnCase']);
        }, attempts: 3);
    }

    public function close(
        Organization $organization,
        ReturnCase $returnCase,
        User $actor,
        ?string $notes = null,
    ): ReturnCase {
        return DB::transaction(function () use ($organization, $returnCase, $actor, $notes): ReturnCase {
            $locked = ReturnCase::query()
                ->where('organization_id', $organization->id)
                ->whereKey($returnCase->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'closed') {
                return $locked->load(['items.orderItem', 'items.receipt', 'statusHistory', 'order']);
            }

            if ($locked->status !== 'received') {
                throw new ConflictHttpException('Only a fully received return can be closed.');
            }

            $locked->status = 'closed';
            $locked->closed_at = now();
            if ($notes !== null) {
                $locked->decision_notes = $notes;
            }
            $locked->save();

            ReturnStatusHistory::query()->create([
                'organization_id' => $organization->id,
                'return_case_id' => $locked->id,
                'previous_status' => 'received',
                'status' => 'closed',
                'actor_user_id' => $actor->id,
                'note' => $notes,
                'created_at' => now(),
            ]);

            $this->audit->record($actor, $organization, 'return.closed', $locked, [
                'return_number' => $locked->return_number,
                'order_id' => $locked->order_id,
            ]);

            return $locked->load(['items.orderItem', 'items.receipt', 'statusHistory', 'order']);
        }, attempts: 3);
    }
}
