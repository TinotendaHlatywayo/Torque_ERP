# Offline workflow classification

## Allowed offline
- POS sale capture (cart, price calculation, checkout intent)
- Limited product lookup (cached catalogue)
- Limited customer lookup (cached customers)
- Device outbox queue inspection

## Cached read-only
- Product list and details (stale-while-revalidate)
- Customer list and details
- Stock balances (last known)
- Payment accounts (last known)

## Queued (offline-capable, server-posted on sync)
- Checkout/sale posting
- Stock movements (opening, correction, sale)
- Customer creation
- Payment collection

## Online-only
- Branch registration and lifecycle
- User/role management
- Settings changes
- Report generation and exports
- Supplier bills and payments
- FX rate entry
- Document template management
- Platform administration
- Authentication and session management

## Sync envelope (version 1)
Every queued operation includes:
- `operation_id` (client-generated UUID)
- `device_id` (registered device identity)
- `branch_id` (server-verified membership)
- `actor_id` (authenticated user)
- `client_time` (ISO 8601, client clock)
- `server_time` (ISO 8601, server clock, set on receipt)
- `payload_version` (integer, current: 1)
- `idempotency_key` (branch-scoped, unique)
- `operation_type` (checkout, stock_movement, customer_create, collection)
- `payload` (operation-specific data)
- `status` (queued, uploading, accepted, rejected, conflict, manual_review)

## Server authorization
- Every sync operation is authorized using the authenticated user's branch membership.
- Client totals are never trusted; the server recalculates all prices, taxes and stock.
- Duplicate operation IDs return the prior result without re-posting.
