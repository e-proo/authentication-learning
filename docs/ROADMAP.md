# Product and Delivery Roadmap

## How to read this roadmap

- **Implemented, unverified** means code exists, but the workflow/runtime/security/tests have not yet been verified.
- **Specified** means the requirement is documented, not implemented.
- **External integration pending** means provider configuration/credentials and an end-to-end verification are still required.
- An application is not complete because it has models, migrations, routes or a design mock.
- Roadmap items are grouped by **standalone product/workspace**, not by a single admin sidebar.

## Phase 0 — Product discovery and application architecture
- [x] Research representative driver, last-mile, fleet, WMS, TMS, shipping API, tracking and forwarding products.
- [x] Publish capability catalogue and research references in `docs/PRODUCT_BENCHMARK.md`.
- [x] Define separate applications/workspaces in `docs/APPLICATIONS_AND_WORKSPACES.md`.
- [x] Specify dedicated Driver Companion in `docs/DRIVER_COMPANION_SPEC.md`.
- [x] Specify Branch/Office/Depot/Hub Operations in `docs/BRANCH_OFFICE_HUB_SPEC.md`.
- [ ] Conduct structured interviews with drivers, branch/counter staff, hub supervisors, warehouse workers, dispatchers, fleet mechanics, cashiers, finance, merchants and customers.
- [ ] Validate legal, payment, messaging, mapping, device and carrier requirements for the launch region.
- [ ] Establish canonical domain glossary, status-transition matrix, permission matrix, event catalogue and OpenAPI contracts.

## Phase 1 — Backend foundation and verification
- [x] Laravel application scaffold and API health endpoints.
- [x] Initial authentication, organizations, membership roles and permissions.
- [x] Initial store/product/variant/price-list APIs.
- [x] Initial warehouses, balances, movements and reservations.
- [x] Initial order, fulfillment, shipment lifecycle and idempotency foundation.
- [x] Initial returns, offline finance records and internal marketing foundations.
- [ ] Run clean install/migrations and full test suite in CI; fix all failures before expanding more workflows.
- [ ] Complete authorization matrix for tenant, branch, site, assignment, attachment and export scopes.
- [ ] Define canonical parcel/package, shipment, bag/cage, manifest, route, stop, cash movement and custody event models.
- [ ] Add outbox/webhook infrastructure, idempotent event processing, retry/dead-letter controls and structured observability.
- [ ] Establish verified test/coverage gates and document actual CI result per commit.

## Phase 2 — Shipment, package and custody backbone
- [ ] Support multiple packages per shipment, package-level identity/barcodes, dimensions, weight and handling flags.
- [ ] Define immutable custody/event timeline with actor, source/destination, parcel/manifest, source timestamp, server receipt timestamp and reason.
- [ ] Define manifests, bags/cages/pallets/seals, manifest close/reopen corrections and count discrepancy workflow.
- [ ] Normalize internal statuses and external provider statuses; add deduplication, stale-event protection and audit.
- [ ] Implement media/proof storage with private-by-default access, upload validation, hash/retention and verification status.
- [ ] Complete partial fulfillment, split/consolidation and explicit failed-delivery recovery policy.

## Phase 3 — Dedicated Driver Companion mobile app
- [ ] Choose PWA vs Expo/React Native after actual-device validation of camera, GPS, background work, offline persistence and push messaging.
- [ ] Implement driver profile eligibility, shifts, assigned vehicles and readiness/pre-trip checklist.
- [ ] Implement assignments, routes, stops, safe navigation hand-off and route-version conflict handling.
- [ ] Implement pickup scanning, parcel/load validation, custody proof and failed pickup reason workflows.
- [ ] Implement delivery attempt, configurable recipient verification, photo/signature/name proof and policy-required field validation.
- [ ] Implement failed delivery, reschedule, partial handoff where allowed, return-to-origin and pickup authorization.
- [ ] Implement return pickup, inspection and return custody handover.
- [ ] Implement COD collection receipts, driver cash sheets, branch/cashier handover, discrepancy escalation and settlement view.
- [ ] Implement vehicle inspections, defect/incident reporting, documents and expiry reminders.
- [ ] Implement dispatcher messaging, task notifications and emergency/help workflow.
- [ ] Implement offline outbox, idempotent sync, media upload retry, conflict resolution and observable sync state.
- [ ] Enforce shift-scoped transparent location collection, least privilege and evidence retention.
- [ ] Field-test on low-connectivity Android devices; pass the release gates in the driver specification.

