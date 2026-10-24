# Finance, Cash Desk, COD and Settlement — Product Specification

**Status: Specified; current invoice and offline payment records are only an initial foundation.**

## Product boundary
A controlled finance product distinct from operational delivery. Drivers record collections; branches/cashiers acknowledge physical money; finance reconciles balances and authorizes settlement; accounting records the resulting financial postings. These are separate actors/events and must not be compressed into one editable payment status.

## 1. Financial master data
- Customer/merchant/shipper accounts, billing contacts, contract, credit limit, billing calendar and allowed settlement terms.
- Rate card/service fee, minimum charge, weight/distance/zone bands, COD fee, remote-area/handling surcharges, discounts and effective dates.
- Currency, tax rules, rounding, accounting periods, numbering schemes and locale configured per legal entity/market.
- Payment mode/provider, bank/cash account, payout destination, payment terms, settlement frequency and approval threshold.
- Versioned prices/fees snapshotted on shipment/order/invoice; retroactive contract changes cannot rewrite historic charges.

## 2. Invoices and receivables
- Shipment/service charges, invoice grouping by merchant/account/period, invoice lines with source shipment and pricing version.
- Draft/review/issued/partially paid/paid/overdue/void/credited states with permitted transitions and reasoned correction.
- Credit/debit note and refund/reversal workflow; never delete an issued financial record without trace.
- Payment allocation to one or many invoices, partial payments, overpayment/unapplied funds, currency/fee differences and write-off policy.
- Receivable ageing, client statement, reminder/escalation, disputed invoice and documented resolution.
- Invoice exports, tax/compliance formatting and document retention validated for the launch jurisdiction.

## 3. COD lifecycle
- At booking: expected COD amount, currency, collection conditions, payer/recipient and authorized payment modes.
- Driver assignment: snapshot expected COD due and payment policy to the work item; changes require server validation and trace.
- Collection: amount actually paid, currency, payment mode, receipt/reference, parcel/order, driver, timestamp and optional evidence.
- Partial collection, refusal, recipient absent and payment mismatch are explicit outcomes; never silently set order paid because driver tapped delivered.
- Driver cash sheet aggregates expected/collected amounts per shift/run/branch/currency while preserving each source transaction.
- Branch/cashier handover records counted amount, acknowledged amount, cashier/till, actor/time and evidence.
- Difference between collected and physically handed over becomes a discrepancy case; it cannot be erased by editing the collection.
- Merchant payable is calculated only under documented contractual/finance policy; fee, return, dispute and reserve adjustments are itemized.
- Settlement batch tracks included items, gross COD, charges, refunds/adjustments, net payable, approval, payout instruction, external reference and reconciliation.

## 4. Cash office and branch till
- Named cashier and terminal/till, opening float, shift start/end, authorized drawers, receipt numbering and payment modes.
- Counter payment receipt, refund/void reason and permission; no silent deletion/rewriting of receipt history.
- Driver handover queue, cash sheet verification, receipt/acknowledgement and unresolved discrepancy worklist.
- Cash movement: received from driver, counter receipts, bank deposit, transfer to another safe/account and closing cash.
- Expected vs counted cash, over/short, denomination count where useful, receipt reprint, supervisor approval and finance review.
- Separation of duties for making a collection, acknowledging it, approving a discrepancy and authorizing payouts.
- Daily close cannot be final while required handovers or material variances remain unresolved unless an authorized override with reason is recorded.

## 5. Payouts, payables and settlements
- Carrier/courier/partner payable and merchant payable, fees/commission, expenses, credits and disputes.
- Periodic, on-demand or threshold-based settlement batch; approval based on amount/risk and dual control.
- Payout instruction status: draft, reviewed, approved, submitted, provider-confirmed, failed, reversed and reconciled.
- Provider reference, verified webhook/signature, duplicate/replay protection and payment reconciliation.
- Bank/provider ledger import, match by reference/amount/currency/date, unmatched transactions, partial settlement and manual resolution.
- Financial reports by organization, branch, merchant, carrier, driver, currency and accounting period.
- Payouts must never be marked provider-confirmed from a UI click without verified external confirmation, unless a separately authorized offline/manual process is explicitly defined.

## 6. Ledger and accounting controls
- Future immutable double-entry ledger: balanced postings, account mapping, posting period, source event, transaction identity, currency, reversal and correction entries.
- Posting rules for receivable, COD cash in transit, cash at branch, bank deposit, merchant payable, carrier payable, fee revenue and refunds must be reviewed by finance experts.
- Enforce debit/credit balancing, idempotency, atomic posting, closed-period control and append-only correction/reversal.
- Financial source event, invoice, collection, handover, settlement and ledger transaction maintain reference links and audit evidence.
- Do not treat a payment-status field or an editable balance table as a trustworthy accounting ledger.

## 7. Fraud, risk and reconciliation
- Duplicate collection prevention, out-of-range amount detection, duplicate receipt/reference checks and stale collection policy.
- Reconcile trip assigned parcels vs deliveries vs COD due vs collected vs cashier receipt vs payout.
- Alerts for unusually late handover, repeated shortages, route/customer anomalies and manual adjustments above policy thresholds; alerts require human investigation.
- Permissioned adjustments with reason, evidence, approval and before/after view.
- No automated accusation/disciplinary result from an anomaly score alone.

## 8. Roles and permissions
Driver, cashier, branch supervisor, finance operator, settlement preparer, independent approver, auditor and platform admin need distinct scoped roles. A person must not approve their own high-risk financial change; enforce separation in APIs and workflows, not just menus.

## 9. Acceptance gates
1. Create COD due at booking, record driver collection and cash sheet.
2. Handover less than expected, open discrepancy, collect evidence, and require authorized independent review.
3. Receive handover at branch and daily-close the till without losing source transaction links.
4. Generate merchant settlement batch with itemized fees and verify approval separation.
5. Import a test bank/provider record, match/reject duplicate and leave ambiguous match in review.
6. Verify partial payments, refunds/reversals, closed-period protection, double-entry balance and tenant/branch isolation before claiming ledger readiness.

## Current gap
The repository currently includes invoice snapshots and basic offline payment/partial-payment records. It does not yet include an immutable double-entry ledger, driver cash sheet, branch cash drawer, cash handover/reconciliation, discrepancy cases, merchant/courier settlement or verified payment webhooks.
