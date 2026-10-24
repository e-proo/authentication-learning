# Catalog and inventory slice

## Catalog

A store belongs to one organization. Products retain an organization boundary and reference a store through a composite foreign key so a product cannot accidentally point to another organization's store. A product can have one or more SKU variants.

Store modes:
- `catalog`: listing, inquiry, or quote request without requiring in-platform checkout.
- `marketplace`: can enable cart/checkout and order processing according to organization policy.

## Inventory

- `inventory_balances` is the current aggregate per warehouse and SKU.
- `inventory_movements` is the traceable movement journal; use positive and negative quantity deltas with a meaningful movement type and reference.
- `reserved` represents stock allocated to uncompleted orders and may never exceed `on_hand`.
- `idempotency_key` helps avoid duplicate movements when integrations retry a request.

All stock-changing operations must be implemented in a transaction, lock the balance row, validate available stock, append a movement, and update the balance atomically. Do not mutate inventory from controllers directly. Movement records should be treated as append-only.
