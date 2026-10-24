# Application Portfolio and Workspace Architecture

## Product decision

**One logistics platform is a portfolio of separate applications, not one universal dashboard.** Share identity, APIs and the core shipment/order data model, but give every operational group a separate user experience, navigation, permissions, device strategy and task lifecycle.

The existing `preview.html` is a temporary visual prototype, not the approved application architecture and not evidence that the system's functional scope is limited to its current sidebar.

## Application portfolio

| Standalone application / workspace | Main users | Experience | Main functional boundaries |
|---|---|---|---|
| Executive Network Control Tower | executives, network managers | Web, high-level KPIs/map/exception queues | Network health, SLA, capacity, service quality, throughput, cost and escalations |
| Platform Administration | platform operator, security/admin | Secure web console | Tenants, legal entities, identity, access policies, integrations, billing/usage and audits |
| Organization Administration | organization owner/admin | Secure web console | Organization, regional hierarchy, branches/hubs, staff, policies, services, contracts and reports |
| Branch & Office Operations | counter clerks, branch supervisors, depot/hub managers, cashiers | Dedicated web/tablet workspace with scanner and printing | Shipment booking/acceptance, label/receipt, intake/sorting/manifests, transfers, branch delivery, customer pickup, COD and daily close |
| Driver Companion | pickup, last-mile, returns, branch-transfer and linehaul drivers | Dedicated mobile app, Android-first and offline-aware | Shift, route, stops, scans, proof, customer contact, exceptions, COD, cash handover, vehicle inspection and returns |
| Dispatch & Route Planning | dispatchers, route planners, control-room staff | Desktop-first map and job board | Unassigned work, resource assignment, route optimization, live progress, replanning and exceptions |
| Fleet Management | fleet managers, mechanics, safety/compliance teams | Fleet web workspace and optional technician mobile flow | Vehicle lifecycle, assignment, maintenance, parts, inspections, fuel, incidents, documents and total costs |
| Warehouse WMS | receiving, putaway, inventory control, pick/pack/load staff | WMS desktop plus scanning PWA/handheld | Locations, receiving, quality, putaway, replenishment, picking waves, pack, staging, loading, counts and discrepancies |
| Transport/Linehaul TMS | transport planners, carrier desk, intercity/long-haul managers | Transport-planning workspace | Legs, lanes, load planning, carriers/rates, RFQs/tendering, trips, milestones, freight charge and carrier invoice audit |
| Shipment Visibility | tracking operators, customer support, authorized partners | Web control tower and APIs | Event quality, ETA, exception monitoring, customer-safe tracking and carrier feeds |
| Merchant/Shipper Portal | merchants and contract shippers | Responsive web/API | Bulk bookings, pickup requests, tracking, invoices, settlements, claims, team access and integration credentials |
| Customer Portal | senders and recipients | Mobile-first web | Tracking, delivery preference, reschedule, pickup authorization, support, return request and consent |
| Finance & Settlement | finance/accounting, cashiers, settlement approvers | Controlled finance workspace | Rates/charges, invoices, cash office, COD handover, allocations, payables, reconciliation and payout approval |
| Customer Service & Claims | support, call center, claims investigators | Case-management workspace | Tickets, contact history, SLA, delivery exceptions, loss/damage claims and evidence investigation |
| Marketing & Merchant Growth | marketers, creative reviewers, analysts | Campaign workspace | Planning, creative approval, connectors, publication state, tracking, attribution and performance |
| AI & Automation Operations | approved AI admins/operators, risk owners | Governed operations console | Tool registry, permissions, approvals, traces, evaluation, usage budget, automation rules and failures |

These are product boundaries and experiences; they need not each be a separate backend deployment. Start with a modular backend and separately routed web/mobile clients. Split backend services only when reliability, scale or independent delivery teams justify it.

## Shared platform services

- Authentication, sessions, recovery, optional SSO and device/session revocation.
- Tenant, legal entity, region, branch, hub, warehouse, depot, service zone and partner hierarchy.
- Organization/branch/resource-scoped authorization; backend authorization remains mandatory even when UI hides controls.
- Canonical customer, parcel/package, shipment, order, manifest, route, stop, vehicle, driver, cash movement, invoice and event identifiers.
- Event/outbox delivery, idempotency, notifications, document/media storage, webhook inbox/outbox and audit trail.
- Integration registry and secret storage, API contracts/versioning, observability, logs/metrics/tracing and data retention.
- Shared localization/design components, not a shared one-size-fits-all navigation.

## Separate application navigation requirements

### Driver Companion
Today/Shift, My Route, Pickups, Deliveries, Hub/Branch Transfers, Returns, COD & Cash Handover, Vehicle Check, Messages/Support, Work Summary/Settlements, Profile/Documents.

### Branch & Office Operations
Counter & Bookings, Inbound Arrivals, Scan/Sort, Bags/Cages/Manifests, Outbound Dispatch, Inter-Branch Transfers, Local Delivery, Customer Pickup, COD/Cash Desk, Customer Service/Claims, Staff/Shifts, Supplies, Daily Close and Reports.

### Warehouse WMS
Work Queue, Inbound/ASN, Quality Holds, Putaway, Inventory/Locations, Replenishment, Wave/Batch Picking, Packing, Staging/Loading, Transfers, Counts, Discrepancies, Labor/Equipment and Reports.

### Fleet Management
Vehicles/Equipment, Assignments, Reservations, Maintenance Planner, Work Orders, Inspections/Defects, Parts, Fuel/Energy, Tires, Incidents, Compliance/Documents, Vendors/Warranties, Costs/Utilization and Reports.

### Dispatch & Route Planning
Unassigned Jobs, Network Map, Capacity/Availability, Planning, Routes, Live Execution, Replanning, Zones/Service Areas, Driver Communication, SLA Exceptions and Route Analytics.

## Cross-app business invariants

1. A parcel/shipment has canonical identity and an append-only custody/event timeline.
2. A handoff between driver, branch, hub, warehouse, linehaul carrier or recipient is recorded with authorized actor, location, time, parcel/manifest, outcome and source.
3. A task references assigned work; the mobile client calls authorized domain APIs and never directly mutates database tables.
4. COD collected by the driver is a cash-custody/liability movement that must reconcile to a handover/settlement. It is not automatically recognized as revenue.
5. A trip completion cannot silently imply that a branch/hub received every parcel: receipt needs scan or a controlled exception process.
6. Proof evidence includes type, capture/receipt timestamps, actor, storage reference, verification state and retention policy; public tracking must never expose private proof.
7. Route re-planning preserves prior versions, assignment history, operator/rule and change reason.
8. Provider events/webhooks are authenticated, de-duplicated, replay-protected where relevant and observable.
9. Organization and branch restrictions apply to nested records, searches, downloads, reports, attachments and exports.
10. UI visibility is not access control. Revalidate permission, assignment, state and approval at the server on every command.

## Readiness labels for every roadmap item

- **Implemented + verified**: implemented end to end and verified by automated tests/CI and relevant integration checks.
- **Implemented, unverified**: code exists; runtime/migration/permissions/integration not yet verified.
- **Specified**: requirements and workflow documented, implementation not claimed.
- **External integration pending**: provider adapter/configuration/credentials and end-to-end verification still needed.
- **Needs decision/field validation**: market requirements, operators or regulatory questions remain open.

Do not mark a mobile app, route optimizer, GPS integration, external ads, payment webhook or AI agent complete because a model, migration, route or mock screen exists.
