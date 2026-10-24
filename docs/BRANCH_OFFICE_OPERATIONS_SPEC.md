# Branch & Parcel Office Operations — Product Specification

**Status: specified, not implemented as an end-to-end branch app.** The current organization/branch foundations, shipment and driver APIs are insufficient to claim full branch, counter, hub or cash-desk functionality.

## Product definition

A parcel branch, customer service office, depot or hub is a full operational workspace. A facility may perform a subset of capabilities based on its type and permissions. A counter branch is not the same as a sorting hub, warehouse bin location, corporate office or fleet workshop.

## Facility network and setup
- Network topology: head office, regional office, parcel counter, depot, local delivery station, hub, cross-dock and partner facility.
- Facility code, type, parent, operating hours/timezone, service zones, cutoff schedule, holiday calendar, capacity, supported services and escalation contacts.
- Staff membership, branch-scoped roles, shifts, counters, tills, scanners, scales, label printers, cameras and storage/staging areas.
- Facility-level opening/closing, service readiness, downtime reason and workload status.
- Configure permitted operations by facility type. Avoid arbitrary status changes that bypass custody or cash control.

## Main workspace navigation
1. Branch dashboard and current shift
2. Counter bookings and shipment intake
3. Quotes, rates and service eligibility
4. Parcels, packages, measurements and labels
5. Inbound arrivals and received manifests
6. Sortation, bags/cages and sealing
7. Outbound manifests and dispatch
8. Inter-branch transfers
9. Local delivery allocation and customer collection
10. Return-to-origin and claims intake
11. COD desk, cashier handover and safe/cash close
12. Staff/shift handover and equipment
13. Exceptions, missing/damaged parcels and investigations
14. Operational reports and audit history

## Counter intake workflow
- Search customer/business account; confirm sender/recipient, safe contact details, address/landmark, service zone and service eligibility.
- Quote price/service, collect package count, weight/dimensions, commodity/handling flags and declared value when required.
- Validate packaging and restricted/unsupported categories using policies.
- Create shipment and parcel IDs, print scan-ready label, issue acceptance receipt and record payment/COD policy.
- Support multi-piece shipment with parent shipment/child parcel identifiers and package-level scan history.
- Prevent intake for a closed service zone or unconfigured service. Exceptions require authorized override with a reason and audit.

## Inbound receiving and sortation
- Show expected incoming manifests/transport legs, origin, destination, vehicle/driver, departure status, seal IDs and expected item counts.
- Scan manifest/seal and parcels during unloading; reconcile expected vs actual count.
- Missing, extra, damaged, misrouted, wrong-branch and broken-seal cases create incident records with evidence and investigation queue.
- Route parcels to destination branch/hub, delivery station, pickup hold or exception area using explicit rules.
- Group scanned items into bags/cages/pallets/handling units; record contents, target facility, capacity, seal, creator, close time and manifest.
- Closure freezes contents except through a controlled reopen/discrepancy process.

## Outbound dispatch and linehaul handover
- Build outbound manifest from eligible parcels/handling units for a transport leg, route or carrier.
- Validate destination, count, capacity/weight, service constraints, due times, driver/vehicle/carrier and credentials.
- Scan each item or sealed handling unit at loading; record load sequence, seal, handover actor and timestamp.
- Driver/carrier signs or acknowledges custody; departure creates in-transit history.
- Receiving facility scans at arrival; trip completion alone does not mark every parcel received.
- Variance queues and aging alerts escalate unreceived, late or inconsistent manifests.

## Local delivery and customer collection
- Local delivery queue separated by zone, SLA, priority, assignment and attempt count.
- Send eligible tasks to Dispatch; keep the branch UI focused on workload/readiness rather than building routing logic inside the counter app.
- Customer collection must validate authorized recipient/pickup code, collect signature/evidence if configured, settle any due charges and issue a receipt.
- Failed delivery returns to a controlled state: retry, reschedule, hold at branch, recipient pickup or return-to-origin.
- Track parcel shelf/hold location and expiry/notifications for branch collection.

## COD and branch finance controls
- Separate expected COD, driver-collected cash, cash handed to branch, cashier-received cash, bank deposit and reconciled balance.
- Cashier handover sheet by driver/run/branch/currency with receipt references, amount, source events and discrepancy state.
- Opening float, till activity, authorized adjustments, expenses, shift transfer, closing count, over/short reason, evidence and supervisor approval.
- User cannot approve their own cash discrepancy or edit a confirmed historical handover; reversal requires a new audited adjustment.
- Branch income, fees, COD liabilities and courier/merchant payables remain distinct accounting concepts.

## Return and claims desk
- Create and classify claims for loss, damage, tampering, delay, refusal or delivery dispute.
- Link claim to shipment/parcel/manifests/custody events and restricted proof attachments.
- Manage return authorization, return pickup, branch receiving/inspection, restock/discard/quarantine and refund request.
- Escalation deadlines, customer communication, investigation owner, decision reason and audit history.

## Reports and operational KPIs
- Inbound/outbound volume, manifests awaiting dispatch/receipt, dwell time, unscanned parcels, misroutes, damaged/missing count, branch backlog and cutoff misses.
- First-attempt success, local delivery backlog, customer pickup aging, branch collection outcomes, COD expected-vs-handled, cash over/short and daily close.
- Staff throughput and errors using explicit formulas and privacy-aware access controls.

## Acceptance tests before declaring the app implemented
- A shipment accepted at Branch A moves through a sealed manifest and transport leg to Branch B with every parcel/handling unit reconciled.
- A broken seal or count mismatch blocks normal close or creates an explicit authorized exception.
- A trip marked arrived cannot mark all parcels received without scans or approved exception.
- A bag closure freezes contents and reopening is audited.
- Customer pickup verifies authorization and records custody handover and receipt.
- Driver cash handover remains separate from cashier confirmation and bank reconciliation.
- Staff without Branch B scope cannot search or export Branch B records.
