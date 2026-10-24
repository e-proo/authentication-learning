<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InvoiceService
{
    /**
     * Issue at most one invoice for an order and snapshot its commercial lines.
     *
     * @return array{invoice: Invoice, created: bool}
     */
    public function issueForOrder(Organization $organization, Order $order): array
    {
        return DB::transaction(function () use ($organization, $order): array {
            $lockedOrder = Order::query()
                ->where('organization_id', $organization->id)
                ->whereKey($order->id)
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();

            $existing = Invoice::query()
                ->where('organization_id', $organization->id)
                ->where('order_id', $lockedOrder->id)
                ->with(['items', 'payments'])
                ->first();

            if ($existing) {
                return ['invoice' => $existing, 'created' => false];
            }

            if ($lockedOrder->status === 'cancelled' || $lockedOrder->items->isEmpty()) {
                throw new ConflictHttpException('An invoice cannot be issued for a cancelled or empty order.');
            }

            $invoice = Invoice::query()->create([
                'organization_id' => $organization->id,
                'order_id' => $lockedOrder->id,
                'invoice_number' => 'INV-'.Str::ulid(),
                'status' => 'issued',
                'currency' => $lockedOrder->currency,
                'subtotal_minor' => $lockedOrder->subtotal_minor,
                'discount_minor' => $lockedOrder->discount_minor,
                'shipping_minor' => $lockedOrder->shipping_minor,
                'tax_minor' => $lockedOrder->tax_minor,
                'total_minor' => $lockedOrder->total_minor,
                'paid_total_minor' => 0,
                'issued_at' => now(),
            ]);

            foreach ($lockedOrder->items as $orderItem) {
                InvoiceItem::query()->create([
                    'organization_id' => $organization->id,
                    'invoice_id' => $invoice->id,
                    'order_item_id' => $orderItem->id,
                    'description_snapshot' => $orderItem->product_name_snapshot,
                    'sku_snapshot' => $orderItem->sku_snapshot,
                    'currency' => $orderItem->currency,
                    'unit_price_minor' => $orderItem->unit_price_minor,
                    'quantity' => $orderItem->quantity,
                    'discount_minor' => $orderItem->discount_minor,
                    'tax_minor' => $orderItem->tax_minor,
                    'line_total_minor' => $orderItem->line_total_minor,
                ]);
            }

            return ['invoice' => $invoice->load('items'), 'created' => true];
        }, attempts: 3);
    }
}
