# Branch, Office, Depot and Hub Operations — Application Specification

**Status: Specified, not implemented end to end.** Organization/branch records are not equivalent to a complete branch operations product.

## Product scope

A dedicated application for retail/counter offices, neighborhood branches, sorting depots, regional offices, main hubs, cross-docks and partner locations. It manages the complete physical handoff and local operating day: accept, identify, sort, store briefly, manifest, load, transfer, receive, hand to a delivery route or customer, reconcile cash, investigate discrepancies and close the day.

Branch, warehouse, depot, hub and office are related but distinct operational site types. A branch may accept customers and collect cash; a hub may primarily handle sortation and linehaul; a warehouse manages stock and fulfillment; an admin office may have no parcel custody. Configure capabilities by site type instead of treating all places as the same table and screen.

## 1. Network/site setup
- Hierarchy: organization/legal entity, region, branch/office, depot, hub, cross-dock, warehouse, partner service point and customer pickup point.
- Site code/label, address and landmarks, service zone, coordinates (with source), hours, holidays, cutoff times, timezone and supported services.
- Site type/capability configuration, secure zones, counter/till, docks, staging areas, bins, cages, scanners, printers, weighing/dimensioning devices and user assignments.
- Capacity and constraints: parcels/hour, dock doors, storage slots, inbound/outbound windows, supported handling classes and linehaul connections.
- Staffing shifts, users/roles, local supervisor, escalation contacts, courier partner and emergency procedures.
- Site-level policies cannot bypass organization-wide permissions, payment controls or custody invariants.

## 2. Customer counter and booking
- Walk-in sender / business customer lookup, contact verification and saved customer permissions.
- Create quote from origin/destination, service level, weight/dimensions, package count, declared value, handling category, delivery promise, COD and customer contract.
- Validate serviceability, cutoff, restrictions, recipient address/landmarks, remote area, required documents and price before accepting.
- Parcel/package creation and labels; multi-piece shipment support; print receipt and customer tracking reference.
- Packaging supplies, insurance/declared-value options where offered, fragile/oversize handling and refusal of unsupported cargo.
- Collect counter fee through configured cash/bank/provider method with receipt and till session; record unpaid/credit exception only when policy permits.
- Edit/cancel before custody acceptance under explicit rules; after acceptance, amendments are versioned and audited.
- Notify sender/recipient only through consented, configured channels; failed notifications remain observable.

## 3. Inbound arrival and receiving
- Expected transport trip/vehicle, driver, origin, ETA, manifest, bag/cage/pallet IDs and seals.
- Arrival check-in, dock assignment, seal inspection/open record and authorized receiving user.
- Scan each manifest unit or parcel; reconcile expected count with actual received/missing/extra/damaged/wrong-destination items.
- Maintain custody event per package or sealed unit using a configurable policy; sealed containers must still be reconciled at break/separation.
- Quarantine damage/unknown/misrouted parcels, capture evidence and create discrepancy case without silently accepting count.
- Calculate dwell time and alert when cutoff/SLA is at risk.
- Trip completion does not equal package receipt; receipt acknowledgement is an explicit receiving operation.

## 4. Sorting, consolidation and handling
- Work queues sorted by destination, route, service level, promised date/time, parcel category and next movement.
- Scan-to-sort with immediate wrong-destination/missort warnings and configurable override reason.
- Consolidate parcels into bag/cage/pallet/roll-container; capture container ID, destination, expected item count/weight and seal ID.
- Break-open and re-sort operation with full parent/child and custody history.
- Oversize, fragile, heavy, high-value, temperature-sensitive or restricted parcel handling only where the service supports it.
- Temporary storage location / bin, item hold, quarantine, customer pickup staging and unclaimed-parcel management.
- Throughput queues, bottlenecks, item dwell time and unresolved mis-sort dashboard.

## 5. Outbound and linehaul departure
- Dispatch plan for the next truck/vehicle/linehaul leg with origin/destination, route, schedule, driver/carrier and capacity.
- Build or select manifest; scan bags/containers/packages as loaded and validate destination, count, weight/capacity and seal.
- Close manifest with authorized staff; record departure, vehicle odometer where relevant, driver acceptance and seal record.
- Handle missing items, late arrivals, overcapacity, vehicle unavailable and missed departure cutoff through explicit exceptions/replanning.
- Emit trip/leg and custody events to central tracking; external carrier handoff includes carrier identity, reference and required proof.
- Preserve manifest versions and load/unload discrepancies; no delete-and-recreate that erases history.

