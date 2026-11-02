# Sync conflicts

## Conflict types
- `stale_price`: product price changed since the client cached it.
- `stock_shortage`: server stock is lower than the offline sale quantity.
- `customer_duplicate`: a similar customer was created on the server.
- `duplicate_sale`: the same sale was already posted.
- `stale_version`: client payload version is outdated.

## Policy
- Server stock ledger is authoritative.
- Offline sales may not oversell known stock; if stock is insufficient, the sale is rejected.
- Never silently overwrite financial or inventory events.
- Conflicts require manual resolution; the Sync Center surfaces them.
- Resolution is audited.
