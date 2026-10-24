# Logistics Platform Benchmark and Capability Catalogue

**Document status:** researched product benchmark and proposed scope, not a claim that these functions are already implemented. Public feature pages describe products at a high level; capabilities must be validated with real operators and local requirements before committing a release.

## Executive conclusion

This project must not be designed as one dashboard with a handful of tabs. A full logistics operation is a connected portfolio of distinct products: driver mobile, branch/counter/hub operations, warehouse WMS, dispatch and route planning, fleet maintenance, transport/linehaul TMS, shipment visibility, customer/merchant portals, finance and cash settlement, customer service/claims, and platform administration. Each product needs its own user experience, navigation, permissions, detailed workflows, and operational reporting while sharing canonical data and APIs.

This is a representative cross-category benchmark of established and specialist tools, **not a literal exhaustive review of every logistics application that exists**. The market is too large and changes continuously. The benchmark should be refreshed by category as the product evolves.

## Products reviewed and what their public capability pages demonstrate

| Category | Product | Reference | Capability areas to account for in our scope |
|---|---|---|---|
| Last-mile orchestration | Bringg | https://www.bringg.com/platform | Delivery scheduling, route/resource planning, carrier selection, dispatch, live monitoring, exception workflows and driver communication. Its documentation describes planned and on-demand routes and assignments pushed to a dedicated driver app. |
| Delivery orchestration | FarEye | https://fareye.com/platform | Delivery-slot management, capacity-aware scheduling, route optimization, service-level and skill constraints, dispatch, pre-sorting/loading and operational execution. |
| Driver field execution | IDS CoStop / IDS Mobile | https://www.idsapp.com/platform/costop/ and https://www.idsapp.com/driver-app/ | Scanning, proof of delivery, signatures, GPS-stamped events, offline work, exception capture, route/stop workflows, and reverse logistics. |
| Driver/site companion | Astrata Mobile Companion | https://www.astratagroup.com/platform/mobile-app | Jobs, inspections, photos/signatures, GPS-stamped arrivals, two-way messaging, offline capture and sync for drivers/site staff. |
| Driver and work-force operations | Samsara Apps | https://www.samsara.com/products/workforce-management/samsara-apps | Distinct driver/fleet/site experiences, inspections, compliance, dispatch, safety coaching, training and digital workflows. |
| Fleet lifecycle | Fleetio | https://www.fleetio.com/features | Asset lifecycle, vehicle assignments, location history, fuel, tires, preventive maintenance, inspections, work orders, parts inventory, warranties, expenses, replacement planning and utilization. |
| Fleet operations/telematics | Samsara | https://www.samsara.com/products | Telematics, safety, maintenance, driver compliance, dispatch, incidents, fuel/energy, documents, training, mobile devices and reporting. |
| Fleet data and tracking | Geotab | https://www.geotab.com/products/fleet-management-software/ | GPS and trip history, driver behavior, engine/odometer data, route comparisons, maintenance health, privacy-aware tracking and integrations. |
| Warehouse management | Manhattan Associates WMS | https://www.manh.com/solutions/supply-chain-management-software/warehouse-management/what-is-warehouse-management-system-wms | Receiving, returns, putaway, inventory control, waving, picking, packing, loading, parcel connectivity; advanced editions add labor, yard and equipment integration, pick-path optimization and dynamic replenishment. |
| Transportation management | SAP Transportation Management | https://help.sap.com/docs/SAP_S4HANA_ON-PREMISE/e3dc5400c1cc41d1bc0ae0e7fd9aa5a2/9c27c9535f267414e10000000a174cb4.html | Forwarding/freight orders, freight units, transport planning, carrier selection, agreements, tendering, dispatch/monitoring, charge calculation and trade/dangerous-goods constraints. |
| Transportation and freight cost | Oracle Transportation Management | https://docs.oracle.com/cd/E18727_01/doc.121/e13432/T441621T441624.htm | Carrier selection/tendering, rating/routing, inbound and outbound in-transit visibility, freight payment, freight invoice audit and approvals. |
| Forwarding / cross-border logistics | CargoWise | https://www.cargowise.com/solutions/cargowise-forwarding/ | Freight forwarding jobs, landside pickups/deliveries, warehousing/consolidation, customs/compliance, shipping documents, accounting and multi-currency handling. |
| Multi-carrier shipping infrastructure | ShipStation API | https://docs.shipstation.com/getting-started | Rate comparison, create/update/cancel shipments, labels, multi-package labels, batch processing, returns, package types, manifests and pickup scheduling. |
| Shipping API and carrier network | Shippo | https://goshippo.com/products/api | Multi-carrier rates, labels, real-time tracking, address validation, return labels, billing/reconciliation and carrier integration. |
| End-to-end visibility | project44 | https://www.project44.com/platform/visibility/ | Multi-mode shipment visibility, predictive ETAs, milestone continuity between carriers/facilities, data-quality controls, analytics and document extraction/integration. |
| Delivery API examples | Shippo and ShipStation | Links above | Reinforce that shipping requires more than a status field: carrier rates, packages, label lifecycle, manifests, pickups, tracking events and reconciliation contracts are first-class objects. |