## 6. Inter-branch, customer pickup and last mile
- Transfer order with origin/destination, package scope, reason, service-level and expected arrival.
- Receive at destination by scans and reconcile count; open a discrepancy case for missing/extra/damaged items.
- Local delivery queue generated from eligible shipments; hand off to dispatch/driver app with route/service details.
- Customer pickup/collection with authorization, OTP or required identity check, signature/receipt and time-stamped custody event.
- Return-to-origin, refused, held-at-branch, expired-pickup and redelivery policies with customer communication and charge rules.
- Branch staff must not fabricate delivery proof on behalf of a driver or mark a parcel handed over without required evidence.

## 7. Cash desk, COD and finance control
- Named cashier/till/drawer shift open, starting float and authorized payment modes.
- Counter fee collections and receipt cancellation/refund with reasons/permissions.
- Driver cash-sheet receipt: expected amount, collected amount, currency, route, parcels, references, cashier count and acknowledgement.
- Separate cash collection, cash received into till, bank deposit, settlement transfer and finance reconciliation records.
- Cash close, over/short, voided receipts, cash movement log, discrepancy ticket, evidence and supervisor/finance review.
- COD payable to merchant is not automatically the same as company revenue; accounting and settlement approval separation must be enforced.
- No one user should silently create a payment, acknowledge its handover and approve its discrepancy resolution without an explicit permitted workflow.

## 8. Customer service, claims and parcel investigation
- Branch search for authorized parcels by label/barcode/reference/customer with scoped permissions and safe masking.
- Lost parcel, mis-sort, damage, seal tampering, wrong branch, missing contents, failed delivery, cash dispute and long dwell cases.
- Timeline and evidence from scans, manifests, driver tasks, trips, pictures, recipient receipt and cash records.
- Quarantine, recount, transfer request, re-delivery, return-to-origin, claim escalation and customer follow-up.
- Record responsible party and custody interval without presuming fault before investigation.

## 9. Staff, devices and local daily management
- Staff roster and shift handover, till/device/scanner/printer assignment, drawer access and supervisor override.
- Work queues, task allocation, counter queue/service time, staffing shortages and operational announcements.
- Supplies and packaging inventory with reorder thresholds and purchase requests.
- Operating-hours/cutoff calendar, public holidays, closures and emergency fallback routing.
- Role-specific views: counter clerk, sorter, receiver, dispatch loader, branch supervisor, cashier, claims staff, auditor.

## 10. Reporting and KPIs
- Accepted shipments by service/product/customer/branch.
- Inbound expected vs received, unmatched scans, missort rate and damage discrepancy.
- Dock dwell, branch dwell, average counter service time and cutoff departure adherence.
- Manifest count/weight variance, transfers in transit, unreceived shipments and aged holds.
- First attempt/on-time delivery results for branch-originated work.
- Counter receipts, COD expected/received/handed over, till close variance and unresolved reconciliation.
- Labor throughput, per-site capacity/utilization and cost-to-serve, using validated metric definitions.
- Each metric drill-throughs to underlying records and displays freshness/definition.

## 11. Integration contracts
- Booking/order service: quote, create parcel/package, print label, accept custody, cancel/amend rules.
- Shipment event service: scan, source, origin/destination, actor/device, timestamp and exception.
- Manifest/linehaul: create, add/remove item with audit, seal/close, depart, arrive, receive and reconcile.
- WMS: storage locations, putaway/hold and warehouse transfers when a site has warehouse capability.
- Dispatch/driver: eligible local-delivery queue, assignment and last-mile status/proof.
- Finance: receipts, till sessions, COD cash sheet, handover acknowledgement, close and discrepancy.
- Notifications: template/provider state, consent, retry and delivery confirmation.
- Claims/support: investigation, evidence linking and SLA escalation.

## 12. Acceptance gates

A branch/office release must prove the end-to-end chain: accept and label a real shipment, scan into an arriving manifest, detect count/mis-sort discrepancies, sort/close an outbound bag or manifest, dispatch a transfer, receive at another site by scan, hand to driver/customer with permitted proof, reconcile collected cash, and close a till/shift. Tests must cover branch-tenant isolation, duplicate scans/events, offline scanner retry, invalid receiving, manifest count mismatch and cash segregation.

## Current gap

Current organization/branch foundations and warehouse/driver/trip/task APIs do not yet implement this complete branch-office product. This specification is a planned scope, not a claim of completion.
