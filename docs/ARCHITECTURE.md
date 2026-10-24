# Architecture

## Application shape

Start with a **modular monolith**. Keep domains separated in code and database design; extract a module into a separate service only when operational evidence justifies it.

## Domains

- **Identity & Access:** users, organizations, branches, memberships, roles, permissions, sessions, audit.
- **Commerce:** stores, catalog, products, variants, price lists, promotions, quotes, orders.
- **Inventory:** warehouses, stock balances, immutable stock movements, reservations.
- **Logistics:** fulfillments, packages, shipments, trips, delivery tasks, proof of delivery, claims.
- **Finance:** invoices, payments, refunds, settlements, ledger integration.
- **Marketing:** campaigns, creatives, channels, approval, tracking, attribution.
- **AI Platform:** context resolution, model gateway, prompt/policy registry, tool registry, approvals, usage and evaluations.
- **Integrations:** maps, messaging, payment providers, ad channels, webhooks.
- **Analytics:** reporting projections and operational metrics.

## Domain invariants

- User identity is separate from an organization membership.
- A membership may have roles scoped to an organization or branch.
- Organization relationships describe commercial cooperation; they do not automatically grant access to each other's records.
- Catalog-only organizations can publish listings and receive inquiries/quote requests without platform checkout.
- Marketplace organizations can enable cart/checkout, inventory, order management, invoices, and returns by policy.
- B2B and B2C are supported through buyer type, price lists, minimum quantities, payment terms, and contract rules.
- An order can produce multiple fulfillments and shipments.
- Confirmed order lines preserve product name, SKU, unit price, discount, tax, and relevant terms as snapshots.

## Order lifecycle

Listing or catalog → cart or quote request → validate price, availability, address, shipping, and terms → confirm and snapshot → payment/COD/collection according to policy → reserve and pick stock → create fulfillment(s) and shipment(s) → dispatch and delivery evidence → reconciliation and settlement.

## AI execution model

AI is an assistant, not an authorization boundary. Each tool call must resolve the current user, active organization/branch, allowed records, and permission. Sensitive operations use approval policies. Store a run record, tool name, sanitized input/output, decision, approver, and outcome. Never expose raw database credentials or permit arbitrary SQL.