### Sources and evidence notes
- Bringg documents routes, automated resource assignment and driver-app dispatch in its platform/knowledge base: https://www.bringg.com/platform and https://help.bringg.com/docs/about-the-bringg-platform
- FarEye lists delivery scheduling, capacity/skill-aware routing and execution capabilities: https://fareye.com/platform
- Fleetio lists vehicle lifecycle, fuel, tire management, assignments, maintenance, inspections, work orders, parts and cost analysis: https://www.fleetio.com/features
- Samsara's navigation and support information separates Driver App, Dispatch, Safety, Compliance, Maintenance, Incidents, Fuel, Documents, Training, Reports and Workflows: https://www.samsara.com/products/workforce-management/samsara-apps and https://kb.samsara.com/hc/en-us/articles/48621492984589-Dashboard-Menus
- Geotab outlines GPS history, driver behavior, engine/odometer data and maintenance: https://www.geotab.com/products/fleet-management-software/
- Manhattan's WMS overview covers receiving, putaway, inventory, wave/pick/pack/load, plus advanced labor/yard/replenishment capabilities: https://www.manh.com/solutions/supply-chain-management-software/warehouse-management/what-is-warehouse-management-system-wms
- SAP TM describes freight planning, carrier selection, tenders, dispatch/monitoring and charges: https://help.sap.com/docs/SAP_S4HANA_ON-PREMISE/e3dc5400c1cc41d1bc0ae0e7fd9aa5a2/9c27c9535f267414e10000000a174cb4.html
- Oracle TMS describes carrier selection, freight rating/routing, freight payment and audit/approval: https://docs.oracle.com/cd/E18727_01/doc.121/e13432/T441621T441624.htm
- ShipStation documents labels, multi-package shipping, batch labels, returns, manifests and pickup endpoints: https://docs.shipstation.com/getting-started
- Shippo documents carrier rates/labels, tracking, address validation, returns and reconciliation: https://goshippo.com/products/api
- project44 covers visibility across modes, predictive ETA, data quality and analytics: https://www.project44.com/platform/visibility/
- IDS and Astrata describe field-side scanning, delivery proof and offline capture/sync: https://www.idsapp.com/driver-app/ and https://www.astratagroup.com/platform/mobile-app

## Expanded capability catalogue

The following is the candidate product scope to decompose into epics, domain models, APIs, application designs, and tests. Items are requirements until explicitly marked implemented and verified.

### A. Dedicated Driver Companion (separate mobile application)
- Driver identity distinct from employee/contractor, account, license/permit, training and operational eligibility.
- Shift sign-in/out, availability, break state, daily briefing, assigned vehicle, mandatory pre-trip inspection and route confirmation.
- Routes and stop queues for pickup, delivery, return pickup, branch/hub transfer, linehaul and special handling.
- Offline-first assigned work, barcode/QR scanning, parcel/count verification, loading and custody handoffs.
- Navigation hand-off, route updates, stop sequence/version, ETA and dispatcher instructions; never require typing/scanning while vehicle is moving.
- Recipient/contact workflow, customer instructions, address landmarks, configurable OTP/signature/photo/name evidence and refusal/failed-attempt reasons.
- Partial delivery where policy permits, reschedule request, return-to-origin, customer pickup and redelivery flow.
- Cash on delivery (COD), receipt/reference, cash/expense records, driver cash sheet, branch cashier handover, discrepancy dispute and settlement history.
- Return collection/RMA, parcel condition, reason code, pickup proof and receiving destination.
- Vehicle safety checks, odometer/fuel/defect reports, incident/accident forms and maintenance handoff.
- Dispatcher communication, shift announcements, emergency support and masked customer contact where available.
- Device/session management, language preference, document expiry, role-scoped work summaries and training.
- Offline outbox, idempotent sync, resumable evidence uploads, collision/conflict handling and user-visible queued/synced/failed states.
- Location collection only while on duty and under visible policy; source/time/accuracy recorded and access/retention controlled.

