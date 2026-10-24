<?php

namespace Tests\Feature;

use App\Models\Fulfillment;
use App\Models\InventoryBalance;
use App\Models\Membership;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockReservation;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('api_version', 'v1');
    }

    public function test_api_about_endpoint_lists_order_to_delivery_modules(): void
    {
        $this->getJson('/api/v1/about')
            ->assertOk()
            ->assertJsonPath('status', 'order-to-delivery-foundation')
            ->assertJsonPath('modules.0', 'identity')
            ->assertJsonPath('modules.5', 'orders')
            ->assertJsonPath('modules.7', 'shipments');
    }

    public function test_order_can_be_fulfilled_shipped_and_delivered_with_stock_reconciliation(): void
    {
        [$user, $organization, $store, $priceList, $variant, $warehouse] = $this->createCommerceFixture();

        $payload = [
            'store_id' => $store->id,
            'price_list_id' => $priceList->id,
            'items' => [[
                'product_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => 2,
            ]],
            'shipping_address' => [
                'name' => 'Test Buyer',
                'phone' => '+967700000000',
                'country_code' => 'YE',
                'city' => 'Sanaa',
                'address_line1' => 'Test street',
            ],
        ];

        $orderResponse = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'test-order-key-0001')
            ->postJson("/api/v1/organizations/{$organization->id}/orders", $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.total_minor', 2500);

        $orderId = $orderResponse->json('data.id');
        $orderItemId = Order::query()->findOrFail($orderId)->items()->value('id');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'order.created',
            'subject_id' => (string) $orderId,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'on_hand' => 10,
            'reserved' => 2,
        ]);

        $this->postJson("/api/v1/organizations/{$organization->id}/orders/{$orderId}/fulfillments")
            ->assertCreated()
            ->assertJsonPath('data.0.status', 'picking');

        $fulfillmentId = Fulfillment::query()
            ->where('organization_id', $organization->id)
            ->where('order_id', $orderId)
            ->value('id');

        $this->postJson("/api/v1/organizations/{$organization->id}/fulfillments/{$fulfillmentId}/pack")
            ->assertOk()
            ->assertJsonPath('data.status', 'packed');

        $shipmentResponse = $this->postJson(
            "/api/v1/organizations/{$organization->id}/fulfillments/{$fulfillmentId}/shipments",
            ['carrier_type' => 'internal'],
        )->assertCreated()->assertJsonPath('data.status', 'created');

        $shipmentId = $shipmentResponse->json('data.id');

        $this->postJson("/api/v1/organizations/{$organization->id}/shipments/{$shipmentId}/events", [
            'status' => 'picked_up',
            'location' => 'Warehouse dispatch gate',
        ])->assertOk()->assertJsonPath('data.status', 'picked_up');

        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'on_hand' => 8,
            'reserved' => 0,
        ]);

        $this->postJson("/api/v1/organizations/{$organization->id}/shipments/{$shipmentId}/events", [
            'status' => 'in_transit',
        ])->assertOk();

        $this->postJson("/api/v1/organizations/{$organization->id}/shipments/{$shipmentId}/events", [
            'status' => 'delivered',
            'location' => 'Customer address',
        ])->assertOk()->assertJsonPath('data.status', 'delivered');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'delivered']);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'shipment.status_changed',
            'subject_id' => (string) $shipmentId,
        ]);
        $this->assertDatabaseHas('stock_reservations', ['order_item_id' => $orderItemId, 'status' => 'consumed']);
        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'movement_type' => 'shipment_dispatch',
            'quantity_delta' => -2,
        ]);
    }

    public function test_idempotency_key_does_not_create_duplicate_orders_or_reservations(): void
    {
        [$user, $organization, $store, $priceList, $variant, $warehouse] = $this->createCommerceFixture();

        $payload = [
            'store_id' => $store->id,
            'price_list_id' => $priceList->id,
            'items' => [[
                'product_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => 1,
            ]],
            'shipping_address' => [
                'name' => 'Test Buyer',
                'phone' => '+967700000000',
                'country_code' => 'YE',
                'city' => 'Sanaa',
                'address_line1' => 'Test street',
            ],
        ];

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'idempotent-order-001')
            ->postJson("/api/v1/organizations/{$organization->id}/orders", $payload)
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'idempotent-order-001')
            ->postJson("/api/v1/organizations/{$organization->id}/orders", $payload)
            ->assertOk()
            ->assertJsonPath('created', false);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'reserved' => 1,
        ]);
    }

    public function test_invoice_and_partial_offline_payments_reconcile_order_totals(): void
    {
        [$user, $organization, $store, $priceList, $variant, $warehouse] = $this->createCommerceFixture();

        $payload = [
            'store_id' => $store->id,
            'price_list_id' => $priceList->id,
            'items' => [[
                'product_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => 2,
            ]],
            'shipping_address' => [
                'name' => 'Finance Test',
                'phone' => '+967700000001',
                'country_code' => 'YE',
                'city' => 'Sanaa',
                'address_line1' => 'Finance test street',
            ],
        ];

        $orderResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/orders", $payload)
            ->assertCreated();
        $orderId = $orderResponse->json('data.id');

        $invoiceResponse = $this->postJson(
            "/api/v1/organizations/{$organization->id}/orders/{$orderId}/invoice"
        )->assertCreated()->assertJsonPath('data.status', 'issued');
        $invoiceId = $invoiceResponse->json('data.id');

        $firstPayment = $this->withHeader('Idempotency-Key', 'finance-payment-key-001')
            ->postJson("/api/v1/organizations/{$organization->id}/payments", [
                'invoice_id' => $invoiceId,
                'payment_method' => 'cash',
                'amount_minor' => 1000,
            ])->assertCreated()->assertJsonPath('data.status', 'pending');
        $firstPaymentId = $firstPayment->json('data.id');

        $this->postJson("/api/v1/organizations/{$organization->id}/payments/{$firstPaymentId}/confirm", [
            'notes' => 'First cash installment received.',
        ])->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 'partially_paid',
            'paid_total_minor' => 1000,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'partial']);

        $secondPayment = $this->withHeader('Idempotency-Key', 'finance-payment-key-002')
            ->postJson("/api/v1/organizations/{$organization->id}/payments", [
                'invoice_id' => $invoiceId,
                'payment_method' => 'bank_transfer',
                'amount_minor' => 1500,
            ])->assertCreated();
        $secondPaymentId = $secondPayment->json('data.id');

        $this->postJson("/api/v1/organizations/{$organization->id}/payments/{$secondPaymentId}/confirm", [
            'reference' => 'BANK-REF-2026-0001',
            'notes' => 'Bank transfer verified against statement.',
        ])->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 'paid',
            'paid_total_minor' => 2500,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'payment.confirmed_offline',
            'subject_id' => (string) $secondPaymentId,
        ]);
    }

    public function test_membership_in_another_organization_does_not_grant_access(): void
    {
        [$user] = $this->createCommerceFixture();
        $otherOrganization = Organization::query()->create([
            'name' => 'Other company',
            'slug' => 'other-company',
            'kind' => 'business',
            'commerce_mode' => 'marketplace',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$otherOrganization->id}/products")
            ->assertForbidden();
    }

    private function createCommerceFixture(): array
    {
        $user = User::query()->create([
            'name' => 'Test Owner',
            'email' => 'owner@example.test',
            'password' => 'testing-password-123',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'Test Commerce',
            'slug' => 'test-commerce',
            'kind' => 'business',
            'commerce_mode' => 'marketplace',
            'is_active' => true,
        ]);

        $membership = Membership::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'branch_id' => null,
            'status' => 'active',
            'is_default' => true,
        ]);

        $permissions = collect([
            'catalog.products.view',
            'catalog.products.manage',
            'inventory.view',
            'inventory.adjust',
            'orders.view',
            'orders.manage',
            'shipments.manage',
            'finance.view',
            'finance.manage',
        ])->map(fn (string $slug) => Permission::query()->create([
            'name' => str($slug)->replace('.', ' ')->headline()->toString(),
            'slug' => $slug,
        ]));

        $role = Role::query()->create([
            'name' => 'Organization Owner',
            'slug' => 'test-organization-owner',
            'description' => 'Test role',
            'is_system' => true,
        ]);
        $role->permissions()->sync($permissions->pluck('id')->all());
        $membership->roles()->sync([$role->id]);

        $store = Store::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Test Store',
            'slug' => 'test-store',
            'mode' => 'marketplace',
            'currency' => 'YER',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'organization_id' => $organization->id,
            'store_id' => $store->id,
            'name' => 'Test Item',
            'slug' => 'test-item',
            'status' => 'published',
            'product_type' => 'physical',
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'TEST-SKU-001',
            'name' => 'Default',
            'is_active' => true,
        ]);

        $priceList = PriceList::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Retail',
            'code' => 'RETAIL',
            'currency' => 'YER',
            'customer_type' => 'all',
            'is_default' => true,
            'is_active' => true,
        ]);
        PriceListItem::query()->create([
            'price_list_id' => $priceList->id,
            'product_variant_id' => $variant->id,
            'unit_price_minor' => 1250,
            'minimum_quantity' => 1,
        ]);

        $warehouse = Warehouse::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'kind' => 'warehouse',
            'is_active' => true,
        ]);
        InventoryBalance::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'on_hand' => 10,
            'reserved' => 0,
            'reorder_level' => 0,
        ]);

        return [$user, $organization, $store, $priceList, $variant, $warehouse];
    }
}
