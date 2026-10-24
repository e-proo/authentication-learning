# Driver Companion — Dedicated Mobile Application Specification

**Status: Specified, not implemented.** Existing driver and vehicle CRUD, trips and delivery tasks are backend foundations only. They do not constitute a working driver application. Native mobile clients, background location, route navigation, offline sync, barcode capture, push notifications, media upload, cash handover and telematics are not claimed to work.

## 1. Product definition

A standalone field app for multiple driver roles: pickup, last-mile delivery, returns, branch/hub transfer, linehaul, dedicated customer fleet and contracted/partner courier. It is optimized for Arabic-first use, one-handed interactions, field safety, intermittent mobile service, accountable parcel custody and cash collection.

Choose Android-first delivery after testing target devices. Keep iOS-capable API/product boundaries. Validate whether Expo/React Native or a PWA is appropriate; camera, GPS, background operation, offline persistence, notifications and device support must be tested on physical devices before committing to technology.

## 2. Driver profile and operational eligibility

- Link user account to driver profile without conflating identity, employment/contract, payout and operating status.
- Staff/contractor/partner type, driver code, contacts, emergency contact, home branch, service zones, language and preferred shifts.
- License/class, expiry, permits, training, dangerous-goods or specialized-skill endorsements where applicable.
- Eligibility engine for active/suspended status, expiry, required training, shift, vehicle type, load/capacity, zone and skill requirements.
- Driver document uploads, expiry reminders, review/renewal history and access controls.
- Employment terms, compensation/incentive policy and payout eligibility as separate permission-scoped capabilities, not visible to every supervisor.

## 3. Shift and day start

- Secure login, session/device state, language, biometric convenience when supported and revocation for lost devices.
- Show offline queue and failed uploads before shift start; require reconciliation or documented override when policy requires it.
- Shift check-in/out, availability, break, sick/unavailable, assigned depot/branch, work zone and daily briefing.
- Confirm driver and assigned vehicle; block/warn if vehicle is suspended, incompatible or inspection/credential requirements are unmet.
- Mandatory pre-trip inspection, odometer/fuel/condition where required.
- Download only the assigned routes/stops, parcel lists, recipient instructions and current policy needed for offline operation.
- Visible on-duty location-tracking state, purpose, policy, permissions and stop time; no covert tracking after shift ends.

## 4. Driver home and daily operations

- Summary of assigned tasks, unaccepted assignments, pickup/delivery/transfer/return counts, urgent time windows and SLA risk.
- Route map and stop list, sequence/version, priority, service time, ETA, allowed arrival window and customer/merchant instructions.
- Task actions filtered by role and assignment; pickup-only drivers should not see irrelevant COD settlement or delivery actions.
- Clear task state (assigned, accepted, en route, arrived, in progress, completed, failed, postponed, returned) and controlled transitions.
- Reassignment notification, route version conflict warning and safe dispatcher messaging.
- At-stop checklist, scan-first experience, minimum necessary typing, supported accessibility and large touch targets.

## 5. Pickup workflow

- Merchant/customer, address and pickup window with contact policy, package count and service instructions.
- Scan expected labels; verify package, quantity, dimensions/weight where needed and handling class.
- Record extra/missing/wrong-label/damaged/unsafe package discrepancy; never silently accept mismatched custody.
- Capture proof of pickup, recipient/shipper name, signature/photo/seal/manifest evidence if configured.
- Label/receipt output via supported Bluetooth/network printer or digital receipt when applicable.
- Failed pickup reasons, notes, evidence, reattempt/reschedule and dispatcher escalation.

## 6. Last-mile delivery workflow

