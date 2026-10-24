# Warehouse Management System (WMS) — Product Specification

**Status: Specified; current warehouse/inventory APIs are only a foundation.** A warehouse list and inventory adjustment endpoint do not constitute a WMS.

## Product boundary
Dedicated application for warehouse receiving, storage, inventory control, fulfillment work, picking/packing, staging, loading, transfer, counts and warehouse performance. It owns location-aware warehouse work and integrates with Orders, Branch/Hub, Dispatch and Finance; it does not replace the driver app or the linehaul TMS.

## Roles and operating modes
Warehouse manager, receiver, quality inspector, putaway operator, replenisher, picker, packer, loader, inventory controller, cycle-counter and auditor. Web/tablet/handheld flows should adapt by role and task; scanning first should be possible where devices permit.

## Master data and layout
- Warehouse/site hierarchy, zones, aisles, racks, shelves, bins, docks, staging lanes, quarantine/damage area and yard slots.
- Location code/barcode, storage type, dimension/weight limit, allowed SKU/handling class, capacity, replenishment threshold and active/blocked status.
- SKU/UOM/barcodes, lot/batch, serial, expiry, owner/merchant, condition, hazard/handling restrictions and inventory status.
- Warehouse calendar, receiving cutoff, shifts, equipment, operators and work area assignments.

## Inbound and receiving
- Purchase/transfer/merchant ASN or expected receipt, appointment, inbound load and supplier/carrier reference.
- Dock assignment, vehicle/seal check, unload, scan container/package, expected-vs-actual quantity, unit-of-measure and condition.
- Quality inspection, damage photos, short/over receipt, unidentified item, wrong SKU, expired/lot mismatch and controlled receiving hold.
- Create traceable stock receipt only after acceptance; all adjustments have reason, actor, timestamp and audit.
- Suggested putaway locations based on capacity, storage constraints, velocity, lot/expiry and zone policy; manual override needs reason.

## Storage, inventory and movement
- Location-level on-hand, reserved, available, allocated, damaged, quarantine, in-transit and count-pending quantities.
- Putaway tasks, directed replenishment, bin-to-bin movement, stock ownership transfer and inter-warehouse transfer.
- FEFO/FIFO and lot/serial traceability where required; blocked, recall, expired or restricted units must not be allocated.
- Cycle counting by frequency/risk/ABC/location, blind count where policy requires, recount and approval thresholds.
- Physical count and adjustment with variance reason, evidence, approval and reconciled inventory movement.
- Inventory ageing, dead/slow stock, capacity utilization, stock accuracy and traceability reports.

## Outbound and fulfillment
- Release eligible orders and allocations based on promise, cutoff, customer priority and inventory availability.
- Wave, batch, zone, cluster, discrete and priority picking strategies, selected by warehouse policy.
- Task interleaving and workload queue; picker sees exact location, item, quantity, lot/serial and scan verification.
- Pick exception handling: empty bin, wrong item, short quantity, damaged item, lot constraint and inventory discrepancy.
- Tote/cart/container association; consolidation only where shipment/order rules allow.
- Packing station: scan verification, packaging rules, dimensions/weight capture, scale integration, fragile/hazard labels and document generation.
- Package/label creation and immutable pack confirmation; no duplicate stock decrement when shipment events later reduce physical inventory.
- Staging lanes by route/carrier/service/departure window; load scan validates package, manifest, destination and vehicle capacity.
- Handover to branch/linehaul/driver as an explicit custody event.

## Yard, docks and labor (staged capabilities)
- Dock appointment calendar, yard check-in, trailer/vehicle slot, dwell time and dock door assignment.
- Inbound/outbound yard movement and load/unload status when operationally justified.
- Worker productivity and labor planning, skill eligibility, workload balancing and equipment/task dependencies.
- Material-handling equipment integration only after hardware and safety requirements are validated.

## Exception and quality management
- Unknown inventory, missing/extra units, damaged goods, wrong location, short pick, label mismatch and shipment/manifest variance.
- Quarantine, hold, recount, damage/disposal approval, supplier claim and return-to-vendor workflows.
- Evidence, responsible custody segment and investigation status.
- SLA timers and alerts for dock dwell, stuck receipt, delayed pick, blocked order and incomplete dispatch.

## Reporting
Receiving accuracy and cycle time; putaway time; pick accuracy; units/order/hour; fill rate; inventory accuracy; aged/unallocated orders; replenishment latency; stockout and shrinkage; dock dwell; labor/equipment utilization; shipment-ready-to-depart time. Metrics must be defined, timestamped and drillable to evidence.

## Integrations and invariants
- Orders/fulfillment: reservations and allocation must agree; pick/pack is not itself proof that a carrier received the parcel.
- Parcel/manifest: one label maps to a canonical package/shipment identity; scans are idempotent and preserve source device/user/time.
- Inventory: all movements occur through domain services and transactions; no free-form table edits.
- Branch/hub: outbound load and receiver scan are separate custody events.
- Finance: cost/value changes must be auditable and cannot be inferred from physical stock state alone.
- Offline handheld scan flow: visible pending sync; server deduplicates replay and surfaces conflicts.
- Enforce tenant, site, warehouse, role and task permissions on reads and commands.

## Initial acceptance gates
1. Receive a planned ASN and detect short/over/damaged/mismatched items.
2. Put accepted stock into a valid bin and query available inventory.
3. Reserve stock for order, create directed pick tasks, scan/confirm picking, pack with a verified package/label and stage by departure.
4. Scan into outbound manifest and record handover without double-decrementing stock.
5. Run cycle count, identify variance, require approval where configured and reconcile through movement history.
6. Test tenant/site isolation, duplicate scans, offline retries, blocked/expired lots and concurrent reservation.

## Current gap
The repository contains initial warehouse records and inventory balances/movements/reservations. Directed putaway, bin-level workflows, receiving/quality, picking waves, pack stations, scanning client, dock/yard/labor management and full WMS acceptance tests remain unimplemented.
