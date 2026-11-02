# Sync API

## Endpoints
- `POST /api/branches/{branch}/sync/upload` — upload a bounded batch of operations.
- `GET /api/branches/{branch}/sync/status?device_id=` — inspect queued/processed operations.

## Retry and error classification
- `retryable`: transient server/network failure; client may retry with same idempotency key.
- `validation`: invalid payload; client must fix and resubmit with a new operation ID.
- `permission`: actor lacks permission; requires authorization change.
- `stale_version`: client payload version is outdated; refresh and resubmit.
- `conflict`: server state conflicts with client intent; requires manual resolution.
- `manual_review`: operation needs human review before posting.

## Idempotency
- Duplicate `operation_id` or `idempotency_key` returns the prior result without re-posting.
- Server recalculates all totals and stock; client totals are never trusted.
