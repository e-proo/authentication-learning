# Merchant/Shipper Portal and Customer Portal — Product Specification

**Status: Specified; the public customer experience is not implemented end to end.** This document defines two separate user-facing products, not admin-dashboard tabs.

# A. Merchant / Shipper Portal

## Product purpose
A business self-service application for retailers, ecommerce merchants, wholesalers and contract shippers to create/manage shipments, request pickup, track fulfillment, manage billing and settle balances.

## Merchant product modules
- Business onboarding, legal/contact details, verification/compliance documents where required, operating addresses, branches and users.
- Team membership and scoped roles: owner, booking operator, warehouse packer, finance viewer, support/claims and API integrator.
- Serviceability/rate quote by origin/destination, service level, dimensions/weight, value, COD and contract.
- Single booking, bulk booking, CSV/XLSX import preview/validation, error correction, labels, package/manifest/print batches.
- Pickup request calendar, address/instructions, assigned pickup status and failed pickup/re-schedule policy.
- Shipment/order board with filters, package detail, event timeline, promise/ETA freshness, failed delivery, return and claims state.
- Address book, sender locations, recipient contacts and privacy-controlled saved data.
- COD expected, collected, handed over, settled, withheld/disputed and payable statements with itemized fee/adjustment logic.
- Invoices, credit/debit notes, billing profile, payment history and downloadable statements.
- Returns request, reason, eligible item/package scope, pickup booking and status without directly restocking goods.
- Reports: shipment volume, service/route, delivery success, returns, claim rate, cost, COD receivable and settlement aging.
- API keys scoped to tenant and actions, webhook endpoints/secrets, idempotency guidance, usage/error logs and connector documentation.
- Integration connectors to ecommerce or ERP platforms through documented adapters; connectors must show sync health and failure queue.
- Support tickets, claims, SLA and attached evidence, while keeping private driver/customer information masked.

## Merchant safety rules
- A merchant sees only its contracted organization/accounts and shipments. Every search/export/attachment must enforce tenant scope.
- Contracted rates and historical quote/invoice snapshots are immutable; changes apply according to effective dates.
- Bulk import validates all rows, explains errors and prevents duplicate creation on retry.
- API keys are shown once, hashed/rotatable, scoped and revocable; webhooks are signed/retried/idempotent.
- COD amounts and settlement calculations are derived server-side from accepted events/contracts, not browser-submitted totals.

# B. Customer Portal and Tracking

## Product purpose
A mobile-first experience for sender/recipient to understand a shipment, choose available delivery preferences and request help without exposing internal logistics systems.

## Customer product modules
- Secure tracking lookup using unguessable reference/token and rate limits; authenticated account as an optional feature.
- Clear milestone timeline, promised delivery window, current estimate and time/source freshness.
- Delivery instructions and preferred service slot where supported; reschedule request subject to route/capacity and contract rules.
- Approved recipient/alternate-recipient authorization or branch pickup code with expiry and verification.
- Notification preferences and consent for SMS, email, WhatsApp or push only when configured provider and local policies allow.
- Delivery attempt/failure explanation and allowed next actions: reschedule, address clarification, pickup point, contact support or return request.
- Return request with eligible shipment/package, reason, required form/photos and policy result; human review where needed.
- Customer support case and conversation history, contact details, accessible Arabic RTL and English LTR.
- Safe delivery feedback/rating after a verified completed event.
- Public tracking page can show only curated milestones and carefully scoped data; it must not expose full internal event payload, private proofs, driver location history, unrelated packages or private notes.

## Customer experience and accessibility
- Simple mobile-first, low-bandwidth page; avoid requiring app installation to track a parcel.
- Arabic RTL as a first-class layout and English LTR as a complete alternate locale.
- Accessible contrast, readable status labels, keyboard/screen-reader support, error recovery and contact alternatives.
- Use clear source/freshness for ETA; explain that it is an estimate, not a guaranteed time unless a service promise says so.
- When provider tracking is stale or unavailable, state last updated time and offer support instead of fabricating live data.

# C. Shared workflow and integrations
- Both portals consume canonical order/package/shipment services through permissioned APIs.
- Merchant commands include quote/book/bulk import/pickup/return/claim; customer commands include preferences, reschedule request, pickup authorization and support.
- Commands validate current state, contract/ownership, eligible service and idempotency on the server.
- Tracking events come from trusted internal scans or verified integrations, not client-submitted free text.
- Notification preference, consent, provider status, retry and complaint/opt-out behavior must be auditable.
- Private proof is available only through an authorized purpose-scoped flow; public URLs never expose raw file storage.

## Acceptance gates
- Merchant creates a shipment, imports a batch with duplicate/error reporting, requests pickup, downloads a label and sees verified status events.
- Merchant cannot read another tenant's shipments, invoice, attachments or settlement data through direct ID guessing, search or export.
- Customer can track securely, reschedule a valid eligible delivery, request support and see a delayed/stale state honestly.
- Invalid/stale tracking tokens, rate-limit abuse, duplicate commands and unauthorized return requests are rejected.
- Provider outage leaves an explicit pending state and retry mechanism; it does not create false tracking events or customer promises.