### B. Branch, Office, Depot and Hub Operations (separate application)
- Network topology: headquarters, region, branch, service office, depot, hub, cross-dock, partner point and pickup/drop-off point.
- Counter bookings, walk-in senders, quote/rate selection, service and SLA, weight/dimensions, declared value, packing supplies, acceptance, label/receipt, payment and customer notification.
- Inbound arrival schedule, vehicle/trip arrival, seal/bag/cage/pallet registration, unloading scans, expected-vs-actual manifest reconciliation.
- Sorting by destination/service/route, mis-sort detection, consolidation, bagging, cage/pallet closure, seal numbers and outbound manifest.
- Dispatch cutoff calendar, route/vehicle loading, load verification, departure scan, inter-branch transfer and receiving acknowledgement.
- Local pickup/delivery queues, courier assignment, customer collection/authorized recipient, identification/OTP policy, proof and missed collection.
- Cash desk, COD remittance, cashier drawer, receipts, driver cash handover, daily closing, shortages/overages and approval-separated investigation.
- Inventory of packaging/materials and counter stock; high-value/fragile/perishable/quarantine storage rules where needed.
- Customer service, complaints, lost/missing/damaged parcel investigation, claims intake and evidence preservation.
- Staff, shifts, till/device/scanner/printer assignment, service windows, holidays, coverage zone and branch-specific permissions.
- Branch KPIs: throughput, acceptance-to-dispatch time, dwell time, missorts, backlog, customer waiting time, failed delivery, cash variance and SLA.
- Every custody transition attributable to parcel/manifest, actor, source/destination location, timestamp, device and exception reason.

### C. Warehouse Management System (WMS)
- Sites, zones, aisles, rack/bin locations, dock and staging areas, inventory ownership and location capacity.
- Expected receipts/ASN, unloading, quality/quantity verification, over/short/damage, receiving holds and putaway strategy.
- SKU, units of measure, barcode, lot/batch, serial, expiry, condition, owner and storage constraints.
- Inventory availability, reservation/allocation, replenishment, wave/batch/zone/cluster picking and task interleaving.
- Pick confirmation, tote/cart tracking, packing, weight/dimension validation, label creation, consolidation and exception handling.
- Cross-dock, dock appointments, staging, loading verification, dispatch manifests and warehouse-to-warehouse transfer.
- Cycle count, physical count, approved adjustment, quarantine, damaged stock, stock ageing and traceability.
- Labor tasks/productivity, handheld/scanner or PWA flow, offline scanning, equipment and yard integration staged over time.
- Inbound/outbound dashboards, dock dwell, pick accuracy, stock accuracy, lines per hour, order aging and fill rate.

### D. Dispatch, Routing and Last-Mile Control Tower (separate workspace)
- Live operational map, unassigned job queue, priority/SLA, zone, branch, stop types and backlog.
- Capacity-aware driver/vehicle assignment based on weight/volume, special skills, availability, shift, zone, vehicle type, time window and cost.
- Route planning for single/multi-depot, pickup-and-delivery pairs, scheduled routes, on-demand jobs, route locks and time windows.
- Owned fleet, contractors and external carriers with provider contracts and cost model.
- Live stop state, planned-vs-actual execution, ETA confidence, no-scan dwell, late-risk detection and exception escalation.
- Reassignment, route change, cancellation, re-delivery and rescheduling with assignment versioning/history.
- Optimizer suggestions with explainable constraints; an authorized dispatcher/rule commits plans; every change is auditable.
- KPIs: on-time delivery, utilization, cost per stop/km, route adherence, first-attempt success, stops per shift and overtime.