## Phase 4 — Branch, Office, Depot and Hub Operations
- [ ] Introduce site types and capabilities: counter branch, office, depot, sorting hub, cross-dock, warehouse and partner point.
- [ ] Implement customer counter booking, rating/serviceability, package capture, label/receipt, payment and acceptance.
- [ ] Implement arrival schedule, dock check-in, seal inspection, receiving scans and expected-vs-actual reconciliation.
- [ ] Implement scan-to-sort, destination queues, mis-sort prevention, bags/cages/containers and seal closure.
- [ ] Implement outbound manifest, load verification, dispatch cutoff, trip departure and transit custody.
- [ ] Implement branch-to-branch transfer receipt scans and discrepancy resolution.
- [ ] Implement local delivery dispatch handoff, authorized customer pickup and held/return-to-origin policies.
- [ ] Implement cashier/till shifts, driver COD handover, cash close, over/short investigation and separation of duties.
- [ ] Implement lost/damaged/missing parcel case investigation and evidence links.
- [ ] Implement branch-specific roles, devices, supplies, shift handover and site-level operating reports.
- [ ] Verify end-to-end branch transfer, missing item, duplicate scan and COD variance scenarios.

## Phase 5 — Warehouse Management System (WMS)
- [ ] Model warehouse zones/bins/capacity and location-aware inventory.
- [ ] Implement expected receipts, receiving inspections, over/short/damage and putaway.
- [ ] Implement replenishment, allocation, waves/batches, zone/cluster picking and task queues.
- [ ] Implement packing, weights/dimensions, labels, staging, dock loading and shipment handoff.
- [ ] Implement cycle/physical counts, approved adjustments, quarantine and traceability.
- [ ] Add scanner/handheld workflow, offline-safe scans and labor/productivity reporting.
- [ ] Integrate WMS receipts/dispatch to shipment and branch/linehaul custody without duplicate stock transitions.

## Phase 6 — Dispatch and Route Planning / Last-Mile Control Tower
- [ ] Implement unassigned-work queue, map, driver/vehicle availability and service-zone constraints.
- [ ] Implement manual assignment/reassignment with history and capacity validation.
- [ ] Implement planned routes, stop windows, multi-depot routes and pickup/drop-off pairing.
- [ ] Add optimizer adapter or optimization engine with explainable constraints and human/rule commit control.
- [ ] Implement live route progress, planned-vs-actual, ETA provenance, delayed-risk detection and exception escalation.
- [ ] Implement reschedule/re-delivery/retry workflow and customer notification hooks.
- [ ] Track route adherence, first-attempt success, on-time performance, utilization and cost/stop.

## Phase 7 — Fleet Management and Maintenance
- [ ] Expand vehicle/equipment registry, assignments, availability, reservations and lifecycle.
- [ ] Implement insurance/permits/inspection/driver-license expiry calendar and alerts.
- [ ] Implement preventive maintenance, service requests, work orders, labor, parts, vendors and service history.
- [ ] Implement fuel/energy, tires, odometer, tolls, fines, expenses and warranty tracking.
- [ ] Implement incident/accident reporting, safety review, downtime and replacement planning.
- [ ] Integrate telematics/GPS/engine signals through verified provider adapters; define location privacy policy.
- [ ] Deliver utilization, cost/km, fuel efficiency, downtime and maintenance KPI reports.

## Phase 8 — Transport Management System (TMS) and Linehaul
- [ ] Implement transport orders, legs/lanes, consolidation, load planning and weight/volume/capacity rules.
- [ ] Implement carrier directory, rate cards, service levels, surcharges, contracts and rate comparison.
- [ ] Implement request-for-quotation/tendering, carrier acceptance/rejection and booking.
- [ ] Implement trips, vehicle/driver assignment, departure/arrival, transshipment and hub handoffs.
- [ ] Implement freight cost estimate/actual, carrier invoice ingestion, audit, dispute and approval.
- [ ] Add international forwarding/customs/document flows only after local/legal scope validation.

## Phase 9 — Shipment Visibility and external shipping/carrier integrations
- [ ] Build customer-safe tracking page and event normalization with clear ETA confidence/freshness.
- [ ] Create external carrier adapter contract for rates, labels, manifests, pickup, tracking and returns.
- [ ] Integrate provider webhook inbox, signature verification, retry, idempotency and event replay controls.
- [ ] Implement multi-carrier rate shopping, label lifecycle, multi-package labels and manifest/pickup scheduling where providers support it.
- [ ] Add address validation/geocoding adapter and make provider availability/config explicit.
- [ ] Measure integration coverage and fail safely when providers are unconfigured/unavailable.

