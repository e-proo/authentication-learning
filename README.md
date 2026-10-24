# Logistics & Commerce Platform

A modular platform for logistics, multi-organization commerce, inventory, marketing, finance, and permissioned AI workflows.

> Current status: foundation in progress. This repository is being converted from an empty repository into the platform's application codebase. Features are only considered complete after automated tests and runtime verification.

## Architecture

- **Backend:** Laravel 13 / PHP 8.3+
- **Database:** PostgreSQL
- **API:** versioned REST API under `/api/v1`
- **Authentication:** Laravel Sanctum
- **Architecture:** modular monolith with explicit domain boundaries
- **Frontend:** planned Vue 3 + TypeScript application (separate implementation phase)

## Core principles

1. A user, organization, branch, membership, role, permission, and commercial relationship are different concepts.
2. Tenant access is enforced on the server, never only by hiding UI elements.
3. A single customer order may split into multiple fulfillments and shipments.
4. Confirmed orders preserve price, address, fee, and terms snapshots.
5. AI tools use registered, permission-checked application actions. They do not get direct database access.
6. Sensitive actions require the appropriate permission and, where configured, explicit approval.
7. Business events and sensitive changes are auditable.

## Local development

Requirements: PHP 8.3+, Composer, PostgreSQL, and the PHP extensions required by Laravel.

1. Copy `.env.example` to `.env` and configure PostgreSQL.
2. Install dependencies: `composer install`
3. Generate an application key: `php artisan key:generate`
4. Run migrations: `php artisan migrate`
5. Start the API: `php artisan serve`
6. Run tests: `php artisan test`

The initial API health endpoint is `GET /api/v1/health`.

## Build plan

- [x] Establish repository direction and initial application scaffold
- [ ] Identity, organizations, memberships, roles, and permissions
- [ ] Tenant isolation and audit logging
- [ ] Product catalog, price lists, inventory, and stock movements
- [ ] Orders, fulfillment, shipment lifecycle, and delivery tasks
- [ ] Invoices, payments, refunds, and settlements
- [ ] Campaigns and marketing integrations
- [ ] Permissioned AI tool registry, approvals, usage tracking, and audit trail
- [ ] Vue application, end-to-end tests, deployment, and observability

## Security

Never commit `.env`, access tokens, passwords, private keys, or production data. See `docs/SECURITY.md`.
