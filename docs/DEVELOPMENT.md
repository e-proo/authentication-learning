# Development notes

## Current implemented vertical slice

The API now supports store/product setup, SKU pricing, warehouse stock adjustments, order placement, stock reservation, warehouse-specific fulfillment, packing, shipment creation, carrier status events, and delivery completion.

## First vertical slice sequence

1. Authenticate a user and create an organization.
2. Create a store, product, and SKU variant; publish it.
3. Create a price list and set the variant price in integer minor units.
4. Create a warehouse and initialize stock.
5. Place an order using the server-side price list; the service validates tenant/store/SKU/warehouse scope and locks inventory rows.
6. Reserve stock and store product/price/address snapshots.
7. Create one fulfillment for each warehouse used by the order, then pack each fulfillment.
8. Create a shipment and record carrier status events.
9. At `picked_up`, atomically decrease on-hand stock, consume the reservation, and append an inventory movement.
10. At `delivered`, complete the fulfillment and mark the order delivered once all fulfillments are complete.

## API conventions

- Prefix endpoints with `/api/v1`.
- Use Form Requests or controller validation for all untrusted input.
- Use OrganizationAccess to enforce active organization-wide membership and permissions.
- Use domain services for state transitions and inventory changes.
- Return consistent JSON errors; invalid state transitions produce conflict responses.
- Require `Idempotency-Key` for reliable retries on order creation and inventory adjustments.
- Keep money as integer minor units; never accept client-calculated order totals.
- Use transactions around state changes that must succeed or fail together.

## Explicit first-slice limitations

- Orders are created from authenticated organization users with `orders.manage`. Public customer checkout and buyer/storefront security are a separate phase.
- One order item is allocated to one warehouse and one fulfillment in this initial iteration. Partial fulfillment and split packages are future work.
- Shipping cost and tax are currently zero because provider-backed rate/tax calculation is not yet implemented.
- Payment status remains pending; payment capture, COD collection, invoices, refunds, and settlement are not implemented.
- Returns and inventory restocking from a return receipt are not implemented.
