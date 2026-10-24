# Logistics & Commerce Platform

A modular platform for logistics, multi-organization commerce, inventory, marketing, finance, and permissioned AI workflows.

> **Status:** the API foundation and first order-to-delivery slice are committed. Runtime verification is still required before production use. Payment-provider webhooks, double-entry ledger, refunds/settlement, public customer checkout, returns, marketing integrations, AI tools, and the Vue UI are not implemented yet.

## Architecture

- **Backend:** Laravel 13 / PHP 8.3+
- **Database:** PostgreSQL 16+
- **API:** REST API under `/api/v1`
- **Authentication:** Laravel Sanctum bearer tokens
- **Architecture:** modular monolith with explicit domain boundaries
- **Frontend:** Vue 3 + TypeScript planned as a separate implementation phase

## Implemented API areas

- User registration, login, logout, current-user endpoint
- Organization creation and organization-wide membership roles
- Store and product management, product variants, publishing
- Price lists with per-SKU prices and minimum quantities
- Warehouse creation and auditable stock adjustments
- Order creation from server-side price-list values, address/product/price snapshots, and stock reservation
- Idempotent order creation through the `Idempotency-Key` header
- Warehouse-split fulfillment, packing, shipment creation, shipment tracking events
- Stock deduction and reservation consumption when a shipment is picked up by the carrier
- Delivery completion and order status history
- Invoice snapshots and offline payment records with partial-payment reconciliation; COD confirmation is blocked until delivery
- Initial PostgreSQL-backed feature tests and GitHub Actions CI

## Core principles

1. A user, organization, branch, membership, role, permission, and commercial relationship are distinct concepts.
2. Organization-wide API routes require an active organization-wide membership; branch-only access must be implemented through branch-scoped routes.
3. Tenant access is enforced on the server. IDs supplied by clients are always resolved within the authorized organization's scope.
4. A confirmed order preserves price, product name, SKU, currency, and delivery address snapshots.
5. Available stock is `on_hand - reserved`; reservations do not reduce physical stock until carrier pickup.
6. Shipment pickup reduces physical stock, releases the reservation, and appends a stock movement in one transaction.
7. AI tools will use registered, permission-checked application actions; they will not get direct database access.
8. Sensitive state transitions have explicit validation, business history records, and sanitized audit-log records written in the same transaction.

## Local development

Requirements: PHP 8.3+, Composer 2, PostgreSQL 16+, and the PHP extensions required by Laravel/Sanctum.

1. Create a PostgreSQL database.
2. Copy `.env.example` to `.env` and configure database credentials.
3. Install dependencies: `composer install`
4. Generate an application key: `php artisan key:generate`
5. Run migrations: `php artisan migrate`
6. Start the API: `php artisan serve`
7. Run tests: `php artisan test`

Health check: `GET /api/v1/health`.

## First end-to-end workflow

1. Register and sign in to obtain a Sanctum token.
2. Create an organization. The creator receives the initial organization-owner role.
3. Create a store, product, and SKU variant; publish the product.
4. Create a price list and add a price for the SKU.
5. Create a warehouse and apply an `opening_balance` inventory adjustment.
6. Create an order with the chosen store, price list, SKU, warehouse, quantity, and delivery address.
7. Create fulfillments, pack each fulfillment, and create a shipment for each packed fulfillment.
8. Record shipment events in order: `picked_up`, `in_transit`, then `delivered`.

Use the bearer token in the `Authorization: Bearer <token>` header. See [Order-to-delivery API notes](docs/ORDER_TO_DELIVERY.md) and [Finance foundation notes](docs/FINANCE.md) for request examples and state rules.

## Build plan

- [x] Laravel application scaffold and API health endpoints
- [x] Authentication, organizations, memberships, roles, and permissions foundation
- [x] Catalog, variants, price lists, inventory balances, movements, and reservations
- [x] Orders, warehouse-based fulfillments, shipments, delivery events, tenant-scope and idempotency tests
- [ ] Branch-scoped workspaces and organization relationship approvals
- [x] Invoice snapshots and offline payment confirmation with partial-payment reconciliation
- [ ] Double-entry ledger, verified payment-provider webhooks, refunds, payouts, and settlements
- [ ] Returns, failed-delivery recovery, and reverse logistics
- [ ] Campaigns, approved external ad channel connectors, tracking, and attribution
- [ ] Permissioned AI tool registry, approvals, usage/cost controls, and evaluations
- [ ] Vue application, Arabic RTL/English LTR, end-to-end browser tests, observability, deployment

## Security

Never commit `.env`, access tokens, passwords, private keys, or production data. Review [Security Baseline](docs/SECURITY.md). This repository is not yet production-ready; review migrations, policies, rate limits, operational monitoring, backups, and tests before a production deployment.