### E. Fleet Management & Maintenance (separate workspace)
- Vehicle/equipment registry, ownership/lease, capacity, type, registration, VIN/serial, home depot, assigned branch and life-cycle status.
- Insurance, permits, inspection, driver credential expiry and compliance calendar.
- Vehicle/driver/route assignment calendar, availability, reservation pool, downtime and replacement vehicle.
- Daily pre/post trip inspections, defects, safety incidents, accident claims and approval workflow.
- Preventive maintenance by time, odometer or engine hours; maintenance requests, work orders, labor, parts and external workshops.
- Parts inventory, purchase requests/orders, vendor, warranty, tires, batteries, fuel/energy, tolls, fines and receipts.
- Telematics/GPS/engine integrations with clear provider/source/quality; privacy-aware tracking and configurable retention.
- Cost per vehicle/km/stop, fuel efficiency, utilization, breakdown frequency, planned-vs-unplanned maintenance and lifecycle/replacement forecast.

### F. Transport Management (TMS) and Linehaul
- Multi-leg transport orders, lanes, schedule, load/route planning, capacity, weight/volume/pallet/stop constraints.
- Carrier directory, service zones, contracts, rate cards, SLAs, fuel/remote-area/handling surcharges and quote comparison.
- RFQ/tender, carrier acceptance/rejection, booking, trip assignment, dispatch, documents and milestone tracking.
- Branch/hub linehaul, loading evidence, manifest, bag/seal, transit scan, transshipment, arrival scan and custody transfer.
- Freight charge estimate and actual, supplier invoice, freight bill audit, dispute, approval and cost allocation.
- Delay, lost/damaged cargo, demurrage/detention and recovery work queues where relevant.
- Future forwarding option: road/air/sea legs, bills of lading/air waybills, customs declarations and regulatory checks after market/legal validation.

### G. Parcel and Shipment Visibility
- Canonical shipment/parcel IDs, multiple packages per shipment, package dimensions/weight, contents category, handling flags and associated documents.
- Append-only shipment event timeline, normalized internal/carrier statuses, event source, source time/server time and deduplication.
- Tracking page with only customer-safe data; estimated delivery, milestones, exceptions, support and delivery preferences.
- SMS/email/WhatsApp/push connectors only after provider configuration, consent policies and webhook verification.
- Address quality/geocoding adapter, landmarks, serviceability, delivery-slot scheduling and address correction.
- Carrier adapters for status sync, labels, rates, manifests and returns, with retry, signature verification and stale-event protection.
- Exception management: delayed/held, missed handoff, mis-sort, failed attempt, damage/loss, customs hold, branch hold and return-to-origin.
- Visibility permissions for customer/merchant/partner and private proof/document storage.

### H. Shipping Infrastructure and Multi-Carrier Integrations
- Rate shopping, create/update/cancel shipment, parcel/package types, multi-package shipment and label voiding.
- Batch labels/manifests, pickup scheduling, end-of-day manifests, carrier handoff records and return labels.
- Carrier accounts, credentials/secrets, supported services, coverage, surcharge tables, currency/time zones and contract-aware cost.
- Address validation, shipment tracking, webhook inbox, signature verification, idempotency, retry and event audit.
- Carrier invoice ingestion, duplicate prevention, billed-versus-quoted comparison and reconciliation.
- Internal operation must work without any one external provider; connectors are adapters, not hidden hard dependencies.

### I. Order Management, Merchant and Customer Experiences
- Order source intake: public API, merchant portal, approved ecommerce connector, branch counter and controlled bulk import.
- Product/service catalogue, package rules, rate quote, SLA/coverage validation, business contracts, COD eligibility, payment terms and credit limits.
- Booking, pickup request, service selection, address validation, shipment split/consolidation, scheduled pickup and order amendments.
- Merchant dashboard for bulk jobs, tracking, pickup requests, invoices, settlement statements, claims, API keys and scoped team access.
- Customer tracking, delivery preferences/reschedule, authorized pickup, contact/support, return request and notifications.
- Quote-to-order history, status definitions, data export and audit record for all externally initiated commands.