- Address landmarks, route navigation hand-off, recipient/contact rules, parcel list, package condition, COD due and delivery instructions.
- Arrival/check-in with time and location provenance; configurable geofence/accuracy checks and override reason.
- Scan parcel at stop and validate assignment; alert when a parcel is missing, extra or belongs to another stop.
- Recipient verification by approved method: name, OTP, signature, ID reference or authorized proxy; minimize collection of unnecessary personal data.
- Policy-driven proof-of-delivery fields: recipient, signature, photo, timestamp, location, condition, package count and note. Required fields vary by customer/service.
- Support partial handoff only if the shipment/parcel model and policy permit it; keep remaining quantities/items open.
- Controlled outcomes: delivered, recipient unavailable, refused, wrong/unclear address, no access, location closed, payment issue, damaged, partial, reschedule, safe-place only if allowed, branch hold or return-to-origin.
- Required failure reason and optional evidence, customer-safe notification and next-step policy.
- Prevent completion unless all configured proof, scanning and payment requirements pass on the server.
- No screen should encourage interaction while the vehicle is moving; use safe-stop gating and read-only navigation while driving.

## 7. Branch, hub, cross-dock and linehaul transfers

- Assigned manifest, bag/cage/pallet/seal identifiers, origin/destination, expected parcel count, route/leg and time window.
- Load scan and loading confirmation; discrepancy list for missing/extra/damaged/wrong-destination items.
- Seal close/open events, responsible actor and evidence where required.
- Arrival and unload scan at destination; receiving staff's acknowledgement must create the receiving custody event.
- A trip's completion cannot auto-mark every shipment received without scans or a controlled discrepancy workflow.
- Transit checkpoint, route diversion, delayed trip, breakdown, seal issue, lost parcel and emergency escalation.
- Hand off to next driver/carrier/branch with immutable manifest and custody record.

## 8. Returns and reverse logistics

- Return pickup authorization/RMA, item/parcel list, reason, original shipment, allowed pickup window and origin/destination.
- Verify label/order, scan item, capture visible condition and tamper/package condition, return reason and sender/recipient acknowledgement.
- Failed pickup/return, refusal, missing item and partial return handling according to explicit policy.
- Return to branch, hub, merchant, sender or warehouse with receiving scan and evidence.
- Do not restock an item on the driver's completion action; receiving/inspection and inventory disposition belongs to authorized operations staff.

## 9. COD, cash, expenses and settlement

- Amount due by shipment/order, currency, allowed mode, status and receipt policy.
- Record collection amount, payment method, receipt/reference, time and associated parcel/order. Partial collection only under explicit contract/policy.
- Idempotent requests and server-side validation prevent double collection, unauthorized amount changes or collection on a non-COD order.
- Cash sheet by shift/route/branch/currency: expected, collected, handed over, pending, disputed and variance.
- Cash handover to named authorized cashier with counted amount, acknowledgement, timestamp and evidence.
- Discrepancy workflow: reason, evidence, supervisor review, dual approval when applicable and immutable history; driver cannot approve their own shortage adjustment.
- Driver expense/toll/fuel/parking advance: receipt image, category, amount, trip linkage, approval and reimbursement/settlement status.
- Earnings/commission/incentive and payout summary only when configured and authorized; do not expose other drivers' balances.

## 10. Vehicle, safety and compliance

- Assigned vehicle/trailer and equipment confirmation, mileage/odometer and fuel/charge level.
- Pre-trip/post-trip inspection checklist by vehicle class, required photos and critical defects.
- Block vehicle usage or require override/maintenance clearance for safety-critical defects.
- Fault/incident/accident form, location/time, photos, parties, witness details as allowed, damage report, insurer/police reference and escalation workflow.
- Breakdown assistance, replacement vehicle, tow/vendor contact and package custody protection.
- Driver licence/permit expiry alerts, required training and compliance prompts relevant to the jurisdiction and vehicle class.
- Optional telematics or engine diagnostics only through documented authorized integrations; show source and freshness.

## 11. Messaging, notifications and support

- Assignment/route-change alerts, customer delivery notices where consented, cutoff reminders and urgent safety/incident messages.
- Task-bound messaging with dispatcher/branch, acknowledgements and searchable audit history.
- Customer contact through masked calling/messaging when provider supports it, not by copying private numbers broadly.
- Push notification registration/revocation and delivery/error state. External SMS/WhatsApp integration is pending until a provider is chosen and tested.
- Help center, FAQs, incident reporting, support request, version/device status and lost-device session revoke.

