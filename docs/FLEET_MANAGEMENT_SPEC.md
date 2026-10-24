# Fleet Management and Maintenance — Product Specification

**Status: Specified; current vehicle/driver records are not a complete fleet system.**

## Product boundary
A dedicated workspace for asset lifecycle, availability, driver/vehicle assignment, compliance, inspection, maintenance, parts, fuel, incidents, operating expenses and utilization. It supplies eligible resource/cost data to Dispatch and TMS; Driver Companion reports inspections and defects back into Fleet. It is not the same product as Driver Companion or Dispatch.

## 1. Asset registry
- Vehicle/equipment/trailer/two-wheeler/truck/van/motorcycle records, ownership/lease, make/model/year, VIN/serial, registration plate, capacity/weight/volume, fuel/energy, meter unit and home depot.
- Asset life state: ordered, active, reserved, in trip, maintenance, blocked, accident hold, retired/disposed.
- Ownership/cost center, purchase/lease details, warranty, assigned branch, allowable zones and operational constraints.
- Attachments with restricted access: registration, insurance, inspection, permit, lease, repair evidence and ownership papers.
- Odometer/engine-hour snapshots with source and timestamp; prevent suspicious backwards jumps without a reviewed correction.

## 2. Driver, vehicle and resource allocation
- Assignment calendar, default/base assignment, temporary substitution and shared vehicle use.
- Check availability against active trip, scheduled maintenance, inspections, permits and vehicle capacity/type before dispatch.
- Support driver eligibility based on license class, credential expiry, skill/training, duty window and contract type.
- Reservation/use policy, keys/fuel card/device assignment, check-in/out and condition report.
- Keep assignment history and reason for reassignment; avoid deleting prior vehicle/driver linkage.

## 3. Compliance and documents
- Registration, insurance, roadworthiness inspection, permit, commercial authorization, license, training and certification.
- Expiry calendar, reminders/escalation and policy-based dispatch block/warning.
- Region-specific requirements are configuration/validated scope, not guessed universal law.
- Auditable review, renewal upload and document visibility by role.

## 4. Inspections and safety
- Configurable pre-trip/post-trip checklists by vehicle type and organization policy.
- Tires, lights, brakes, mirrors, safety gear, body, cleanliness, fuel/charge and equipment checklist.
- Pass, minor defect, major/critical defect; required photo/comment and meter values.
- Critical defects put vehicle into maintenance/blocked state until an authorized clearance.
- Accident, road incident, breakdown, cargo damage, theft/seal tamper, third-party details and response/evidence workflow.
- Driver coaching or training should use recorded events and privacy/legal review; do not infer guilt from one raw signal.

## 5. Maintenance planner
- Preventive schedule by date, distance/odometer, engine hours or manufacturer/validated interval.
- Repair request from driver inspection, dashboard/telematics alert or mechanic report.
- Triage severity, maintenance job card/work order, approval, workshop/vendor, booking, downtime and promised return-to-service.
- Labor tasks, parts consumed, external invoice, attachments and supervisor inspection.
- Service history, recurring defects, root cause, warranty claim and post-repair test.
- Return vehicle to active state only after requirements pass; preserve who cleared it and why.

## 6. Parts, fuel and operating costs
- Part catalogue, SKU, stock location, reorder point, purchase request/order, receipt, issue to work order and stock audit.
- Fuel transactions: liters/kWh, amount, currency, station/vendor, odometer, vehicle, driver, receipt, fuel card and anomaly checks.
- Tires: tire serial/position, fitment/removal, tread/condition, retread and lifecycle cost.
- Tolls, fines, parking, permits, insurance premiums, repairs, leasing and incident expenses.
- Budget vs actual by asset, branch, vehicle class and cost center; require approval for exceptional spend.
- Store currency/tax as explicit configuration and capture evidence for financial records.

## 7. Telematics and monitoring
- Provider adapter for GPS, trip history, odometer, engine fault/health, fuel, harsh events and device health.
- Every reading includes provider/device ID, observed time, received time, units, accuracy/freshness and processing state.
- Map views show last update and location quality; stale data must not appear as live.
- Shift/duty privacy policy, visible driver notice, retention and permission-scoped access to location and camera data.
- No provider integration is working until credentials, event validation and failure/latency behavior are tested.

## 8. Fleet reports
Availability/utilization, cost/km and cost/stop, fuel economy, downtime, preventive-maintenance compliance, repair turnaround, repeated defect rate, incident frequency, asset age/replacement estimate, tire cost and operating expenses. Each KPI needs a definition and traceable source data.

## 9. Integration invariants
- Dispatch sees only eligible assets; assignment is revalidated when a trip departs.
- An inspection can block dispatch when critical defects exist; dispatcher overrides require permission and reason.
- Fleet maintenance doesn't silently rewrite trip or shipment history.
- A driver task with vehicle must preserve vehicle/driver/trip assignment snapshots.
- Telematics readings are evidence, not automatic proof of parcel delivery.
- Costs and maintenance approvals use separation of duties and auditable records.

## 10. Acceptance gates
Create an asset, upload a dated document, detect expiry, assign to a driver/route, run pre-trip inspection, record a critical defect and block dispatch, create maintenance work order with part and cost, clear the defect with an authorized inspection, then report utilization and total costs. Test stale telematics, reassignment history, vehicle capacity and permission boundaries.

## Current gap
The repository has initial `FleetDriver` and `FleetVehicle` records. Maintenance schedules/work orders, inspections, part inventory, fuel, documents/expiry lifecycle, incidents, telematics adapters and full fleet KPIs remain unimplemented.
