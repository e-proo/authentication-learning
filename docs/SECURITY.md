# Security baseline

- Keep secrets in environment variables or a managed secret store; never commit them.
- Enforce organization and branch scoping in server-side policies and query services.
- Use deny-by-default authorization and least-privilege roles.
- Validate all input with Form Requests; validate state transitions in domain services.
- Record sensitive operations in append-oriented audit records.
- Use idempotency keys for externally retried money/order operations.
- Verify webhook signatures and prevent replay where supported.
- Keep payment card data out of this platform unless a compliant provider/tokenization flow requires it.
- AI tools must use registered application services, never arbitrary SQL, shell, or unrestricted URLs.
- Require explicit approval for configured sensitive actions such as refunds, price changes, publishing campaigns, or payouts.
- Apply rate limits to authentication, all authenticated API endpoints, public quote forms, webhooks, and AI endpoints.
- Configure CORS to allow only explicitly listed frontend origins; bearer-token APIs should not require cross-origin cookies by default.
- Restrict user-supplied metadata to documented keys and never store credentials or payment secrets in metadata.
- Use HTTPS in deployed environments and disable debug output in production.
- Back up PostgreSQL and test restoration regularly.


## Order-to-delivery controls

- Organization-level endpoints require an active membership with no branch scope; branch-only membership is not treated as organization-wide permission.
- Order creation reads price from the server-owned price list and ignores client-supplied totals.
- Stock availability is checked while locking the balance row; the reservation and order records are committed in one transaction.
- An idempotency key may be reused only for the same normalized request. Reusing it for another payload is rejected.
- Fulfillment and shipment state transitions are validated; physical stock is deducted at carrier pickup, and the inventory movement is written in the same transaction.
- Delivery-address, product, SKU, unit-price, and currency snapshots are preserved on the order.

This is a foundation rather than a complete security certification. Add branch-scoped policies, rate limiting on all state-changing endpoints, audit coverage, webhook verification, and external penetration testing before production.


## Audit trail

The shared audit logger records organization creation, order create/cancel, inventory adjustments, fulfillment creation/packing, shipment creation/state transitions, invoice issuance, and payment record/confirmation. Records include actor, organization, subject, request ID, IP/user-agent when available, and narrowly scoped action metadata. The logger drops metadata keys that resemble credentials/secrets; callers should still allowlist user-supplied metadata and avoid logging full addresses or free-form sensitive details.
