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

        $returnResponse = $this->postJson(
            "/api/v1/organizations/{$organization->id}/orders/{$orderId}/returns",
            [
                'reason' => 'damaged',
                'notes' => 'Return workflow integration test.',
                'items' => [[
                    'order_item_id' => $orderItemId,
                    'quantity' => 1,
                    'reason' => 'Damaged packaging',
                ]],
            ],
        )->assertCreated()->assertJsonPath('data.status', 'requested');

        $returnCaseId = $returnResponse->json('data.id');
        $returnItemId = $returnResponse->json('data.items.0.id');

        $this->postJson(
            "/api/v1/organizations/{$organization->id}/returns/{$returnCaseId}/approve",
            ['notes' => 'Approved after inspection.'],
        )->assertOk()->assertJsonPath('data.status', 'approved');

        $this->postJson(
            "/api/v1/organizations/{$organization->id}/returns/{$returnCaseId}/items/{$returnItemId}/receive",
            [
                'quantity_received' => 1,
                'disposition' => 'restock',
                'warehouse_id' => $warehouse->id,
                'notes' => 'Item inspected and approved for restock.',
            ],
        )->assertCreated()->assertJsonPath('data.disposition', 'restock');

        $this->assertDatabaseHas('return_cases', [
            'id' => $returnCaseId,
            'status' => 'received',
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'on_hand' => 9,
            'reserved' => 0,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'movement_type' => 'return_restock',
            'quantity_delta' => 1,
        ]);

        $this->postJson(
            "/api/v1/organizations/{$organization->id}/returns/{$returnCaseId}/close",
            ['notes' => 'Return completed.'],
        )->assertOk()->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'return.closed',
            'subject_id' => (string) $returnCaseId,
        ]);
    }

    public function test_last_mile_delivery_task_drives_shipment_to_delivery_with_evidence(): void
    {
        [$user, $organization, $store, $priceList, $variant, $warehouse] = $this->createCommerceFixture();

        $order = $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/organizations/{$organization->id}/orders",
            [
                'store_id' => $store->id,
                'price_list_id' => $priceList->id,
                'items' => [[
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => 2,
                ]],
                'shipping_address' => [
                    'name' => 'Task Recipient',
                    'phone' => '+967700000002',
                    'country_code' => 'YE',
                    'city' => 'Sanaa',
                    'address_line1' => 'Delivery task street',
                ],
            ],
        )->assertCreated();
        $orderId = $order->json('data.id');

        $this->postJson("/api/v1/organizations/{$organization->id}/orders/{$orderId}/fulfillments")
            ->assertCreated();
        $fulfillmentId = Fulfillment::query()
            ->where('organization_id', $organization->id)
            ->where('order_id', $orderId)
            ->value('id');

        $this->postJson("/api/v1/organizations/{$organization->id}/fulfillments/{$fulfillmentId}/pack")
            ->assertOk();

        $shipment = $this->postJson(
            "/api/v1/organizations/{$organization->id}/fulfillments/{$fulfillmentId}/shipments",
            ['carrier_type' => 'internal', 'carrier_name' => 'Test Fleet'],
        )->assertCreated();
        $shipmentId = $shipment->json('data.id');

        $driver = $this->postJson("/api/v1/organizations/{$organization->id}/drivers", [
            'driver_code' => 'DRIVER-TEST-01',
            'display_name' => 'Test Driver',
            'phone' => '+967700000003',
            'driver_type' => 'employee',
        ])->assertCreated();
        $driverId = $driver->json('data.id');

        $vehicle = $this->postJson("/api/v1/organizations/{$organization->id}/vehicles", [
            'vehicle_code' => 'VAN-TEST-01',
            'registration_plate' => 'TEST-PLATE-01',
            'vehicle_type' => 'van',
            'capacity_kg' => 900,
        ])->assertCreated();
        $vehicleId = $vehicle->json('data.id');

        $trip = $this->postJson("/api/v1/organizations/{$organization->id}/transport-trips", [
            'trip_type' => 'last_mile',
            'origin' => [
                'name' => 'Main Warehouse',
                'city' => 'Sanaa',
                'country_code' => 'YE',
                'address_line1' => 'Warehouse street',
            ],
            'destination' => [
                'name' => 'Customer',
                'city' => 'Sanaa',
                'country_code' => 'YE',
                'address_line1' => 'Delivery task street',
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'planned');
        $tripId = $trip->json('data.id');

        $this->postJson("/api/v1/organizations/{$organization->id}/transport-trips/{$tripId}/assign", [
            'driver_id' => $driverId,
            'vehicle_id' => $vehicleId,
        ])->assertOk()->assertJsonPath('data.status', 'assigned');

        $task = $this->postJson("/api/v1/organizations/{$organization->id}/delivery-tasks", [
            'task_type' => 'last_mile',
            'shipment_id' => $shipmentId,
            'location' => [
                'name' => 'Customer',
                'city' => 'Sanaa',
                'country_code' => 'YE',
                'address_line1' => 'Delivery task street',
            ],
            'recipient_name' => 'Task Recipient',
            'recipient_phone' => '+967700000002',
        ])->assertCreated()->assertJsonPath('data.status', 'queued');
        $taskId = $task->json('data.id');

        $this->postJson("/api/v1/organizations/{$organization->id}/delivery-tasks/{$taskId}/assign", [
            'driver_id' => $driverId,
            'transport_trip_id' => $tripId,
            'sequence_number' => 1,
        ])->assertOk()->assertJsonPath('data.status', 'assigned');

        $this->postJson("/api/v1/organizations/{$organization->id}/transport-trips/{$tripId}/depart", [])
            ->assertOk()->assertJsonPath('data.status', 'departed');

        $this->postJson("/api/v1/organizations/{$organization->id}/delivery-tasks/{$taskId}/events", [
            'status' => 'en_route',
            'location' => 'Dispatch gate',
        ])->assertOk()->assertJsonPath('data.status', 'en_route');

        $this->assertDatabaseHas('shipments', ['id' => $shipmentId, 'status' => 'out_for_delivery']);
        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'on_hand' => 8,
            'reserved' => 0,
        ]);

        $this->postJson("/api/v1/organizations/{$organization->id}/delivery-tasks/{$taskId}/events", [
            'status' => 'arrived',
            'location' => 'Customer gate',
        ])->assertOk()->assertJsonPath('data.status', 'arrived');

        $this->postJson("/api/v1/organizations/{$organization->id}/delivery-tasks/{$taskId}/events", [
            'status' => 'completed',
            'proof_reference' => 'POD-TEST-0001',
            'recipient_name' => 'Task Recipient',
            'recipient_phone' => '+967700000002',
            'location' => 'Customer address',
            'notes' => 'Delivery completed.',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('shipments', ['id' => $shipmentId, 'status' => 'delivered']);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'delivered']);

        $this->postJson("/api/v1/organizations/{$organization->id}/transport-trips/{$tripId}/complete", [
            'notes' => 'All assigned tasks completed.',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'delivery_task.completed',
            'subject_id' => (string) $taskId,
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

    public function test_campaign_requires_approval_before_internal_publication_and_tracks_metrics(): void
    {
        [$user, $organization, $store] = $this->createCommerceFixture();

        $campaign = $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/organizations/{$organization->id}/campaigns",
            [
                'store_id' => $store->id,
                'name' => 'Internal Store Launch',
                'description' => 'Test campaign for the workflow.',
                'objective' => 'sales',
                'currency' => 'YER',
                'budget_minor' => 50000,
                'audience_snapshot' => [
                    'buyer_type' => 'B2C',
                    'geographic_regions' => ['Sanaa'],
                    'segment_name' => 'Retail customers',
                ],
            ],
        )->assertCreated()->assertJsonPath('data.status', 'draft');
        $campaignId = $campaign->json('data.id');

        $channel = $this->postJson(
            "/api/v1/organizations/{$organization->id}/campaigns/{$campaignId}/channels",
            [
                'channel_key' => 'internal',
                'publish_mode' => 'internal',
                'settings_snapshot' => ['tracking_source' => 'wasl-test'],
            ],
        )->assertCreated()->assertJsonPath('data.status', 'draft');
        $channelId = $channel->json('data.id');

        $creative = $this->postJson(
            "/api/v1/organizations/{$organization->id}/campaigns/{$campaignId}/creatives",
            [
                'campaign_channel_id' => $channelId,
                'creative_type' => 'text',
                'name' => 'Launch text',
                'headline' => 'Shop local',
                'primary_text' => 'Discover products from local businesses.',
                'call_to_action' => 'shop_now',
                'destination_url' => 'https://example.test/store',
            ],
        )->assertCreated()->assertJsonPath('data.status', 'draft');
        $creativeId = $creative->json('data.id');

        $this->postJson("/api/v1/organizations/{$organization->id}/campaigns/{$campaignId}/submit", [
            'notes' => 'Ready for marketing review.',
        ])->assertOk()->assertJsonPath('data.status', 'in_review');

        $this->postJson(
            "/api/v1/organizations/{$organization->id}/campaigns/{$campaignId}/creatives/{$creativeId}/submit",
            ['notes' => 'Creative submitted.'],
        )->assertOk()->assertJsonPath('data.status', 'in_review');

        $this->postJson(
            "/api/v1/organizations/{$organization->id}/campaigns/{$campaignId}/creatives/{$creativeId}/approve",
            ['notes' => 'Copy reviewed.'],
        )->assertOk()->assertJsonPath('data.status', 'approved');

        $this->postJson("/api/v1/organizations/{$organization->id}/campaigns/{$campaignId}/approve", [
            'notes' => 'Approved for internal publication.',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->postJson(
            "/api/v1/organizations/{$organization->id}/campaigns/{$campaignId}/channels/{$channelId}/publish",
            ['notes' => 'Published to internal storefront.'],
        )->assertOk()->assertJsonPath('data.status', 'published');

        $metricDate = now()->subDay()->toDateString();
        $this->postJson(
            "/api/v1/organizations/{$organization->id}/campaigns/{$campaignId}/channels/{$channelId}/metrics",
            [
                'metric_date' => $metricDate,
                'impressions' => 100,
                'clicks' => 12,
                'conversions' => 3,
                'spend_minor' => 500,
                'attributed_revenue_minor' => 2500,
                'source' => 'manual',
            ],
        )->assertOk()->assertJsonPath('data.impressions', 100);

        $this->getJson("/api/v1/organizations/{$organization->id}/campaigns/{$campaignId}/metrics")
            ->assertOk()
            ->assertJsonPath('totals.impressions', 100)
            ->assertJsonPath('totals.clicks', 12)
            ->assertJsonPath('totals.conversions', 3)
            ->assertJsonPath('totals.spend_minor', 500)
            ->assertJsonPath('totals.attributed_revenue_minor', 2500);

        $this->assertDatabaseHas('campaigns', ['id' => $campaignId, 'status' => 'active']);
        $this->assertDatabaseHas('campaign_channels', ['id' => $channelId, 'status' => 'published']);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'campaign.internal_channel_published',
            'subject_id' => (string) $channelId,
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
            'returns.view',
            'returns.manage',
            'transport.manage',
            'delivery_tasks.view',
            'delivery_tasks.manage',
            'marketing.view',
            'marketing.manage',
            'marketing.approve',
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
