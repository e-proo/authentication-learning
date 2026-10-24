<?php

namespace App\Services\Orders;

use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OrderService
{
    /**
     * Create a confirmed order and reserve inventory in one database transaction.
     *
     * All monetary amounts are integer minor units. Client-supplied totals are
     * intentionally ignored; prices are read from the selected price list.
     *
     * @return array{order: Order, created: bool}
     */
    public function place(
        Organization $organization,
        User $actor,
        array $data,
        ?string $idempotencyKey = null,
    ): array {
        $fingerprint = hash('sha256', json_encode([
            'organization_id' => $organization->id,
            'data' => $data,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $organization,
            $actor,
            $data,
            $idempotencyKey,
            $fingerprint,
        ): array {
            if ($idempotencyKey !== null) {
                $existing = Order::query()
                    ->where('organization_id', $organization->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->with(['items', 'statusHistory', 'fulfillments'])
                    ->first();

                if ($existing) {
                    if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                        throw new ConflictHttpException(
                            'This Idempotency-Key was already used for a different order request.'
                        );
                    }

                    return ['order' => $existing, 'created' => false];
                }
            }

            $store = Store::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->whereKey($data['store_id'])
                ->first();

            if (! $store) {
                throw ValidationException::withMessages([
                    'store_id' => ['The selected store is not active in this organization.'],
                ]);
            }

            $priceList = PriceList::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->whereKey($data['price_list_id'])
                ->lockForUpdate()
                ->first();

            if (! $priceList
                || ($priceList->valid_from && $priceList->valid_from->isFuture())
                || ($priceList->valid_until && $priceList->valid_until->isPast())) {
                throw ValidationException::withMessages([
                    'price_list_id' => ['The selected price list is unavailable.'],
                ]);
            }

            if ($priceList->currency !== $store->currency) {
                throw ValidationException::withMessages([
                    'price_list_id' => ['The price list currency must match the store currency.'],
                ]);
            }

            $prepared = [];
            $subtotal = 0;

            foreach ($data['items'] as $index => $inputItem) {
                $variant = ProductVariant::query()
                    ->whereKey($inputItem['product_variant_id'])
                    ->where('is_active', true)
                    ->whereHas('product', fn ($query) => $query
                        ->where('organization_id', $organization->id)
                        ->where('store_id', $store->id)
                        ->where('status', 'published'))
                    ->with('product:id,organization_id,store_id,name,status')
                    ->first();

                if (! $variant) {
                    throw ValidationException::withMessages([
                        "items.$index.product_variant_id" => [
                            'The selected SKU is not a published product in this store.',
                        ],
                    ]);
                }

                $price = PriceListItem::query()
                    ->where('price_list_id', $priceList->id)
                    ->where('product_variant_id', $variant->id)
                    ->lockForUpdate()
                    ->first();

                $quantity = (int) $inputItem['quantity'];

                if (! $price || $quantity < $price->minimum_quantity) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => [
                            'No applicable price exists or the minimum quantity was not met.',
                        ],
                    ]);
                }

                $warehouse = DB::table('warehouses')
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true)
                    ->where('id', $inputItem['warehouse_id'])
                    ->first();

                if (! $warehouse) {
                    throw ValidationException::withMessages([
                        "items.$index.warehouse_id" => [
                            'The selected warehouse is not active in this organization.',
                        ],
                    ]);
                }

                $balance = InventoryBalance::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_variant_id', $variant->id)
                    ->lockForUpdate()
                    ->first();

                if (! $balance || ($balance->on_hand - $balance->reserved) < $quantity) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => ['There is not enough available stock.'],
                    ]);
                }

                $unitPrice = (int) $price->unit_price_minor;
                $lineTotal = $unitPrice * $quantity;

                if ($lineTotal < 0 || $lineTotal > 9000000000000000) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => ['The calculated line total is out of range.'],
                    ]);
                }

                $subtotal += $lineTotal;

                $prepared[] = [
                    'variant' => $variant,
                    'warehouse_id' => (int) $warehouse->id,
                    'quantity' => $quantity,
                    'unit_price_minor' => $unitPrice,
                    'line_total_minor' => $lineTotal,
                    'product_name_snapshot' => $variant->product->name,
                    'sku_snapshot' => $variant->sku,
                    'variant_name_snapshot' => $variant->name,
                ];
            }

            if ($subtotal > 9000000000000000) {
                throw ValidationException::withMessages([
                    'items' => ['The order total is out of range.'],
                ]);
            }

            $order = Order::query()->create([
                'organization_id' => $organization->id,
                'store_id' => $store->id,
                'price_list_id' => $priceList->id,
                'customer_user_id' => $data['customer_user_id'] ?? null,
                'placed_by' => $actor->id,
                'order_number' => 'ORD-'.Str::ulid(),
                'status' => 'confirmed',
                'payment_status' => 'pending',
                'currency' => $priceList->currency,
                'subtotal_minor' => $subtotal,
                'discount_minor' => 0,
                'shipping_minor' => 0,
                'tax_minor' => 0,
                'total_minor' => $subtotal,
                'shipping_address_snapshot' => $data['shipping_address'],
                'billing_address_snapshot' => $data['billing_address'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $idempotencyKey !== null ? $fingerprint : null,
                'customer_note' => $data['customer_note'] ?? null,
                'placed_at' => now(),
                'confirmed_at' => now(),
            ]);

            foreach ($prepared as $line) {
                $orderItem = OrderItem::query()->create([
                    'organization_id' => $organization->id,
                    'order_id' => $order->id,
                    'product_variant_id' => $line['variant']->id,
                    'warehouse_id' => $line['warehouse_id'],
                    'product_name_snapshot' => $line['product_name_snapshot'],
                    'sku_snapshot' => $line['sku_snapshot'],
                    'variant_name_snapshot' => $line['variant_name_snapshot'],
                    'currency' => $priceList->currency,
                    'unit_price_minor' => $line['unit_price_minor'],
                    'quantity' => $line['quantity'],
                    'discount_minor' => 0,
                    'tax_minor' => 0,
                    'line_total_minor' => $line['line_total_minor'],
                ]);

                $balance = InventoryBalance::query()
                    ->where('warehouse_id', $line['warehouse_id'])
                    ->where('product_variant_id', $line['variant']->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $balance->reserved += $line['quantity'];
                $balance->save();

                StockReservation::query()->create([
                    'organization_id' => $organization->id,
                    'order_item_id' => $orderItem->id,
                    'warehouse_id' => $line['warehouse_id'],
                    'product_variant_id' => $line['variant']->id,
                    'quantity' => $line['quantity'],
                    'status' => 'reserved',
                    'reserved_at' => now(),
                ]);
            }

            OrderStatusHistory::query()->create([
                'organization_id' => $organization->id,
                'order_id' => $order->id,
                'previous_status' => null,
                'status' => 'confirmed',
                'actor_user_id' => $actor->id,
                'note' => 'Order created and stock reserved.',
                'created_at' => now(),
            ]);

            return [
                'order' => $order->load(['items', 'statusHistory']),
                'created' => true,
            ];
        }, attempts: 3);
    }

    public function cancel(Organization $organization, Order $order, User $actor): Order
    {
        return DB::transaction(function () use ($organization, $order, $actor): Order {
            $lockedOrder = Order::query()
                ->where('organization_id', $organization->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === 'cancelled') {
                return $lockedOrder->load(['items', 'statusHistory']);
            }

            if ($lockedOrder->status !== 'confirmed') {
                throw new ConflictHttpException(
                    'Only confirmed orders that have not entered fulfillment can be cancelled.'
                );
            }

            $reservations = StockReservation::query()
                ->where('organization_id', $organization->id)
                ->whereHas('orderItem', fn ($query) => $query->where('order_id', $lockedOrder->id))
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $balance = InventoryBalance::query()
                    ->where('warehouse_id', $reservation->warehouse_id)
                    ->where('product_variant_id', $reservation->product_variant_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($balance->reserved < $reservation->quantity) {
                    throw new \RuntimeException('Inventory reservation consistency check failed.');
                }

                $balance->reserved -= $reservation->quantity;
                $balance->save();

                $reservation->status = 'released';
                $reservation->released_at = now();
                $reservation->save();
            }

            $previous = $lockedOrder->status;
            $lockedOrder->status = 'cancelled';
            $lockedOrder->save();

            OrderStatusHistory::query()->create([
                'organization_id' => $organization->id,
                'order_id' => $lockedOrder->id,
                'previous_status' => $previous,
                'status' => 'cancelled',
                'actor_user_id' => $actor->id,
                'note' => 'Order cancelled; reserved stock released.',
                'created_at' => now(),
            ]);

            return $lockedOrder->load(['items', 'statusHistory']);
        }, attempts: 3);
    }
}
