# Delivery roadmap

## Phase 1 — Foundation
- Laravel application scaffold and health endpoints
- Authentication and API token/session strategy
- Organizations, branches, memberships, roles, permissions
- Tenant isolation tests and audit events

## Phase 2 — Commerce foundation
- Stores, products, variants, price lists, category/media
- Catalog-only and marketplace modes per organization
- Inventory balances, movements, and reservations

## Phase 3 — Order-to-delivery vertical slice
- Quote/cart and order creation with immutable snapshots
- Fulfillment splitting and shipment lifecycle
- Delivery tasks, status history, proof of delivery, returns

## Phase 4 — Finance
- Invoices, payment records, refunds, settlements
- Idempotency, reconciliation, and audit controls

## Phase 5 — Marketing
- Campaign planning and creatives
- Approval workflow and channel adapters
- Tracking and attribution with clear measured/estimated distinctions

## Phase 6 — AI platform
- Context-aware assistant across modules
- Tool registry, permission checks, approvals, run history, cost controls
- Evaluations, safety rules, monitoring

## Phase 7 — User interface and production
- Vue 3 + TypeScript workspaces and role-aware navigation
- Accessibility, localization, Arabic RTL and English LTR
- CI, integration/e2e tests, deployment, observability, backup/restore drills
