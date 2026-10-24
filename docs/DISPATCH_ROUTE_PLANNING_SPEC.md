# Dispatch and Route Planning — Product Specification

**Status: Specified; current trips/tasks do not constitute a dispatch or route-optimization product.**

## Product boundary
A distinct planning/control-tower workspace for turning eligible pickup/delivery/transfer jobs into feasible assignments and route plans, monitoring execution and handling exceptions. It is not the driver app: dispatchers plan and intervene; drivers safely execute assigned work. It is not Fleet: Fleet owns asset availability/maintenance; Dispatch consumes eligibility and assignment data.

## 1. Work intake and queue
- Unified candidate queue from orders, pickups, returns, branch transfers, hub/linehaul legs and approved external tasks.
- Filters by region/branch/zone, promised date/window, service level, customer priority, parcel size/weight, handling class, task type and SLA risk.
- Validate serviceability, destination, duplicate work, current status, ready-for-dispatch state, required documents/payment conditions and inventory/manifest handoff.
- Ready, blocked, on-hold, unassigned, assigned, planned, dispatched, in-progress, exception, completed and cancelled work states with reasons.
- Manual edits are versioned and authorized; no dispatch command bypasses order/shipment state rules.

## 2. Resource/capacity model
- Drivers, contractors, partner carriers, vehicles, route capacity, weight/volume/stop limits, depot, zones, skills, certifications, duty window, breaks and availability.
- Asset suitability from Fleet, driver eligibility from Identity/Workforce, parcel constraints from Shipment, stop time windows from Service Promise.
- Predict capacity and workload before accepting new same-day jobs.
- Score assignment options by on-time promise, route distance/time, resource cost, capacity, service zone, skills and workload; show reasons/tradeoffs.

## 3. Route planning
- Single/multi-depot routes, scheduled runs, same-day/on-demand routes, pickup-delivery precedence pairs and time windows.
- Plan using distance/travel time, stop service times, vehicle capacity, driver shift/breaks, road restrictions and required return-to-depot.
- Route sequence, departure time, loading order, planned stop windows, planned distance/duration and capacity utilization.
- Manual route editing, route locking and exceptions with change reason.
- Optimize batch/all-day planning and incremental insertion of urgent stops while minimizing disruption.
- Recompute with fresh traffic/map data only when an enabled provider exists; expose data source, last update and fallback behavior.
- Optimization should provide explainable constraints, before/after route version and estimated cost/SLA impact. Dispatcher or approved rule commits the plan.
- Never claim AI optimization exists when code only sorts tasks or assigns them manually.

## 4. Dispatch and live monitoring
- Assign tasks/stops/route versions to drivers and vehicles; push assignment to Driver Companion and acknowledge receipt.
- Live operations map and list with last location timestamp, task state, ETA provenance, actual check-in, remaining stops and last successful sync.
- Reassign work; cancel/reschedule; update route; send safe task-specific instructions; preserve assignment history.
- Track planned vs actual departure/arrival, dwell, late risk, skipped stops and route deviation where data is available.
- Monitor partner/external carrier progress separately with provider source and event freshness.

## 5. Exception management
- No driver/vehicle available, over capacity, late pickup, delayed transit, no scan, failed attempt, wrong address, recipient unavailable, damaged parcel, payment issue, vehicle breakdown, signal/device offline or missing proof.
- Rules for alert, retry, escalate, reassign, return to branch/hub, contact customer or supervisor approval.
- Every exception has owner, priority, reason, timestamps, SLA, related shipment/task/route and resolution history.
- Avoid repeated alert storms by deduplication, cooldown and clear resolved/reopened states.

## 6. Dispatcher control-room UX
- Separate views for unassigned work, map, planning, route board, live execution, exception queue, capacity/coverage, driver communication and analytics.
- High-density desktop planning with filters and bulk action previews; operations remain auditable and reversible where policy allows.
- A command preview states affected stops/orders, risk, notification impact and permission before committing major replans.
- Driver app and branch/hub screens are separate; they receive assigned work and actions through APIs.

## 7. Performance reporting
On-time pickup/delivery, first-attempt success, stops per shift, route adherence, idle/dwell time, miles/km per stop, load utilization, SLA-risk resolution time, assignment latency, reassignments, exception rate and route cost. Show planned vs actual and metric definition.

## 8. Integration contracts and safeguards
- Reads eligible jobs/resources through domain services; commits assignments and route versions via validated APIs.
- Push notification/acknowledgement and offline route conflict handling.
- Map/traffic/geocoding providers behind adapters, credential/configuration status and quotas.
- Optimization engine is replaceable, has bounded time/size, fallback to manual operation and no silent destructive replan.
- Revalidate assignment, driver eligibility, vehicle status, state and permissions at dispatch/departure.
- Idempotency for job assignment and route commit, audit events for every manual/automated change and observability for optimizer failures.

## 9. Acceptance gates
Import eligible jobs, validate capacity and service windows, generate a feasible route plan, inspect estimated distance/time and constraints, commit a route version, deliver assignment to the driver app, record actual scans/check-ins, alert on a missed window, reassign a stop and retain the original history. Test invalid/ineligible vehicle, overlapping assignment, stale location, duplicate event and optimizer outage.

## Current gap
Current trip/task creation and assignment are basic execution foundations. Dispatch board, map/live monitoring, capacity model, route planning/optimization, ETA quality, exception control tower and dispatcher UX remain unimplemented.
