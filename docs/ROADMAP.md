# Delivery roadmap

## Phase 1 — Application foundation (initial implementation)
- [x] Laravel application scaffold and API health endpoints
- [x] Authentication endpoint foundation and Sanctum tokens
- [x] Organizations, branches, memberships, roles, permissions
- [ ] Branch-scoped authorization and audit-log coverage for all sensitive actions

## Phase 2 — Commerce and inventory
- [x] Stores, products, variants, and product publishing
- [x] Price lists and per-SKU price snapshots
- [x] Warehouses, inventory balances, stock movements, and reservations
- [ ] Categories, media, promotions, and B2B contract terms

## Phase 3 — Order-to-delivery vertical slice
- [x] Order creation with address and price snapshots
- [x] Idempotent order creation and stock reservation in a transaction
- [x] Fulfillment grouping by warehouse
- [x] Shipment lifecycle events and stock reconciliation at carrier pickup
- [x] Delivery completion and order status history
- [ ] Partial fulfillment quantities, multiple packages per fulfillment, proof-of-delivery media, returns, and failed-delivery recovery

## Phase 4 — Finance
- [x] Invoice and invoice-item snapshots per order
- [x] Idempotent pending payment records and offline confirmation for cash/bank transfer/COD rules
- [x] Partial-payment allocation and invoice/order payment-status updates
- [ ] Verified provider webhooks, replay protection, payment failure/reversal, refunds, and settlement
- [ ] Immutable double-entry ledger, reconciliation imports, merchant/courier payouts, and approval separation of duties

## Phase 5 — Marketing
- [ ] Campaign planning, budgets, creatives, and approval workflow
- [ ] External channel adapters with explicit API/access limitations
- [ ] Tracking, attribution, and measured-vs-estimated reporting

## Phase 6 — AI platform
- [ ] Context resolver for active user, organization, branch, and permissions
- [ ] Tool registry, policy registry, approval workflow, and run history
- [ ] Model gateway, cost/usage controls, evaluation sets, and monitoring

## Phase 7 — UI and production readiness
- [ ] Vue 3 + TypeScript workspaces and role-aware navigation
- [ ] Arabic RTL and English LTR, accessibility, and responsive layout
- [ ] API/e2e tests, CI quality gates, structured logs, metrics, tracing
- [ ] Production deployment guide, backups, restore drills, and security review
