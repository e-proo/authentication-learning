# Shipment Visibility and Carrier Integration — Product Specification

**Status: Specified; existing shipment status endpoints are only a foundation.**

## Product boundary
Separate visibility/control product and integration layer that answers: where is each parcel/package now, what is the last verified event, what is the expected next milestone, is the ETA reliable, and what needs intervention? It serves internal control-room users, authorized partners, merchants and customer-safe tracking pages while hiding sensitive data by default.

## 1. Canonical shipment and parcel model
- Shipment can contain one or more packages/parcels; each package has a stable barcode/QR and lifecycle.
- Consignment/shipment reference, internal IDs, merchant/customer references, service, origin/destination, route/legs, promised window, current custody actor/location and last event.
- Package dimensions/weight, handling flags, COD due/collected state and document references where authorized.
- Parent/child relationship for split, consolidation, bag/cage/manifest/leg, without losing individual package lineage.
- Label/barcode version and duplicate/void history; never reissue a label invisibly for a different package.

## 2. Event normalization and chain of custody
- Event envelope: event ID, organization, shipment/package, event type, source system/provider, external reference, source occurrence time, server receive time, actor/device, location provenance, payload version and idempotency key.
- Event taxonomy for booked, label-created, accepted, received-at-branch, sorted, bagged/sealed, departed, arrived, received-at-hub, out-for-delivery, delivery-attempted, delivered, return-initiated, return-received, delayed, damaged, missing and cancelled.
- Internal and carrier-specific statuses map through versioned adapters; preserve raw provider payload securely for investigation without exposing it in public APIs.
- Authenticate external webhooks, verify signatures where supported, deduplicate by provider/event identity and reject replay/stale status regression according to policy.
- Distinguish observed event, inferred status, predictive ETA and manually imported record. Never invent a physical scan because a trip or route was completed.
- Corrections append compensating/correction events with author, reason and reference; original records remain auditable.

## 3. Tracking UI and permission tiers
- Internal control tower: multi-shipment search, event timeline, current custody, dwell time, missed milestone, stale provider data and exception queue.
- Merchant view: shipments, promised date, allowed events, customer-facing tracking links, recipient-safe details, export and claim status.
- Customer view: branded tracking page, simple milestones, delivery estimate with confidence/freshness, instructions/contact options, delivery preferences and safe exception messages.
- Partner/carrier view: only contracted shipments/legs and required booking/event data.
- Public tracking uses random unguessable tokens or equivalent safe authorization, rate limits, abuse prevention and field minimization; no sequential IDs or private driver GPS history.
- Delivery proof, full address, phone, COD or internal notes are not public by default.

## 4. ETA, schedules and exception control
- Promised window vs current ETA, source, last calculation time and confidence label.
- ETA inputs may include provider tracking, route plan, travel-time adapter, stop progress and dwell; the system must show when inputs are missing or stale.
- Late-risk rules by service/customer/region, missed scan milestone, excessive dwell, unexpected backtrack, long unassigned time and event gaps.
- Exception ownership, priority, SLA, notification/escalation, disposition, linked case and full resolution history.
- Customer-safe delay explanation only when a validated reason exists; don't reveal sensitive internal/partner information.

## 5. Carrier/integration registry
- Provider configuration with supported services, locations/countries, API/label/rate/track/return capabilities, quotas and billing.
- Credential reference in a secrets manager; never return secrets to browser/mobile clients or commit credentials.
- Contracted carrier account, service mapping, currency, surcharges, pickup availability, label formats and tracking event mapping.
- Webhook endpoint/key rotation, allowed source, signature validation, event queue, retry/dead-letter and reconciliation job.
- Connector health, last success, failure rate, latency, quota, credential expiry and manual fallback.
- Integration should be capability-driven: a carrier may support tracking but not rates, labels or returns. Don't assume all features exist for all carriers.

## 6. Labels, rate shopping, pickup and return APIs
- Address validation, package type, dimensions/weight and serviceability before rating.
- Compare configured rates by cost, delivery promise, service and constraints; display quote freshness and surcharges.
- Create/cancel shipment, buy/void label, multi-package labels, manifest/batch, schedule pickup and create return label only when provider supports them.
- Webhook and polling reconciliation with idempotency, backoff, circuit breakers and audit.
- Preserve quote and actual charges for finance audit; compare quoted/billed charges where invoices exist.

## 7. Documents and proof
- Private proof storage, evidence type, SHA/hash if applicable, actor, capture time, server receipt time, location provenance, verification state and retention.
- Permissioned display of signature/photo and signed document; public link is short-lived and purpose-scoped.
- Upload validation, MIME/content inspection, file size limits, access logging, retention and deletion policy.
- Keep internal tracking event separate from the proof document itself; missing proof is an explicit exception.

## 8. Reporting
- On-time by promised window/service/lane/carrier, ETA accuracy, scan compliance, shipment dwell by facility, missing milestones, repeat exceptions, failed first attempt, return rate and support inquiry rate.
- Provider data completeness/freshness, webhook errors, unmatched external IDs, deduplication and reconciliation backlog.
- All dashboards define metric/timezone/status mapping and drill through to underlying source events.

## 9. Acceptance gates
1. Track a multi-package shipment with append-only event history and role-safe views.
2. Ingest duplicate, stale, invalid-signature and out-of-order provider events; verify no duplicated or regressed lifecycle.
3. Show ETA with source/freshness and no false confidence when provider data is stale.
4. Enforce public tracking privacy and prevent access to proof/other customer records.
5. Generate a label/manifest through a mocked provider contract and fail safely when capability/configuration is absent.
6. Verify rate/label/webhook retries, idempotency, dead-letter visibility and cross-tenant isolation.

## Current gap
Current shipment endpoints store internal shipment status events, but package-level manifests, generalized carrier adapters, verified webhook intake, customer-safe tracking UI, provider rate/label flows, predictive ETAs and visibility control tower are not implemented end to end.
