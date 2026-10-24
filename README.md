# Logistics & Commerce Platform

A modular logistics, multi-organization commerce, inventory, transport, fleet, branch-network, finance, marketing, and permissioned AI platform.

> **Project status:** this repository contains a Laravel API foundation and initial order-to-delivery workflow. The frontend is not yet a complete product. Runtime verification is still required before production use. The driver mobile application, full branch/office/hub operations, WMS workflows, dispatch optimization, fleet maintenance, full TMS/linehaul, customer/merchant portals, complete cash reconciliation, external integrations, and AI execution workflows are not yet delivered end to end.

## Important product architecture decision

This is **not** intended to be one generic admin dashboard that contains every logistics role as a small tab. The target is a portfolio of connected products with separate workspaces:

- Dedicated Driver Companion mobile app
- Branch, Office, Depot and Hub Operations
- Warehouse Management System (WMS)
- Dispatch and Route Planning / Last-Mile Control Tower
- Fleet Management and Maintenance
- Transport Management (TMS) and Linehaul
- Shipment Visibility and Customer Tracking
- Merchant/Shipper Portal and Customer Portal
- Finance, Cash Desk, COD and Settlement
- Customer Service and Claims
- Organization / Platform Administration
- Marketing, AI and Automation Operations
- Executive Network Control Tower

Apps share identity, domain APIs, canonical shipment/order events and authorization; they do not share one universal navigation or pretend that a database table equals a completed application.

## Product research and specifications

- [Product benchmark and capability catalogue](docs/PRODUCT_BENCHMARK.md) — researched representative logistics, driver, fleet, WMS, TMS, shipping API, visibility and forwarding products and expands their public capability categories into the candidate product scope.
- [Applications and workspaces architecture](docs/APPLICATIONS_AND_WORKSPACES.md) — defines separate products, audiences, boundaries and cross-application invariants.
- [Driver Companion application specification](docs/DRIVER_COMPANION_SPEC.md) — dedicated mobile app scope including offline workflow, scans, proof, COD, cash handover, vehicle checks, returns and release acceptance gates.
- [Branch, Office, Depot and Hub specification](docs/BRANCH_OFFICE_HUB_SPEC.md) — counter booking, receiving, sortation, manifests, transfers, customer pickup, cash desk, investigation and reporting.
- [WMS, TMS and Fleet specifications](docs/WMS_TMS_FLEET_SPEC.md) — independent warehouse, transportation/linehaul, and fleet/workshop products.
- [Branch & Parcel Office detailed specification](docs/BRANCH_OFFICE_OPERATIONS_SPEC.md) — additional acceptance tests and counter/custody/cash workflows.
- [Application portfolio preview](preview.html) — directory of separate apps/workspaces, not an operational dashboard.
- [Standalone Driver Companion preview](driver-app-preview.html) — interactive mobile-first prototype with task, route, COD, inspection, return and offline/sync mock flows; not connected to the backend.
- [Delivery roadmap](docs/ROADMAP.md) — implementation stages with clear status labels.

The benchmark covers representative products, not literally every logistics application in the market. Feature research is a scope baseline; interviews with actual drivers, branch teams, dispatchers, warehouse operators, cashiers, finance, merchants and customers remain necessary. Specifications are **not** claims that these features already exist in code.

## Current technical architecture

- **Backend:** Laravel 13 / PHP 8.3+
- **Database:** PostgreSQL 16+
- **API:** REST under `/api/v1`
- **Authentication:** Laravel Sanctum bearer tokens
- **Backend pattern:** modular monolith with explicit domain boundaries
- **Frontend:** the current `preview.html` is an application directory; `driver-app-preview.html` is a standalone Driver Companion interaction prototype. Both are design-only, use sample data and are not connected to the API. Production apps and role-specific interfaces remain unimplemented.

## Backend foundations currently present

- User registration/login/logout and current-user endpoint
- Organizations and organization-wide membership roles
- Store/product/variant publishing and price lists
- Warehouses, inventory balances, movements, reservations and adjustments
- Order creation with server-side prices, address/product/price snapshots and idempotency
- Warehouse-split fulfillment, packing, shipment events, tracking and stock reconciliation at carrier pickup
- Initial driver/vehicle records, transport trips and delivery tasks
- Initial return request/approval/receipt/disposition/restock workflow
- Invoice snapshots and offline payment records with partial-payment reconciliation
- Internal marketing campaign approval workflow and manually/imported/estimated metrics
- Preliminary AI action/run/tool-call/approval/event data models

The presence of a model, migration, controller, route or visual mock does **not** mean the corresponding operational product is complete. See the roadmap for verification and implementation gaps.

## Engineering principles

1. A user, tenant/organization, legal entity, branch, hub, warehouse, membership, role and operational actor are distinct concepts.
2. Server-side authorization applies to all nested reads, actions, attachments, searches, reports and exports.
3. Parcel/shipment custody events are auditable and not silently overwritten.
4. COD collection, cash handover, bank deposit, settlement and recognized revenue are separate concepts.
5. Inventory reservations are distinct from physical stock; movement/reconciliation happens in controlled transactions.
6. Delivery completion validates assignment, lifecycle, required evidence and payment rules.
7. AI uses registered, permission-checked application actions; it never gets arbitrary database access.
8. Integrations use adapters, authenticated webhooks, idempotency, replay protection, bounded retries and observability.
9. Driver and warehouse workflows must visibly handle poor connectivity, pending sync and conflict resolution.
10. External carrier, payment, messaging, advertising, mapping or GPS support is not claimed until provider configuration and end-to-end verification succeed.

## Local development

Requirements: PHP 8.3+, Composer 2, PostgreSQL 16+, and PHP extensions required by Laravel/Sanctum.

1. Create a PostgreSQL database.
2. Copy `.env.example` to `.env` and configure credentials.
3. Run `composer install`.
4. Run `php artisan key:generate`.
5. Run `php artisan migrate`.
6. Start the API with `php artisan serve`.
7. Run tests with `php artisan test`.

Health check: `GET /api/v1/health`.

Runtime verification is still required; do not treat this guide as proof that the current revision passes setup/tests on every environment.

## Security

Never commit secrets, tokens, passwords, private keys or production data. Review [Security Baseline](docs/SECURITY.md). The repository is not production-ready until migrations, tenant/branch policies, operational tests, CI, backups/restore, retention, monitoring, integrations and security review have been verified.
