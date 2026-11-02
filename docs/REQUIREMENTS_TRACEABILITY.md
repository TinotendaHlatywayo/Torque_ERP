
| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-0.4-SCHEMA | 0.4 | DONE | Foundation migrations with FKs, uniques, ULID, UTC timestamps, soft deletes, document sequences |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-0.5-AUTH | 0.5 | DONE | Breeze API + Sanctum, login/logout/session flow available |
| R-0.5-TENANT | 0.5 | DONE | Server-side branch context, default-deny policy, cross-branch negative test added |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-0.6-UI | 0.6 | DONE | Responsive shells, reusable components, loading/empty/error/success/denied states |
| R-0.6-A11Y | 0.6 | DONE | Keyboard focus, ARIA labels, skip link, semantic structure |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-0.7-SKELETON | 0.7 | DONE | Public placeholder, login, platform landing, branch workspace wired with routing and auth |
| R-0.7-TESTS | 0.7 | DONE | Migrations, tenant isolation tests, regression tests passed |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-1.1-ROLES | 1.1 | DONE | Fixed branch role catalogue; unknown roles grant no permissions |
| R-1.1-PERMISSIONS | 1.1 | DONE | Default-deny branch permissions; platform permissions are separate and not assignable to branch memberships |
| R-1.1-MEMBERSHIP | 1.1 | DONE | Branch user list/create/update/remove with audit entries and last-admin protection |
| R-1.1-TENANT | 1.1 | DONE | Route branch/membership server resolution; negative cross-branch tests pass |
| R-1.1-REVOCATION | 1.1 | DONE | Permissions re-read per request; losing last membership deletes tokens and sessions |
| R-1.1-UI | 1.1 | DONE | Branch users/roles page with loading, error, empty and permission-denied states |
| R-1.1-TESTS | 1.1 | DONE | 34 backend tests passing on SQLite and MariaDB (dedicated test DB), including direct API/cross-branch/revocation cases; frontend typecheck/lint/build pass |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-1.2-CATALOGUE | 1.2 | DONE | Branch-owned products with SKU/barcode, price, category, base unit, active/archive and stock foundation |
| R-1.2-CATEGORIES | 1.2 | DONE | Category CRUD, per-branch slugs, parent validation, cycle guard and archive semantics |
| R-1.2-UNITS | 1.2 | DONE | Unit CRUD, uppercase codes, decimal policy, per-branch uniqueness and archive semantics |
| R-1.2-CONVERSION | 1.2 | DONE | Explicit alternate units and conversion factors through the product base unit; inactive/unknown units rejected |
| R-1.2-MONEY | 1.2 | DONE | Integer minor-unit prices with ISO 4217 exponents and major-unit API input/output |
| R-1.2-STOCK | 1.2 | DONE | `stock_levels` zero-balance foundation created; movement ledger deferred to Stage 1.3 |
| R-1.2-TENANT | 1.2 | DONE | Composite branch-safe FKs, scoped route bindings, per-branch SKU/barcode/unit uniqueness and cross-branch tests |
| R-1.2-UI | 1.2 | DONE | `/app/products` with search/filter/pagination, product/category/unit management, alternate units and conversion tool |
| R-1.2-TESTS | 1.2 | DONE | 87 backend tests / 310 assertions pass on SQLite and MariaDB; frontend typecheck/build pass with only 2 pre-existing lint warnings |

| R-1.3-LEDGER | 1.3 | DONE | Canonical `stock_movements` ledger with branch/product/quantity/unit/source/reason/actor/time |
| R-1.3-BALANCE | 1.3 | DONE | Transaction-safe `stock_levels` projection updated under row lock |
| R-1.3-IDEMPOTENCY | 1.3 | DONE | Branch-scoped idempotency key replay/conflict handling |
| R-1.3-NEGATIVE | 1.3 | DONE | Negative stock rejected for opening, correction, sale and reversal |
| R-1.3-CONCURRENCY | 1.3 | DONE | MariaDB concurrent sale/oversell test passes |
| R-1.3-TENANT | 1.3 | DONE | Cross-branch stock reads, postings and reversals rejected |
| R-1.3-TESTS | 1.3 | DONE | 102 backend tests / 446 assertions pass on MariaDB; SQLite blocked by missing pdo_sqlite driver |

| R-1.4-CUSTOMERS | 1.4 | DONE | Branch-safe customer records with lookup/create |
| R-1.4-DUPLICATES | 1.4 | DONE | Duplicate warnings without silent merging |
| R-1.4-ARCHIVE | 1.4 | DONE | Customer archive semantics and status filters |
| R-1.4-PERMISSIONS | 1.4 | DONE | `branch.customers.view` and `branch.customers.manage` |
| R-1.4-TENANT | 1.4 | DONE | Cross-branch customer isolation tests |
| R-1.4-TESTS | 1.4 | DONE | 109 backend tests / 490 assertions pass on MariaDB |