## Phase 10 — Merchant, Customer, Support and Claims products
- [ ] Merchant portal for bulk bookings, pickup requests, tracking, invoices, settlement statements, claims, scoped team and API credentials.
- [ ] Customer portal for tracking, notification preferences, reschedule, pickup authorization, support and returns.
- [ ] Customer service case management, SLA queues, communications, escalation and evidence-linked claim investigations.
- [ ] Service catalog, rate/contract rules, serviceability and booking API with documented state policy.
- [ ] Role-specific UI journeys; do not make customer/merchant/mobile flows small tabs of the admin console.

## Phase 11 — Finance and cash settlement
- [x] Initial order invoice snapshots and offline pending-payment/partial-payment foundation (unverified until CI/runtime succeeds).
- [ ] Verified payment-provider webhooks, signature checks, replay protection, failure/reversal and refund state machine.
- [ ] COD expected/collected/handed-over/disputed/settled accounting by parcel, driver, shift, branch and merchant.
- [ ] Branch till/drawer open-close, cash receipts, driver handover acknowledgement, shortages/overages and approval separation.
- [ ] Immutable double-entry ledger with reconciliation and period-close controls.
- [ ] Courier/merchant/vendor payables, commissions, settlements, payouts and financial reports.

## Phase 12 — Marketing and merchant growth
- [x] Initial internal campaign/creative approval and manual/imported/estimated metric records (unverified).
- [ ] External ad-platform connectors with verified token handling, provider-side publication state and webhooks.
- [ ] Consent-aware customer notification templates and channel delivery status.
- [ ] Conversion/event tracking, attribution and measured-vs-estimated reporting connected to source evidence.
- [ ] Merchant onboarding, campaign billing and performance exports where business model requires them.

## Phase 13 — AI and workflow automation
- [x] Initial AI action/run/tool-call/approval/event data models and definitions exist (not a working AI agent).
- [ ] Context resolver for current user, tenant, branch, assignment and effective permissions.
- [ ] Strict code-defined tool registry and input/output schemas; no arbitrary class/function dispatch from stored data.
- [ ] Read-only tools using domain services and tenant-scoped filters.
- [ ] Human-approved write actions with permission revalidation, approval separation and idempotency.
- [ ] Model gateway/provider configuration, quotas, cost/token tracking, redaction and timeout/retry behavior.
- [ ] Run/event traces, evaluation datasets, regression tests, monitoring, abuse controls and rollback.
- [ ] Workflow automation triggers, condition rules, bounded retries and dead-letter visibility.

## Phase 14 — Dedicated frontend applications
- [ ] Platform/org administration workspace.
- [ ] Dedicated Driver Companion mobile application.
- [ ] Dedicated Branch/Office/Depot/Hub application.
- [ ] Dedicated WMS workspace/scanning client.
- [ ] Dedicated Dispatch/Route Planning workspace.
- [ ] Dedicated Fleet Maintenance workspace.
- [ ] Dedicated TMS/linehaul workspace.
- [ ] Customer-safe Tracking and Customer portal.
- [ ] Merchant/Shipper portal.
- [ ] Finance and COD Settlement workspace.
- [ ] Customer service/claims workspace.
- [ ] Marketing/AI/AUTOMATION operational workspaces.
- [ ] Arabic RTL and English LTR, accessibility, responsive behavior and end-to-end tests.

## Phase 15 — Production readiness
- [ ] Full clean-install/migration/test verification on CI and supported environments.
- [ ] Branch/tenant/assignment authorization matrix and adversarial multi-tenant test suite.
- [ ] Load/performance testing, queues, backups, restore drills and disaster-recovery objectives.
- [ ] Structured logs, metrics, traces, alerting, runbooks and incident response.
- [ ] Secrets rotation, audit retention, private proof media, data minimization and security review.
- [ ] Verified integration credentials/contract tests and operational dashboards.
- [ ] Production deployment runbook, upgrade/rollback plan and operations acceptance.

## Release governance

A work item may be marked complete only when code, UI/client (if required), business transition rules, permissions, automated tests and integration verification for that capability are all complete. Documentation or mock screens are specifications/prototypes, not delivery evidence.
