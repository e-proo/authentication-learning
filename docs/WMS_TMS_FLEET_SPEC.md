# WMS, TMS and Fleet — Separate Product Specifications

**Status: specified, not implemented end-to-end.** These are separate domains and workspaces even though they share shipments, facilities, vehicles and events.

## A. Warehouse Management System (WMS)

### Workspace users and navigation
Users: receivers, putaway staff, pickers, packers, loaders, inventory controllers, supervisors and warehouse managers.
Navigation: Work Queue, Inbound, Quality/Discrepancies, Putaway, Inventory/Bins, Replenishment, Allocation, Picking Waves, Packing, Staging/Loading, Inter-warehouse Transfer, Cycle Counts, Returns, Labor/Tasks, Exceptions, Reports, Configuration.

### Required workflows
- Facilities, zones, aisles, racks, bins, docks, staging and handling-unit/container IDs.
- Expected receipt/ASN and supplier/carrier reference; scan receipt, inspect quantity/quality and manage discrepancy.
- Rule-guided putaway based on product/storage requirements, capacity and location constraints.
- Bin-level inventory with SKU/unit, quantity, serial/lot/batch/expiry, owner, quality status and audit trail.
- Allocation and reservation rules that do not oversell available stock.
- Picking by order, wave, batch, cluster or zone; task queues by priority and worker/zone.
- Replenishment from reserve to pick faces, shortage exception and demand-triggered restock.
- Pack validation, package dimensions/weight, packaging recommendation, label generation and multi-package handling.
- Staging, dock/load appointment, manifest, load verification and carrier handover.
- Cycle count, physical count, variance investigation, approval-based adjustment and stock ageing.
- Cross-dock, returns inspection, quarantine, refurbishment/discard and controlled restock.
- Mobile/handheld scanning, accessible large touch controls, offline-safe task queue and explicit sync results.
- Optional later capabilities: RF/voice, labor standards, slotting, yard management, dock scheduling, warehouse automation/robotics and 3PL billing.

### Core invariants
- Do not decrement/increment inventory without an immutable inventory movement and authorization.
- Reserved, available, damaged, quarantined, in-transit and on-hand quantities must not be conflated.
- A handling unit tracks its contents and location; sealing/closure freezes contents until an audited reopen.
- Every scan is idempotent and validates expected facility, task, resource state and permission.
- Corrections are compensating movements with reasons, not deleting historical evidence.

## B. Transportation Management System (TMS) and linehaul

### Workspace users and navigation
Users: transport planners, carrier procurement, linehaul dispatchers, freight accountants and transport supervisors.
Navigation: Transport Orders, Planning Board, Lanes, Load Building, Carrier Directory, Contracts/Rate Cards, Rating/Quotation, Tendering, Trips/Legs, Dispatch, Milestones/Visibility, Exceptions, Freight Audit, Claims, Reports, Integrations.

### Required workflows
- Transport order can create one or multiple legs with origin/destination, mode, service window, handling units and constraints.
- Build consolidation/load plan across consignments; validate capacity, weight, volume, stop sequence, transit time, commodity/temperature/handling requirements.
- Maintain carriers and service levels, contract/rate card versions, currencies, fuel/remote-area surcharges, accessorials and effective dates.
- Rate a shipment, compare eligible services, tender to carrier, record accept/reject/expiry and preserve tender history.
- Dispatch owned fleet or partner carrier with driver/vehicle/manifest/seals and authorized documents.
- Record pickup, departure, arrival, transshipment, delay, exception and delivery milestones; normalize carrier status.
- Calculate estimated and actual freight cost; import carrier bills, compare against contracted/estimated charges, flag variance, route for approval and reconciliation.
- Track carrier performance, on-time departure/arrival, dwell time, tender acceptance, damage/loss claims, cost per lane and service-level compliance.
- International forwarding is a separate optional module with jurisdiction-specific customs requirements; do not imply customs compliance before validation.

### Core invariants
- Planned route/load and actual execution are distinct records.
- A leg's completion does not imply shipment receipt without custody/scan or approved exception.
- Rate and carrier contract versions are snapshotted onto a quote/order.
- Provider updates are deduplicated and cannot roll back a more advanced verified state.
- Freight invoice approval is separate from the person who created the carrier charge.

## C. Fleet and Workshop Management

### Workspace users and navigation
Users: fleet managers, dispatch coordinators, technicians, workshop supervisors, procurement and safety/compliance staff.
Navigation: Assets, Vehicle Calendar, Driver Assignments, Inspections, Defects, Work Requests, Maintenance Plans, Work Orders, Workshop Schedule, Parts Inventory, Purchase Orders, Vendors, Fuel/Energy, Tires, Incidents, Licenses/Documents, Warranties, Costs, Utilization, Reports, Telematics Integrations.

### Required workflows
- Vehicle/equipment register, plate/asset code, class, capacity, ownership/lease, branch/depot, status and assigned operator.
- Registration/insurance/permit/inspection dates, expiry alerts and document access controls.
- Reservation and driver assignment calendar with collision prevention and operating-hour checks.
- Pre-trip/post-trip inspection, severity, photos/evidence, defect ownership, repair decision and release-to-service.
- Preventive service schedules by date/odometer/engine hours; work orders, diagnosis, vendor, parts, labor, duration, cost and next service.
- Parts stock, reorder threshold, purchase requests/orders, receiving, issue to work order and returns.
- Fuel/charging, odometer, tires, toll/fines, accidents, damage and lifecycle cost.
- Telematics/GPS adapters with data provenance, connectivity health and retention policy.
- Fleet availability, utilization, downtime, maintenance compliance, cost per km/stop, fuel efficiency, repeat defects and replacement planning.

### Safety and financial boundaries
- A critical vehicle defect may block new assignments; dispatch cannot override without explicit policy/approval.
- Driver does not mark their own reported high-risk defect as resolved; mechanic/supervisor records inspection and release.
- Actual sensor data identifies source/time/quality. Missing GPS is not equivalent to zero movement.
- Maintenance invoices feed payable review; work-order closure alone does not mean invoice paid.