### J. Finance, COD and Settlements
- Rate/fee engine, surcharges, discounts, taxes and contract price snapshots.
- Invoice, credit/debit notes, payment allocations, partial payment, refunds, chargeback/adjustment, customer receivables and carrier payables.
- COD expected/collected/remitted, by parcel/order/driver/run/branch/merchant/currency; printed/electronic cash sheet and acknowledged handovers.
- Cash desk, drawer sessions, daily close, shortage/overage, bank deposit, settlement batch and discrepancy resolution.
- Commission, payout, courier/merchant/vendor balances, reconciliation import, period close and evidence-linked approvals.
- Future immutable double-entry ledger must be a distinct controlled milestone. An editable payment flag is not an accounting ledger.

### K. Customer Service, Claims and Quality
- Customer/shipper profiles, contacts, consent, contracts and linked shipment/order timeline.
- Ticket intake, reason/category, assignment, priority, SLA timers, communications, internal notes and escalation.
- "Where is my order?" inquiry, recipient unavailable, address issue, damage/loss, non-delivery, cash dispute, returns and claims.
- Investigation case linking scan events, photos, signature, manifest, GPS checkpoint provenance, driver/branch actions and financial adjustment.
- Quality review, repeat issue analysis, root cause, corrective action, customer feedback and service recovery.

### L. Platform Administration and Workforce
- Tenant/legal entity/organization, branch, hub, warehouse, depot, zone and partner hierarchy.
- Staff/contractor/partner identity, scoped roles/permissions, assignment eligibility, certifications, shifts and separation of duties.
- Tenant policies for service levels, reason codes, SLA, evidence requirements, COD and retention.
- Integration registry, secret management, webhook configuration, API keys, usage, audit, access review and security incident workflow.
- Device/scanner/printer assignment, documents and expiries, internal communications, forms, training, leave/attendance as staged HR modules.

### M. Marketing and Merchant Growth
- Campaign plans, budget allocation, channel/creative variants, approval gates, scheduled tasks, segment rules and content/calendar.
- Connector registry, permissions, platform tokens, rate limits, provider-side publication state, retries and verified webhooks.
- Pixel/conversion/event capture, UTM/campaign attribution and separation of measured/imported/estimated metrics.
- Merchant acquisition/onboarding, service performance reports, promotions/rate offers and notification templates subject to consent.
- Do not present internal-only campaign records as published external advertisements.

### N. Analytics, Automation and AI
- Event-backed KPIs with documented formulas and data freshness; drill-through to underlying records.
- Volume/capacity forecasting, risk of SLA breach, no-scan dwell, mis-sort detection, fuel/COD anomalies, stock discrepancies and repeated failed delivery.
- Scenario simulation, route/capacity recommendations, explanations and human acceptance.
- Document classification/extraction for invoices, manifests and shipment documents with review before high-impact writes.
- Rule builder with triggers/conditions/actions, permissions, idempotency, bounded retries, dead-letter handling and audit trace.
- AI assistant with a permissioned tool registry, tenant-scoped access, usage/cost limits, run traces, evaluations and persisted approval for sensitive actions.
- AI must not access arbitrary database tables, fabricate custody events or settle money without validated, authorized actions.

## Yemen-first considerations to validate with real operators

- Arabic RTL as a first-class UI, English support, locally appropriate phone/address/landmark inputs, calendar and timezone.
- Low-connectivity and offline-first driver/warehouse operations with visible sync state.
- Cash on delivery and traceable physical cash handover, daily close and shortage investigation.
- Branch-to-branch intercity linehaul, hub dwell, manifest/bag/seal controls and scan-based custody.
- Serviceable/remote zones, delivery promises, address landmarks and configurable remote-area fees.
- WhatsApp/SMS connectors, consent, provider availability and delivery/webhook verification only after choosing a provider.
- Currency/tax/fees configurable by market and customer contract, not hard-coded assumptions.
- Low-cost deployment staged by operational value without collapsing domain boundaries.

## Implementation rule

A feature is complete only when its workflow exists end to end, API authorization and state invariants are tested, user experience is implemented where required, and external behavior has been verified. A model, migration, route or screenshot alone is not completion.

## Research limits

The benchmark uses public product information from representative platforms; it does not prove access to private implementation details, vendor-specific commercial availability, local compliance or a full competitor census. The next validation activity should be structured interviews with drivers, counter/branch staff, hub supervisors, warehouse operators, dispatchers, fleet mechanics, cashiers, finance, merchants and customers.
