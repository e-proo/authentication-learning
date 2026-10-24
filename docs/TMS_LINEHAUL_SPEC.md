# Transportation Management (TMS) and Linehaul — Product Specification

**Status: Specified; current transport trips are a basic foundation, not a full TMS.**

## Product boundary
A dedicated operations product for the movement of freight across branches/hubs/depots and external carriers, especially scheduled intercity or long-haul legs. It coordinates transport orders, lanes, capacity, carriers, load planning, tendering, trips, milestones and freight cost. The driver app executes assigned trips; branch/hub operations perform physical receipt and custody scans.

## 1. Network and lane master data
- Origin/destination facilities, lane code, allowed corridors, planned distance/duration, service days, cutoff, departure/arrival windows and handoff requirements.
- Scheduled service, one-off trip, multi-leg transfer, relay/driver change, consolidation point and cross-dock.
- Vehicle/cargo constraints, weight/volume/pallet/container limits, permitted goods and route/seasonal restrictions where validated.
- Planning calendar, holidays, provider service windows and time zone.

## 2. Transport order and load planning
- Convert eligible shipment/manifest/transfer requirements into transport demand with required arrival and service level.
- Consolidate loads by compatible destination, handling class, capacity and promise without breaking parcel-level traceability.
- Split workload into transport legs and freight units; preserve shipment-to-manifest-to-leg relationships.
- Load plan with weight, volume, parcel count, loading order, unloading sequence and capacity utilization.
- Validate vehicle/carrier suitability, capacity, driver eligibility, temperature/special handling requirements and mandatory documents.
- Simulate cost/service tradeoffs for direct, multi-stop, relay or cross-dock routing.

## 3. Carrier directory, contracts and procurement
- Carrier/provider profile, legal/contact data, supported lanes/services, operating hours, coverage, capacity and documents.
- Customer/shipper contract, carrier rate cards, currency, base rate, distance/weight/volume band, minimum charge, fuel surcharge, remote-area, wait/detention, handling and other defined extras.
- RFQ/request-for-quote to eligible carriers, quotation validity, response, comparison, selection, approval and audit history.
- Tender workflow: sent, acknowledged, accepted, rejected, expired, re-tendered and awarded, with provider communication channel history.
- Internal fleet and external carriers share one operational model but remain distinct regarding ownership, driver data, cost, service terms and access.
- Rate version and transport terms are snapshotted at booking; updating a contract must not rewrite historic freight charges.

## 4. Trip, dispatch and execution
- Transport order/leg, load, vehicle, driver, carrier, manifest, departure, checkpoints, ETA and expected arrival.
- Pre-departure checklist, capacity validation, cargo/seal count, documents, driver/vehicle eligibility and authorized departure.
- Transit event stream: departed, checkpoint, delayed, transfer/transshipment, arrived, offloaded, incident, reroute, cancelled and completed.
- Handoff to another carrier/vehicle/hub is explicit; record manifest/seal scans and custody acknowledgements.
- Arrival does not automatically mean every parcel was received; destination personnel must reconcile expected vs actual.
- Breakdown, weather/disruption, lost/damaged cargo, missed departure, detention and arrival-window exceptions.
- Preserve all leg/route versions, event source, actor, source/receipt timestamp and reason.

## 5. Freight cost and settlement
- Expected cost by carrier/leg/rate version and actual cost from carrier invoice/extra charges.
- Freight charge calculation, accessorials, currency, tax, discount, weight/dimension re-rating and customer billing allocation.
- Invoice ingestion/import, line-level comparison to quote/contract/manifest, duplicate prevention and dispute/approval.
- Detention/waiting, fuel, remote area, handling and agreed accessorial charges require evidence and applicable rate rule.
- Allocate cost to shipment, merchant, customer contract, branch, route or cost center with audit.
- Finance approval remains separate from the operator who created the trip or carrier quote.

## 6. Documents and compliance (market-dependent)
- Waybill/consignment note, manifest, loading list, delivery receipt, permits and carrier paperwork.
- Cross-border forwarding extension only after country-specific requirements are validated: air/sea/road legs, bills of lading/air waybills, packing lists, customs declarations, certificates, restricted-goods and compliance screening.
- Secure private document access, expiry/status, revision and association to appropriate leg/party.

## 7. Monitoring and analytics
Plan-vs-actual departure/arrival, on-time lane performance, load utilization, transit dwell, transfer success, loss/damage, carrier acceptance, tender cycle time, freight spend vs contract, invoice discrepancy, cost per kg/parcel/km and emissions where reliable data exists.

## 8. Integration contracts
- Shipment/manifest/hub operation: canonical packages and custody scans.
- Fleet/Driver: eligible resources, trip assignment, departure checklist and journey updates.
- Dispatch: local pickup/delivery legs and route handoff.
- Carrier adapter: tender, booking, status, documents and invoice feed only after provider access is verified.
- Finance: freight payable, supplier invoice, approval, reconciliation and allocation.
- Event bus/outbox with idempotency, retry, signature verification for provider webhooks and stale/duplicate milestone protection.

## 9. Acceptance gates
Create a scheduled lane, turn shipments into a load/manifest, calculate capacity, compare at least two configured carrier offers (or test fixtures), award/tender, assign vehicle/driver, depart with load evidence, record an intermediate handoff, arrive and reconcile package counts, ingest carrier invoice lines and detect a rate variance. Test no-capacity, rejected tender, duplicate milestone, missing package and invoice mismatch.

## Current gap
Current trips and task events cover only a small internal execution slice. Lane planning, carrier contracts/rates/tendering, load planning/consolidation, multi-leg/cross-dock, freight invoice audit, external carrier integrations and full TMS reporting remain unimplemented.
