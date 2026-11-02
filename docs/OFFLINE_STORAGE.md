# Offline storage and device lifecycle

## Strategy
- Use the browser IndexedDB API directly for the POS outbox and cached read models.
- Dexie is not currently installed; native IndexedDB keeps dependencies minimal.
- Schema versioning is handled by IndexedDB `onupgradeneeded`.

## Local data
- Outbox operations (queued, uploading, accepted, rejected, conflict)
- Cached product/customer/stock snapshots for offline lookup
- Device identity and registration state

## Minimization
- Do not store passwords, tokens or full payment card data locally.
- Store only branch-scoped product/customer/stock snapshots needed for POS.
- Clear local data on logout or device revocation.

## Device lifecycle
- Each browser/device registers a device ID.
- Revoked devices are rejected by the server on sync.
- Shared POS devices must log out between users; local outbox is cleared on logout.

## Schema upgrade
- Increment the IndexedDB version on schema changes.
- Migrate records in `onupgradeneeded`; never drop data silently.

## Browser restart / interrupted write
- Outbox writes are transactional per operation.
- On restart, queued operations remain and are retried.
