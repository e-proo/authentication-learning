# Initial data model

## Identity and tenant boundary

- `users`: global identity and authentication.
- `organizations`: a company, merchant, carrier, logistics provider, or other business actor.
- `branches`: operational locations belonging to one organization.
- `memberships`: the user's access relationship with an organization and optionally a branch.
- `roles` and `permissions`: reusable authorization definitions.
- `membership_roles`: roles granted in the scope of a membership.
- `role_permissions`: permissions granted to a role.
- `audit_logs`: trace of sensitive and relevant actions.

The composite foreign key from `memberships(organization_id, branch_id)` to `branches(organization_id, id)` prevents a membership from selecting a branch belonging to a different organization.

## Next schema slices

Commerce: stores, products, variants, categories, price lists, stock balances, immutable stock movements, reservations.

Order-to-delivery: orders, order items, address/price snapshots, status history, fulfillments, packages, shipments, shipment events, delivery tasks, proof of delivery.

Finance: invoices, invoice items, payments, refunds, settlements, reconciliation records.

AI: action definitions, runs, tool calls, approvals, evaluations, model usage and cost records.
