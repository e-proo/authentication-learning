# Order-to-delivery API walkthrough

All examples use JSON. Except for the public health/about endpoints, endpoints require a Sanctum bearer token.

    Authorization: Bearer YOUR_SANCTUM_TOKEN
    Accept: application/json
    Content-Type: application/json

## 1. Create a store

POST /api/v1/organizations/{organization}/stores

    {
      "name": "Main Store",
      "slug": "main-store",
      "mode": "marketplace",
      "currency": "YER"
    }

The organization creator receives the initial organization-owner role. Store and product operations require catalog.products.manage.

## 2. Create and publish a product

POST /api/v1/organizations/{organization}/products

    {
      "store_id": 1,
      "name": "Example Item",
      "slug": "example-item",
      "description": "Product details",
      "product_type": "physical"
    }

Then add a SKU variant:

POST /api/v1/organizations/{organization}/products/{product}/variants

    {
      "sku": "EXAMPLE-001",
      "name": "Default variant",
      "attributes": {"color": "black"},
      "weight_grams": 300
    }

Publish it with POST /api/v1/organizations/{organization}/products/{product}/publish.

## 3. Set price and initialize warehouse stock

Create a price list:

POST /api/v1/organizations/{organization}/price-lists

    {
      "name": "Retail",
      "code": "RETAIL",
      "currency": "YER",
      "customer_type": "B2C",
      "is_default": true
    }

Add an SKU price:

POST /api/v1/organizations/{organization}/price-lists/{priceList}/items

    {
      "product_variant_id": 1,
      "unit_price_minor": 1250,
      "minimum_quantity": 1
    }

Money is represented in integer minor units to avoid floating-point arithmetic. Configure the currency's unit convention consistently in the application and any connected accounting/payment provider.

Create a warehouse, then initialize stock using the stock-adjustment endpoint with movement_type: opening_balance and a positive quantity_delta. The API records an inventory movement for every adjustment.

## 4. Place an order

POST /api/v1/organizations/{organization}/orders

Add an Idempotency-Key header for safe retries. Example:

    {
      "store_id": 1,
      "price_list_id": 1,
      "items": [
        {
          "product_variant_id": 1,
          "warehouse_id": 1,
          "quantity": 2
        }
      ],
      "shipping_address": {
        "name": "Test Buyer",
        "phone": "+967700000000",
        "country_code": "YE",
        "city": "Sanaa",
        "address_line1": "Example street"
      },
      "customer_note": "Call on arrival"
    }

The server gets prices from the selected price list. Client-supplied totals are not accepted. The order stores item and address snapshots and reserves inventory in a database transaction.

## 5. Fulfill, pack, and ship

Create fulfillments:

POST /api/v1/organizations/{organization}/orders/{order}/fulfillments

Each warehouse used by an order receives a fulfillment in this first version. Pack a fulfillment:

POST /api/v1/organizations/{organization}/fulfillments/{fulfillment}/pack

Create its shipment:

POST /api/v1/organizations/{organization}/fulfillments/{fulfillment}/shipments

    {
      "carrier_type": "internal",
      "carrier_name": "Company Fleet",
      "tracking_number": "TRACK-0001"
    }

Record each transition:

POST /api/v1/organizations/{organization}/shipments/{shipment}/events

    {
      "status": "picked_up",
      "location": "Warehouse dispatch gate",
      "notes": "Picked up by courier"
    }

Continue with in_transit, out_for_delivery if needed, and finally delivered. Valid transitions are enforced server-side. At picked_up, on-hand stock is reduced, the reservation is consumed, and a stock movement is appended in the same transaction. At delivered, the fulfillment is completed; the order becomes delivered once all its fulfillments are complete.

## 6. Issue an invoice and record payment

Issue at most one invoice for an order:

POST /api/v1/organizations/{organization}/orders/{order}/invoice

Record a payment attempt (this does not itself prove collection):

POST /api/v1/organizations/{organization}/payments

Required header: Idempotency-Key.

    {
      "invoice_id": 1,
      "payment_method": "cash",
      "amount_minor": 1000,
      "reference": "CASH-RECEIPT-001"
    }

Confirm an offline payment:

POST /api/v1/organizations/{organization}/payments/{payment}/confirm

    {
      "notes": "Cash counted and receipt verified"
    }

Bank transfer confirmation requires a reference. Cash-on-delivery confirmation is allowed only after the order has been marked delivered. Online payment is kept pending and cannot be manually confirmed until a signed and verified provider-webhook integration is implemented.



## 7. Request and receive a return

A return can be requested only after the order is marked delivered. The service checks the delivered shipment-item quantity and accounts for other open and completed returns so the total requested/received quantity cannot exceed what was delivered.

Request a return:

POST /api/v1/organizations/{organization}/orders/{order}/returns

    {
      "reason": "damaged",
      "notes": "Packaging was damaged on arrival",
      "items": [
        {
          "order_item_id": 1,
          "quantity": 1,
          "reason": "Damaged packaging"
        }
      ]
    }

Approve or reject the request using the return case ID. Only approved cases may be received. Each return line records one receipt with a disposition:

- restock: requires inventory.adjust permission and an active warehouse in the same organization. This increases the on-hand balance and appends a return_restock inventory movement in the transaction.
- damaged: records receipt without making the units sellable.
- discard: records disposal without adding sellable stock.

When every return line has a receipt, the return moves to received. Close it with the close endpoint. The current version creates at most one receipt per return line and does not automate refunds or reverse-pickup transport.

## Current limitations

- Public customer checkout, cart sessions, guest customers, tax/shipping rate calculators, and payment provider webhooks are not included yet.
- Shipping/tax totals are currently zero; configure provider-verified rates before using these endpoints commercially.
- One order item is allocated to one warehouse; partial item allocation and multiple packages per fulfillment are future work.
- Sanitized audit records are created for order, stock, fulfillment, shipment, invoice, and payment actions.
- Automated refunds, failed-delivery reattempt policies, proof-of-delivery media, and settlement accounting are not implemented.
- Test the workflow against PostgreSQL with php artisan test before deployment. Do not use production money or inventory until database migrations and integration tests pass.


See [Fleet, trips, and delivery tasks](TRANSPORT_AND_DELIVERY_TASKS.md) for the internal fleet execution workflow and its current limitations.
