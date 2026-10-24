# Development notes

## Why modular monolith first?

It keeps transactions and local development straightforward while enforcing domain boundaries. Extract services only when scaling, team ownership, or reliability needs justify the operational cost.

## First vertical slice

1. Authenticate a user.
2. Select an organization and branch the user is authorized to access.
3. Create or view a product listing.
4. Create an order and snapshot prices and addresses.
5. Create fulfillment and shipment records.
6. Record delivery outcome and associated financial events.
7. Make relevant information available to the assistant through permissioned tools.

## API conventions

- Prefix endpoints with `/api/v1`.
- Use Form Requests for input validation.
- Use Policies/services for authorization and state transitions.
- Return consistent JSON error responses.
- Add idempotency to retried operations that create orders, payments, refunds, or payouts.
- Use transactions around state changes that must succeed or fail together.