| R-1.5-PRICE | 1.5 | DONE | Domain-level price calculation with quantities and snapshots |
| R-1.5-DISCOUNT | 1.5 | DONE | Configurable allowed discount threshold |
| R-1.5-FLOOR | 1.5 | DONE | Floor price enforcement |
| R-1.5-TAX | 1.5 | DONE | Tax hook after net price |
| R-1.5-APPROVAL | 1.5 | DONE | Approval path and audit with actor/reason |
| R-1.5-TESTS | 1.5 | DONE | 119 backend tests / 529 assertions pass on MariaDB |

| R-1.6-CHECKOUT | 1.6 | DONE | Transactional POS checkout |
| R-1.6-IDEMPOTENCY | 1.6 | DONE | Duplicate submission/retry protection |
| R-1.6-PAYMENTS | 1.6 | DONE | Separate sale/payment states and split payments |
| R-1.6-RECEIPT | 1.6 | DONE | Unique receipt and print view |
| R-1.6-STOCK | 1.6 | DONE | Stock movement posted with checkout rollback |
| R-1.6-TESTS | 1.6 | DONE | 126 backend tests / 568 assertions pass on MariaDB |

| R-1.7-HISTORY | 1.7 | DONE | Sales history and detail |
| R-1.7-VOID | 1.7 | DONE | Authorized void with stock reversal |
| R-1.7-RETURNS | 1.7 | DONE | Return workflow foundation |
| R-1.7-PERMISSIONS | 1.7 | DONE | Permission and duplicate void/return tests |
| R-1.7-TESTS | 1.7 | DONE | 133 backend tests / 613 assertions pass on MariaDB |

| R-1.8-E2E | 1.8 | DONE | End-to-end product→stock→customer→sale→receipt→history |
| R-1.8-DUPLICATE | 1.8 | DONE | Duplicate checkout protection |
| R-1.8-CONCURRENCY | 1.8 | DONE | Oversell protection |
| R-1.8-SECURITY | 1.8 | DONE | Unauthorized override and tenant isolation |
| R-1.8-MIGRATION | 1.8 | DONE | Migration and build verification |
| R-1.8-TESTS | 1.8 | DONE | 138 backend tests / 649 assertions pass on MariaDB |

| R-2.2-ACCOUNTS | 2.2 | DONE | Payment account management |
| R-2.2-MOVEMENTS | 2.2 | DONE | Immutable money movements |
| R-2.2-TRANSFERS | 2.2 | DONE | Linked paired transfer movements |
| R-2.2-REVERSALS | 2.2 | DONE | Money movement reversals |
| R-2.2-MULTICURRENCY | 2.2 | DONE | Multi-currency account isolation |
| R-2.2-TESTS | 2.2 | DONE | 144 backend tests / 672 assertions pass on MariaDB |

| R-2.3-EXPENSES | 2.3 | DONE | Expenses with incurred/paid dates |
| R-2.3-BILLS | 2.3 | DONE | Bills to pay and ageing |
| R-2.3-PAYMENTS | 2.3 | DONE | Bill payment allocation |
| R-2.3-COGS | 2.3 | DONE | Stock purchase vs COGS documented |
| R-2.3-TESTS | 2.3 | DONE | 148 backend tests / 693 assertions pass on MariaDB |

| R-2.4-CREDIT | 2.4 | DONE | Credit sale/invoice support |
| R-2.4-COLLECTIONS | 2.4 | DONE | Receipt allocation and collections |
| R-2.4-AGEING | 2.4 | DONE | Receivable ageing |
| R-2.4-DOUBLECOUNT | 2.4 | DONE | Prevent double-counting sales in Money In |
| R-2.4-TESTS | 2.4 | DONE | 152 backend tests / 719 assertions pass on MariaDB |

| R-2.5-METRICS | 2.5 | DONE | Finance metrics formulas |
| R-2.5-FX | 2.5 | DONE | FX history and conversions |
| R-2.5-TAX | 2.5 | DONE | Configurable tax rate |
| R-2.5-TESTS | 2.5 | DONE | 155 backend tests / 737 assertions pass on MariaDB |

| R-2.6-TEMPLATES | 2.6 | DONE | Template registry and versioning |
| R-2.6-VALIDATION | 2.6 | DONE | Placeholder validation and missing checks |
| R-2.6-PREVIEW | 2.6 | DONE | Template preview |
| R-2.6-QR | 2.6 | DONE | QR verification target |
| R-2.6-PRINT | 2.6 | DONE | Print CSS |
| R-2.6-TESTS | 2.6 | DONE | 161 backend tests / 754 assertions pass on MariaDB |

| R-2.8-RECONCILIATION | 2.8 | DONE | Financial reconciliation acceptance |
| R-2.8-DOUBLECOUNT | 2.8 | DONE | No double-counting of receiving, purchases, expenses, COGS |
| R-2.8-TESTS | 2.8 | DONE | 169 backend tests / 804 assertions pass on MariaDB |