## 12. Offline-first and synchronization requirements

- Persist minimum assigned work locally; encrypt sensitive cache where supported and remove/revoke access after reassignment/logout according to policy.
- Outbox event ID, idempotency key, device ID, event type, local capture time, server receipt time and sync state.
- State indicators for locally saved, awaiting upload, synced, retry failed and requires review. Never show a local-only delivery as server-confirmed.
- Resumable photo/signature uploads with content checks, bounded retries and retention; don't mark required evidence verified before server acceptance.
- Conflict handling for offline route reassignment, cancelled orders, duplicate scans, invalid transitions, stale COD amounts and expired assignments.
- Server authority over status, cash and proof requirements; device clock/location is evidence with provenance, not absolute truth.
- Monitoring for stale devices, sync age, failed media, conflict queues and repeated API failures.
- Physical-device tests: no signal, airplane mode, low storage, force stop, battery saver, app update mid-route, duplicate taps, damaged camera, GPS unavailable, clock skew and interrupted upload.

## 13. Privacy and security

- Driver tokens are least-privilege and assignment-scoped; no organization-wide order browsing.
- Data minimization: display only fields required for current work; protect recipient contact details and delivery addresses.
- Shift-scoped location tracking with a visible indicator, documented purpose, tenant policy, access logs, retention and a clear off-duty state.
- Proof media private by default; short-lived authorized links, role-scoped access and audit.
- Device loss/session revocation, rate limits, secure local storage and media validation.
- Every sensitive driver command validates organization, assignment, state, required evidence, time window and permission on the server.
- Cash, safety and proof events are append-only/auditable with corrections represented by new events, not overwritten records.

## 14. Required screens and application map

1. Sign-in and device/session security.
2. On-duty status, shift start/end and readiness checklist.
3. Home / daily work summary.
4. Route planner/list/map and stop detail.
5. Pickup execution and scan exceptions.
6. Delivery execution and proof capture.
7. Branch/hub manifest, loading/unloading and transfer.
8. Returns pickup and handover.
9. COD collection receipt and cash sheet.
10. Cash handover and discrepancy record.
11. Vehicle assignment and inspections.
12. Incident/breakdown reporting.
13. Dispatch messages and announcements.
14. Offline queue, uploads and sync-conflict resolution.
15. Document/credential expiry and training.
16. Work history, settlements, expenses and support.
17. Privacy/location permission, language and preferences.

## 15. Core API/domain contracts to design before UI

- Driver profile and eligibility, shifts, vehicles and inspections.
- Assignment feed, route version, tasks, stops, scan events and custody handoffs.
- Parcel/package identity, manifest load/unload and exception events.
- Delivery proof schema/policy, private media lifecycle, verified outcomes and failed attempts.
- COD collection, receipt, cash sheet, handover acknowledgement, discrepancy case and settlement.
- Return authorization, pickup, receiving location and reverse custody.
- Offline sync batch with idempotency, event acknowledgements and conflict resolution.
- Device registration/revocation, on-duty location session and consent/policy.
- Push notification, dispatcher conversation and incident workflow.

## 16. Release acceptance gates

A first release is not “complete” until a real driver can sign in, start a shift, receive assignment, scan/load a package, work offline, record pickup/delivery or failure with required proof, hand over COD, sync without duplicates and finish the shift; authorized office users can reconcile every parcel/cash handoff; automated tests verify tenant/assignment isolation, invalid transitions and idempotency; and field testing covers low-connectivity Android devices.

## Current implementation gap

The repository currently has preliminary `FleetDriver`, `FleetVehicle`, `TransportTrip` and `DeliveryTask` backend structures. It does not yet deliver this mobile application, offline sync, route optimization, scanner UX, private proof media pipeline, push messaging, location session policy, COD cash-handover/reconciliation or the release acceptance gates above.
