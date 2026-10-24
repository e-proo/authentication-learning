# Finance foundation

This slice provides invoice snapshots and an offline payment reconciliation workflow. It is a foundation, not a complete accounting system.

## Invoices

- One invoice per organization/order in the initial model.
- Invoice headers are copied from the confirmed order totals.
- Invoice items preserve descriptions, SKUs, currency, unit price, quantity, discounts, taxes, and line totals.
- Amounts are stored as integer minor units; do not use floating-point amounts in money calculations.

## Payment attempts

A payment is created with pending status. Creating a payment record does not verify that funds were received. Pending and paid payment attempts reserve part of the remaining invoice balance to prevent recording over the invoice amount.

Idempotency keys are scoped to the organization and reused only for an identical request. A retry with a different request body is rejected.

Supported methods at this stage:
- cash: may be confirmed by a user with finance management permission.
- bank_transfer: manual confirmation requires a reconciliation reference.
- cash_on_delivery: confirmation is allowed only after the order has a delivered status.
- online: may be recorded as pending but cannot be confirmed manually. A provider adapter, signed webhook verification, replay protection, and reconciliation process must be implemented before enabling live online payment.

On successful offline confirmation, a payment event and sanitized audit record are appended, the invoice paid total/status is recomputed from paid payments, and the order payment status is updated. Partial payments are supported.

## Not implemented

- Double-entry immutable accounting ledger
- Refunds, chargebacks, payment reversals, and provider webhooks
- Currency conversion and provider fee allocation
- Merchant/courier settlements, payouts, reconciliation imports, and approval separation of duties
- Tax calculation and legal invoice-number rules for a specific jurisdiction

Before production, add authorization/audit tests, payment-provider integration tests, accounting-ledger invariants, and a migration/runtime verification run against PostgreSQL.
