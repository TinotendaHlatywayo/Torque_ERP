
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

| R-3.1-LIFECYCLE | 3.1 | DONE | Branch registration and lifecycle |
| R-3.1-TRANSITIONS | 3.1 | DONE | Approve, suspend, activate, archive transitions |
| R-3.1-SUSPENSION | 3.1 | DONE | Suspended branch write protection |
| R-3.1-TESTS | 3.1 | DONE | 172 backend tests / 824 assertions pass on MariaDB |

| R-3.2-MEMBERSHIP | 3.2 | DONE | Multi-branch membership |
| R-3.2-CONTEXT | 3.2 | DONE | Deterministic context switching |
| R-3.2-STALE | 3.2 | DONE | Stale membership rejection |
| R-3.2-HOST | 3.2 | DONE | Unauthorized host rejection |
| R-3.2-TESTS | 3.2 | DONE | 177 backend tests / 831 assertions pass on MariaDB |

| R-3.3-SETTINGS | 3.3 | DONE | Platform/branch settings |
| R-3.3-SCOPES | 3.3 | DONE | Setting scopes and precedence |
| R-3.3-AUDIT | 3.3 | DONE | Sensitive setting audit |
| R-3.3-TESTS | 3.3 | DONE | 181 backend tests / 843 assertions pass on MariaDB |

| R-3.4-CROSSBRANCH | 3.4 | DONE | Cross-branch reports |
| R-3.4-EXPORTS | 3.4 | DONE | Private scoped expiring exports |
| R-3.4-AUTH | 3.4 | DONE | Platform-only authorization |
| R-3.4-TESTS | 3.4 | DONE | 184 backend tests / 849 assertions pass on MariaDB |

| R-3.5-AUDIT | 3.5 | DONE | Audit coverage and redaction |
| R-3.5-IMMUTABILITY | 3.5 | DONE | Audit log immutability |
| R-3.5-RETENTION | 3.5 | DONE | Audit retention policy |
| R-3.5-SECURITY | 3.5 | DONE | Cross-branch security regression |
| R-3.5-TESTS | 3.5 | DONE | 189 backend tests / 859 assertions pass on MariaDB |

| R-4.6-RECOVERY | 4.6 | DONE | Interruption and recovery acceptance |
| R-4.6-EXACTLYONCE | 4.6 | DONE | Exactly-once business effect |
| R-4.6-TESTS | 4.6 | DONE | 194 backend tests / 875 assertions pass on MariaDB |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-5.1-SSR | 5.1 | DONE | Public landing page rendered by Laravel Blade; private ERP remains React/Vite |
| R-5.1-META | 5.1 | DONE | Canonical, description, Open Graph, locale, favicon and verified business JSON-LD |
| R-5.1-CRAWL | 5.1 | DONE | Environment-specific robots, public sitemap and SPA noindex |
| R-5.1-TESTS | 5.1 | DONE | 4 public SEO feature tests pass (22 assertions) |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-5.2-CMS | 5.2 | DONE | Platform-protected content studio with draft preview, publish and rollback |
| R-5.2-VERSIONS | 5.2 | DONE | Immutable snapshots; live version stays live until explicit publication |
| R-5.2-UPLOAD | 5.2 | DONE | JPEG/PNG/WebP allowlist, 8 MiB and 4000 px limits, private originals, safe responsive variants |
| R-5.2-MEDIA-ACCESS | 5.2 | DONE | Media preview requires platform permission; public media requires a published reference |
| R-5.2-TESTS | 5.2 | DONE | 5 focused tests pass (34 assertions) |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-5.3-PUBLISH | 5.3 | DONE | Branch products require customer-facing fields and explicit publication |
| R-5.3-PUBLIC-FIELDS | 5.3 | DONE | Public response omits SKU, internal description, cost, supplier and stock quantities |
| R-5.3-CATALOGUE | 5.3 | DONE | Server-rendered categories, search, pagination, clean product pages and internal links |
| R-5.3-SEO | 5.3 | DONE | Canonical/OG, Product and ItemList JSON-LD, search noindex, sitemap routes |
| R-5.3-BRANCH | 5.3 | DONE | Public query restricted to configured active branch |
| R-5.3-TESTS | 5.3 | DONE | 28 focused regressions pass (153 assertions) |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-5.4-HOLD | 5.4 | DONE | Public request creates an expiring hold only against available stock |
| R-5.4-RELEASE | 5.4 | DONE | Cancellation and scheduled expiry release held quantity idempotently |
| R-5.4-STOCK | 5.4 | DONE | Stock ledger prevents sales/corrections from consuming held units |
| R-5.4-PRIVACY | 5.4 | DONE | Encrypted contact details; private noindex status link; branch authorization |
| R-5.4-UI | 5.4 | DONE | Responsive reservation request/status pages and branch reservation screen |
| R-5.4-TESTS | 5.4 | DONE | Focused regression run: 48 tests / 319 assertions |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-5.5-PAYNOW | 5.5 | DONE | Paynow hosted checkout is configurable through server-only credentials |
| R-5.5-SIGNATURE | 5.5 | DONE | SHA-512 hashes validated on initiation response, callback and poll response |
| R-5.5-VERIFY | 5.5 | DONE | Signed result triggers Paynow poll; amount and merchant reference must match |
| R-5.5-IDEMPOTENCY | 5.5 | DONE | Duplicate and stale status events do not repeat or reverse stock holds |
| R-5.5-LATE | 5.5 | DONE | Payment after hold release is marked for manual review without reclaiming stock |
| R-5.5-NETWORK | 5.5 | DONE | Bounded timeouts/retries; ambiguous initiation is not retried automatically |
| R-5.5-TESTS | 5.5 | DONE | Focused regression run: 52 tests / 338 assertions |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-5.6-DELIVERY | 5.6 | DONE | Reservation-created and payment-confirmed deliveries are durable and unique per recipient/event |
| R-5.6-RECIPIENTS | 5.6 | DONE | Customers receive email only when supplied; only active branch users with reservation-management permission are notified |
| R-5.6-RETRY | 5.6 | DONE | Queued mail uses bounded retries/backoff and records sent, failed, or skipped delivery state without changing reservation/payment state |
| R-5.6-VERIFY | 5.6 | DONE | Phase 5 focused suite: 30 tests / 155 assertions; browser check passed on local Laravel server at desktop and 390px mobile |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-6.1-PROFILE | 6.1 | DONE | Branch-owned employee records are separate from login accounts and linked explicitly |
| R-6.1-LIFECYCLE | 6.1 | DONE | Hired/active/suspended/terminated/archived transitions are enforced; archives are read-only |
| R-6.1-ARCHIVE | 6.1 | DONE | Records are archived, never destructively deleted; terminated employees must be archived explicitly |
| R-6.1-PRIVACY | 6.1 | DONE | Sensitive fields require `branch.hr.sensitive.view` and are redacted in API and audit snapshots |
| R-6.1-ACCESS | 6.1 | DONE | Termination/archival deactivates branch membership and revokes tokens/sessions when no access remains |
| R-6.1-TESTS | 6.1 | DONE | 10 focused tests / 76 assertions pass on SQLite |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-6.2-LEAVE-TYPES | 6.2 | DONE | Branch-scoped leave types configure paid status, approval policy, annual limits and weekend counting |
| R-6.2-LEAVE-CALC | 6.2 | DONE | Inclusive working-day calculation rejects empty ranges and overlaps |
| R-6.2-LEAVE-LIMIT | 6.2 | DONE | Approved days are charged to the start-date year and annual limits are rechecked at approval |
| R-6.2-LEAVE-APPROVAL | 6.2 | DONE | Approval/rejection use `branch.hr.leave.approve`; rejection requires a note; configured auto-approval is supported |
| R-6.2-ATTENDANCE | 6.2 | DONE | One attendance record per branch/employee/date with upsert, future-date rejection and audit |
| R-6.2-DISCIPLINARY | 6.2 | DONE | Disciplinary records require sensitive HR permission, enforce transitions and block edits after closure |
| R-6.2-ATTACHMENTS | 6.2 | DONE | Private PDF/image attachments up to 5 MB; disciplinary upload/download requires sensitive permission |
| R-6.2-UI | 6.2 | DONE | `/app/hr` provides leave, attendance and disciplinary workflows with permission-aware actions |
| R-6.2-TESTS | 6.2 | DONE | 7 focused tests / 67 assertions; full suite 242 tests / 1179 assertions, 1 skipped |
| R-7.1-FITMENT | 7.1 | DONE | Vehicle make/model/year/engine fitment search via `product_fitments`; results include source/match type |
| R-7.1-OEM | 7.1 | DONE | OEM/cross-reference search via `product_oem_refs`; results include source/match type |
| R-7.1-EXACT | 7.1 | DONE | Exact SKU/barcode match prioritized in search results |
| R-7.1-IMPORT | 7.1 | DONE | JSON imports for fitments/OEM require `source`+`source_ref` + `Idempotency-Key`; idempotent logs recorded |
| R-7.1-TYPO | 7.1 | DONE | Deterministic typo tolerance (token len>=5, Levenshtein<=1); tests for positive + false positive + no-result |
| R-7.1-TENANT | 7.1 | DONE | Branch isolation enforced for search/imports and data |
| R-7.1-UI | 7.1 | DONE | `/app/catalogue-search` page with search, results, and import form (manage only) |
| R-7.1-TESTS | 7.1 | DONE | `CatalogueFitmentSearchTest` (8 tests) pass; full backend suite passes on MariaDB test DB (263 tests) |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-6.3-GRADES | 6.3 | DONE | Branch salary grades support monthly/hourly bases, overtime configuration and minor-unit amounts |
| R-6.3-ASSIGNMENTS | 6.3 | DONE | Effective-dated employee assignments, allowances, deductions and statutory rules with overlap checks |
| R-6.3-ENGINE | 6.3 | DONE | Deterministic proration, attendance-based hourly pay, overtime, statutory items, loans and half-up rounding |
| R-6.3-APPROVAL | 6.3 | DONE | Draft regeneration, approval locking, payslip snapshots and one-time loan/adjustment application |
| R-6.3-CORRECTIONS | 6.3 | DONE | Pending adjustments and reversals apply to later open periods without editing approved payslips |
| R-6.3-PERMISSIONS | 6.3 | DONE | Separate view/manage/approve permissions and branch isolation |
| R-6.3-UI | 6.3 | DONE | `/app/payroll` covers setup, periods/runs, loans and adjustments; browser runtime verification NOT RUN |
| R-6.3-DOCS | 6.3 | DONE | Salary basis, rounding, proration, statutory assumptions, unknown legal rates and production risk documented |
| R-6.3-TESTS | 6.3 | DONE | 6 focused tests / 69 assertions; final MariaDB suite 248 tests / 1251 assertions |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-6.4-ACCRUAL | 6.4 | DONE | Approved runs create one unique accrued payroll expense distinct from cash paid |
| R-6.4-PAYMENTS | 6.4 | DONE | Partial payment allocation, idempotency, account currency checks and overpayment protection |
| R-6.4-RECONCILIATION | 6.4 | DONE | Run totals, payslips, money movements, payments and accruals reconciled with PASS/FAIL checks |
| R-6.4-EXPORT | 6.4 | DONE | Payslip CSV export requires `branch.payroll.export` |
| R-6.4-TESTS | 6.4 | DONE | Focused payroll suite 7 tests / 90 assertions |

| R-6.5-TASKS | 6.5 | DONE | Task lifecycle, assignment, completion history and events |
| R-6.5-NOTIFICATIONS | 6.5 | DONE | Queued in-app/email deliveries with preferences, attempts and failures |
| R-6.5-UI | 6.5 | DONE | Task centre at `/app/tasks` with notification delivery state |
| R-6.5-TESTS | 6.5 | DONE | Focused task/notification suite 4 tests / 20 assertions |

| R-6.6-HR-PAYROLL | 6.6 | DONE | Executable acceptance fixture set for HR/payroll/finance/audit flow |
| R-6.6-ACCESS | 6.6 | DONE | Access revocation and branch boundary negative tests |
| R-6.6-BLOCKERS | 6.6 | BLOCKED | Statutory rates, tax filing and leave-pay effects require owner/professional confirmation |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-7.2-CATALOGUE | 7.2 | DONE | Branch service catalogue with view/manage permissions and API |
| R-7.2-STATE | 7.2 | DONE | Draft → estimated → approved → in_progress → completed with cancel/reopen preserving previous status |
| R-7.2-FIELDS | 7.2 | DONE | Customer, vehicle, complaint/diagnosis, technician, labour and part lines |
| R-7.2-APPROVAL | 7.2 | DONE | Approval reference/channel required before start; invalid transitions return 422 |
| R-7.2-STOCK | 7.2 | DONE | One canonical `job_consumption` movement per part line, idempotent by job + key + payload |
| R-7.2-CANCEL | 7.2 | DONE | Cancel/reopen do not reverse stock or invoices |
| R-7.2-INVOICE | 7.2 | DONE | Manual link to existing branch `Sale`; no auto-generated invoice |
| R-7.2-TENANT | 7.2 | DONE | Branch scoping and permission negative tests |
| R-7.2-UI | 7.2 | DONE | `/app/jobs` list/create/detail with state actions and line entry; browser runtime NOT RUN |
| R-7.2-WARRANTY | 7.2 | NOT RUN | Warranty references unsupported/not scoped in this stage |
| R-7.2-TESTS | 7.2 | DONE | `JobCardTest` 8 tests / 101 assertions; full MariaDB suite 271 tests / 1460 assertions |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-7.3-TRANSFER-STATE | 7.3 | DONE | Draft → dispatched → in_transit → received; cancellation only before receipt |
| R-7.3-CROSSBRANCH | 7.3 | DONE | Both source and destination branches require active membership and `branch.transfers.manage`; destination must be active |
| R-7.3-PRODUCT-MAPPING | 7.3 | DONE | Explicit source/destination product and base-unit IDs; no inferred SKU/unit conversion |
| R-7.3-STOCK-POSTING | 7.3 | DONE | One source movement at dispatch, one destination movement at receipt, deterministic keys, duplicate receipt rejected |
| R-7.3-CANCEL | 7.3 | DONE | Cancellation reverses only source dispatch; draft cancellation posts no movement; received transfers are terminal |
| R-7.3-STOCKTAKE | 7.3 | DONE | Locked expected balance, line reasons for variance, review then approve |
| R-7.3-STOCKTAKE-POSTING | 7.3 | DONE | One canonical adjustment per nonzero variance line at approval; duplicate approval rejected |
| R-7.3-PERMISSIONS | 7.3 | DONE | Transfer view/manage and stocktake view/manage/approve permissions with branch scoping |
| R-7.3-UI | 7.3 | DONE | `/app/transfers` list/create/detail/actions for transfers and stocktakes; browser runtime NOT RUN |
| R-7.3-ASSUMPTION | 7.3 | BLOCKED | Cross-branch base-unit equivalence requires owner/master-data confirmation; no conversion invented |
| R-7.3-TESTS | 7.3 | DONE | Focused tests 9 / 83 assertions; full MariaDB suite 280 tests / 1543 assertions |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-7.4-QUALIFY | 7.4 | DONE | Qualification computed from non-void committed sales minus documented returns; voided sales excluded; tests `test_qualification_uses_committed_sales_and_configurable_inactivity_window`, `test_voided_sales_are_excluded_and_documented_returns_reduce_qualification` |
| R-7.4-IDEMPOTENCY | 7.4 | DONE | Sale events idempotent by branch + event key + business dedupe hash; replay no-op and conflicting key returns 409; test `test_duplicate_sale_events_and_recalculation_do_not_double_count` |
| R-7.4-RECALC | 7.4 | DONE | Explicit audited recalculation endpoint/service upserts one qualification snapshot per branch/customer; repeated recalculation is stable |
| R-7.4-INACTIVITY | 7.4 | DONE | Inactivity window stored per branch in `loyalty_configs`, exposed via GET/PUT config API and applied at recalculation and candidate selection |
| R-7.4-STACKING | 7.4 | DONE | Loyalty + manual discount combined before existing floor-price/tax calculation; floor clamped, never breached; test `test_stacking_respects_floor_price_and_requires_authorized_approval` |
| R-7.4-APPROVAL | 7.4 | DONE | Combined discount above configured threshold requires `branch.loyalty.approve` plus reason and is audited; unauthorized/missing reason returns 422 |
| R-7.4-PRIVACY | 7.4 | DONE | Win-back requires explicit consent, supported channel and active channel; consent settable/audited via customer PATCH with invalid channel rejection; export is redacted stub with `delivery_status=NOT_RUN`; no automated message sent; tests `test_winback_requires_consent_and_active_channel_and_export_does_not_send`, `test_marketing_consent_can_be_recorded_through_customer_api` |
| R-7.4-PERMISSIONS | 7.4 | DONE | New branch permissions are view/manage/approve; branch-scoped routes and data; test `test_loyalty_data_and_permissions_are_branch_scoped` |
| R-7.4-UI | 7.4 | DONE | `/app/loyalty` with qualification list, config form, win-back candidates, recalculation and export stub; browser runtime NOT RUN |
| R-7.4-DELIVERY | 7.4 | NOT RUN | Actual win-back message delivery is BLOCKED until consent proof and a channel integration exist |
| R-7.4-TESTS | 7.4 | DONE | Focused suite 7 tests / 91 assertions; full MariaDB suite 287 tests / 1634 assertions |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-7.5-ACQUISITION | 7.5 | DONE | Acquisition date, `cost_minor`, currency, location, custodian, status and source type/id stored; `asset.acquired` audited; test `test_acquisition_links_existing_purchase_without_creating_duplicate_expense` |
| R-7.5-LINK-NO-DUPLICATE | 7.5 | DONE | Existing same-branch expense/supplier bill/money movement linked without creating a second expense or payment; test asserts expense and money movement counts unchanged |
| R-7.5-DUPLICATE-LINK | 7.5 | DONE | Duplicate source link rejected with 409 via service pre-check and `as_source_uniq`; test `test_duplicate_acquisition_link_is_rejected` |
| R-7.5-MAINTENANCE | 7.5 | DONE | Date, cost, provider, description and optional expense/payment links recorded and audited without new postings; test `test_maintenance_is_recorded_with_optional_links_without_new_postings` |
| R-7.5-DISPOSAL | 7.5 | DONE | Audited `asset.disposed` event with before/after snapshot plus cost, accumulated estimate, proceeds and estimated gain/loss; no automatic entries; duplicate disposal 409; test `test_disposal_is_audited_and_returns_estimated_financial_effect_without_posting` |
| R-7.5-DEPRECIATION | 7.5 | DONE | Estimate endpoints labelled ESTIMATE / BLOCKED with caller-supplied assumptions; no ledger/audit writes; test `test_depreciation_estimate_is_labelled_blocked_and_posts_nothing` |
| R-7.5-POLICY | 7.5 | BLOCKED | Accounting/tax use of depreciation remains BLOCKED until a policy and professional rules are confirmed; no defaults asserted |
| R-7.5-PERMISSIONS | 7.5 | DONE | `branch.assets.view/manage/dispose` added to permissions/roles and enforced on branch-scoped routes; test `test_assets_are_branch_scoped_and_permission_gated` |
| R-7.5-UI | 7.5 | DONE | `/app/assets` list/create/detail, maintenance form, disposal action and estimate display; browser runtime NOT RUN |
| R-7.5-TESTS | 7.5 | DONE | Focused suite 6 tests / 71 assertions; full MariaDB suite 293 tests / 1705 assertions |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-7.6-REORDER | 7.6 | DONE | Configurable sales window, lead time and days of cover; reorder point and suggestion formulas tested with known fixtures (40 units/10 days, 5 available => 15 units; 20-day window => 5 units) |
| R-7.6-AGEING | 7.6 | DONE | Last stock movement date plus configurable slow/dead thresholds; active/slow/dead/no-stock/unknown buckets and value asserted in `test_ageing_and_dead_stock_classification_use_last_movement_and_thresholds` |
| R-7.6-ABC | 7.6 | DONE | Revenue contribution over configurable window with configurable A/B cumulative thresholds; A/B/C classes and reclassification asserted in `test_abc_classification_uses_configurable_contribution_and_window` |
| R-7.6-DATA-QUALITY | 7.6 | DONE | Insufficient sales/cash history returns named warnings with null/zero values; mixed currency and future as-of warnings included; no invented demand, movement date or contribution |
| R-7.6-CASH-FORECAST | 7.6 | DONE | `FORECAST` label, assumptions, straight-line horizon projection, known bills and illustrative target asserted; null projections when cash events are insufficient |
| R-7.6-AUTHORITATIVE | 7.6 | BLOCKED | Forecast/target must not be used as authoritative finance statements; no seasonality, FX, growth or payment-timing model is asserted |
| R-7.6-SHARED-ENDPOINT | 7.6 | DONE | `GET /api/branches/{branch}/dashboard/v2` is branch-scoped, serves all charts from one `DashboardMetricsService` and reuses `FinanceMetricsService` |
| R-7.6-PERMISSIONS | 7.6 | DONE | Existing `branch.inventory.view` / `branch.finance.view` gate sections; restricted and cross-branch requests return 403; no new permission added |
| R-7.6-UI | 7.6 | DONE | `/app` dashboard v2 panels and `/app/inventory-intelligence` use accessible captioned tables, header scopes, bars with text alternatives and visible assumptions/warnings; browser runtime NOT RUN |
| R-7.6-TESTS | 7.6 | DONE | Focused suite 6 tests / 96 assertions; full MariaDB suite 299 tests / 1801 assertions |
| R-7.6-DOCS | 7.6 | DONE | Seven required docs updated with formulas, assumptions, acceptance report, commands and rollback notes; no new docs files |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-7.7-JOB | 7.7 | DONE | Test `test_job_labour_parts_invoice_and_retries_post_once_without_duplicate_stock_or_expense` proves labour/parts/estimate/approval/manual invoice link and one stock posting; duplicate part key replay/conflict handled |
| R-7.7-TRANSFER-STOCKTAKE | 7.7 | DONE | Test `test_transfer_receipt_and_stocktake_approval_move_stock_once_and_reject_duplicate_retries` proves source/destination movements, correction and duplicate 409 handling |
| R-7.7-LOYALTY-RETURN | 7.7 | DONE | Test `test_loyalty_discount_return_reports_and_dashboard_do_not_double_count_amounts` proves qualification, stacking/floor/approval, non-posting quotes, duplicate event and return effect |
| R-7.7-REPORTS-DASHBOARD | 7.7 | DONE | Same fixture asserts exact sales/stock report and dashboard v2 finance/inventory/forecast values from one shared endpoint |
| R-7.7-TENANT | 7.7 | DONE | Test `test_cross_branch_reads_actions_and_references_are_rejected` proves scoped reads, permission denial and rejection of cross-branch service/product/sale references |
| R-7.7-NO-DOUBLE-COUNT | 7.7 | DONE | Duplicate checkout/job part/loyalty event/return replay and duplicate transfer receipt/stocktake approval add no duplicate stock, sale, expense, money, discount or qualification rows |
| R-7.7-COGS | 7.7 | BLOCKED | No product/sale cost basis or cost layers exist; no COGS amount asserted and no cost invented |
| R-7.7-SOURCES | 7.7 | BLOCKED | Manual catalogue/OEM imports, no automated marketing channel, depreciation/forecast estimates, manual job invoices, unconfirmed base-unit equivalence and statutory/tax assumptions documented |
| R-7.7-BROWSER | 7.7 | NOT RUN | Browser runtime verification not run; frontend commands also not run because no frontend files changed |
| R-7.7-TESTS | 7.7 | DONE | Focused suite 4 tests / 180 assertions; full MariaDB suite 303 tests / 1981 assertions; no migrations or production code changes |
| R-7.7-DOCS | 7.7 | DONE | Seven required docs updated with acceptance report, commands, assumptions, blockers and rollback notes; no new docs files |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-8.1-REVIEW | 8.1 | DONE | Reviewed auth/session, authorization, tenant isolation, CSRF, XSS, SQL injection, mass assignment, uploads, secrets, rate limits, webhooks, errors, exports, jobs and audit; no production/destructive test used |
| R-8.1-CSRF | 8.1 | DONE | Removed `login`/`logout` CSRF exemptions in `backend/bootstrap/app.php`; SPA already calls `/sanctum/csrf-cookie` and sends XSRF header |
| R-8.1-NEGATIVE-TESTS | 8.1 | DONE | `SecurityRegressionTest.php` covers inactive user, revoked token, cross-branch access, missing permission, branch_id mass assignment, unknown field, public XSS, export permission/audit, invalid Paynow signature and login throttling |
| R-8.1-AUDIT | 8.1 | DONE | Export test asserts `report.exported` audit row with actor and null branch; existing immutability/redaction tests retained |
| R-8.1-WEBHOOK | 8.1 | DONE | Invalid Paynow signature returns 400 before provider HTTP, payment writes or status audit; existing `PaynowHash`/`hash_equals` retained |
| R-8.1-SECRETS | 8.1 | DONE | `.env.example` placeholders only; no real secret printed or committed; no custom crypto added |
| R-8.1-DEPENDENCIES | 8.1 | BLOCKED | Composer advisories (`laravel/framework`, `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing`) remain until dependency changes are approved |
| R-8.1-BROWSER | 8.1 | NOT RUN | Browser runtime verification unavailable; frontend checks not needed because no frontend file changed |
| R-8.1-TESTS | 8.1 | DONE | Focused suite 14 tests / 54 assertions; full MariaDB suite 312 tests / 2025 assertions; no migrations |
| R-8.1-DOCS | 8.1 | DONE | Six required docs updated with acceptance report, commands, assumptions, blockers and rollback notes; no new docs files |

| R-8.2-MEASURE | 8.2 | DONE | Baseline/after timings, query counts and bundle sizes recorded |
| R-8.2-OPTIMIZE | 8.2 | DONE | Sales N+1/payload fix, pagination caps, deferred search, route splitting and lazy charts |
| R-8.2-INFRA | 8.2 | DONE | No speculative infrastructure changes; residual production latency BLOCKED without production evidence |
| R-8.2-BROWSER | 8.2 | NOT RUN | Browser runtime profiling unavailable |
| R-8.3-META | 8.3 | DONE | Unique titles/descriptions <=160 chars, canonicals, robots meta, favicon, lang and 1200x630 OG metadata asserted on five rendered public pages |
| R-8.3-CRAWL | 8.3 | DONE | Environment-aware robots route, public-only sitemap, production private-path disallows; stale shadowing static robots.txt removed |
| R-8.3-STRUCTURE | 8.3 | DONE | One H1 first, logical headings, alt text, DOMXPath structural validation; CMS H1 fallback added for text/image-only pages |
| R-8.3-JSONLD | 8.3 | DONE | Valid schema.org JSON-LD with accurate types/URLs/prices; fixed Laravel 13 `@context` Blade directive mangling in four views and added ItemList url |
| R-8.3-NOINDEX | 8.3 | DONE | Search, reservation and private SPA/iframe mockup noindex; non-production robots disallow all |
| R-8.3-PRIVATE | 8.3 | DONE | Negative tests confirm no /app, /platform, /api, drafts, unreferenced media, other-branch products or private notes in public HTML/JSON-LD/sitemap |
| R-8.3-LINKS | 8.3 | DONE | Clean hyphenated slugs and public-only internal links asserted in rendered HTML |
| R-8.3-BROWSER | 8.3 | NOT RUN | Browser runtime and external W3C HTML validation unavailable per constraints; production crawler claims BLOCKED |
| R-8.3-TESTS | 8.3 | DONE | Focused 14 tests / 273 assertions; adjacent 20 tests / 111 assertions; full MariaDB suite 327 tests / 2342 assertions; no migrations |
| R-8.3-DOCS | 8.3 | DONE | Six required docs updated with PASS/FAIL/BLOCKED/NOT RUN criteria, commands, files, assumptions, risks and rollback notes; no new docs files |

| R-8.4-QUEUE | 8.4 | DONE | Notification jobs assert tries=3, timeout=30, backoff=[15,60], ShouldBeUnique and uniqueFor=300; duplicate dispatch creates one jobs row |
| R-8.4-NOTIFY | 8.4 | DONE | Mail failure logs retry_scheduled, failed() marks delivery failed with exception class only, logs notification.delivery.failed, leaves task unchanged and prints no recipient/SMTP secret |
| R-8.4-WEBHOOK | 8.4 | DONE | Signed Paynow callbacks deduped by unique webhook_events(provider,event_key); replay skips poll and state changes; concurrent collision and invalid signature/amount/poll URL paths logged or rejected |
| R-8.4-EXTERNAL | 8.4 | DONE | Paynow initiate/poll use clamped connect/read timeouts, max three poll attempts and one poll per callback; Http::fake prevents stray/real calls |
| R-8.4-HEALTH | 8.4 | DONE | GET /api/health reports database, queue size, failed-job count and correlation ID; 200 healthy, 503 degraded, no exception detail |
| R-8.4-LOGS | 8.4 | DONE | StructuredLogger writes event + correlation IDs to observability channel, recursively redacts secrets/PII and Throwable messages; tests assert secrets absent from log file |
| R-8.4-CORRELATION | 8.4 | DONE | X-Correlation-Id/X-Request-Id validated or generated, shared with logs, echoed on responses, copied into queue payloads and restored on JobProcessing |
| R-8.4-ALERT-HOOK | 8.4 | DONE | Queue::failing logs queue.job.failed with job/connection/queue and redacted exception class via FAILED_JOB_LOG_CHANNEL |
| R-8.4-PAGER | 8.4 | BLOCKED | External Slack/webhook paging not configured or exercised; no real notification sent |
| R-8.4-BREAKER | 8.4 | NOT APPLICABLE | Single bounded provider plus webhook dedupe and throttling do not justify persistent circuit-breaker state or new infrastructure |
| R-8.4-BROWSER | 8.4 | NOT RUN | Browser runtime verification unavailable per constraints; no live Paynow, SMTP or pager integration used |
| R-8.4-FRONTEND | 8.4 | NOT RUN | Frontend typecheck/lint/build not needed because no frontend file changed |
| R-8.4-TESTS | 8.4 | DONE | Focused/adjacent 31 tests / 171 assertions; full MariaDB suite 338 tests / 2419 assertions; one migration applied |
| R-8.4-DOCS | 8.4 | DONE | Six required docs updated with PASS/BLOCKED/NOT APPLICABLE/NOT RUN criteria, commands, files, assumptions, risks and rollback notes; no new docs files |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-8.5-SCHEDULE | 8.5 | PASS | Proposed nightly cron, backup scope and alerting documented in `docs/BACKUP_RESTORE.md`; no crontab was installed (live scheduling NOT RUN) |
| R-8.5-OFFSITE | 8.5 | BLOCKED | No off-site target is configured; target, TLS transfer, server-side encryption and retention ownership assigned to the infrastructure owner |
| R-8.5-ENCRYPTION | 8.5 | BLOCKED | GPG encryption path implemented but NOT RUN; demonstrated artifact used `encryption_at_rest=none` on local filesystem |
| R-8.5-RETENTION | 8.5 | PASS | 14 local daily, 90 off-site daily plus 12 monthly proposed, keep newest successful/restore-verified set; pruning automation BLOCKED until off-site exists |
| R-8.5-RPO-RTO | 8.5 | BLOCKED | Achievable RPO is up to 24h with nightly dumps; 15-minute target requires PITR/binlogs; 2-hour RTO proposed but unmeasured at production scale |
| R-8.5-RESPONSIBILITY | 8.5 | PASS | Infrastructure owner, on-call engineer, application owner and security owner responsibilities documented |
| R-8.5-DRILL | 8.5 | PASS | Synthetic source `torqueerp_stage85_source_test` backed up and restored into separate `torqueerp_stage85_restore_test`; 14 counts and 7 read-only workflow invariants PASS; second verify-only run PASS |
| R-8.5-NO-PROD | 8.5 | PASS | Guards refuse production-like names, shared test targets and source-as-target; negative commands recorded; production data/credentials untouched |
| R-8.5-RESTORE-GATE | 8.5 | PASS | Backup accepted only after demonstrated restore; manifest validation and checksum/size verification are mandatory before restore |
| R-8.5-SECRET-ROTATION | 8.5 | PASS | Rotation procedure documented for DB/off-site/GPG/Paynow/notification credentials with APP_KEY re-encryption gate; no real rotation executed (NOT RUN) |
| R-8.5-TESTS | 8.5 | DONE | Pint PASS, `migrate --force` Nothing to migrate, full MariaDB suite 338 tests / 2419 assertions; no focused PHPUnit test added because exact shell/manifest outputs are the acceptance evidence |
| R-8.5-FRONTEND | 8.5 | NOT RUN | No frontend file changed; browser runtime verification out of scope |
| R-8.5-DOCS | 8.5 | PASS | `docs/BACKUP_RESTORE.md` added plus six required docs updated with criteria, commands, outcomes, files, assumptions, risks and rollback notes |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-8.6-HOST | 8.6 | PASS | Actual host documented: PHP 8.4.27, Node 22.17.1, npm 10.9.2, Composer 2.7.1, Laravel 13.39.0; root-level `artisan` failure and correct `backend/` path recorded |
| R-8.6-WEB | 8.6 | PASS | nginx 1.24.0 active with unrelated default root; XAMPP Apache 2.4.58 installed but not running; no TMS vhost assumed |
| R-8.6-HTTPS | 8.6 | BLOCKED | No `:443` listener; TLS curl failed; production certificate/domain/redirect are placeholders |
| R-8.6-DB | 8.6 | PASS | MariaDB server 8.4.11 via `pdo_mysql`; local database name only, no credentials printed; production DB details placeholders |
| R-8.6-DEPS | 8.6 | PASS | Composer validate/install dry-runs and platform checks PASS; `npm ci --dry-run` PASS; no dependency changed |
| R-8.6-MIGRATIONS | 8.6 | PASS | `migrate --force` = `Nothing to migrate`; `migrate:status` Ran; no migration added |
| R-8.6-CACHE | 8.6 | PASS | `optimize:clear` verified locally; production `config:cache`/`optimize` documented as placeholders and NOT RUN |
| R-8.6-ASSETS | 8.6 | PASS | Exact build commands documented; existing `frontend/dist` observed; build NOT RUN because no frontend file changed (last known PASS Stage 8.3) |
| R-8.6-QUEUE | 8.6 | PASS | Bounded queue worker and drain commands documented; no TMS worker installed locally (installation NOT RUN) |
| R-8.6-SCHEDULER | 8.6 | PASS | `schedule:list` shows `reservations:expire` each minute; cron/unit command documented with placeholders, not installed |
| R-8.6-STORAGE | 8.6 | PASS | Local dirs `775 wayne:wayne`; production web-user procedure avoids `777`; private storage included in backup scope |
| R-8.6-SECRETS | 8.6 | PASS | Names/blank placeholders only; real values in ignored `.env`/secret store; no secret printed or committed |
| R-8.6-HEALTH | 8.6 | PASS | `/api/health` returned HTTP 200 `status=ok` with database/queue/failed-job checks and correlation IDs; degradation behaviour documented |
| R-8.6-LOGS | 8.6 | PASS | `laravel.log` and daily `observability-*.log` paths/commands verified; rotation/shipping BLOCKED |
| R-8.6-UPTIME | 8.6 | BLOCKED | No external uptime, TLS-expiry, queue/scheduler heartbeat or pager monitor is configured |
| R-8.6-ROLLBACK | 8.6 | PASS | Code/config/db/storage/frontend rollback and forward recovery documented; no production rollback executed (NOT RUN) |
| R-8.6-NO-SECRETS | 8.6 | PASS | All seven touched/new docs reviewed; no production credential, key, token or webhook value included |
| R-8.6-TESTS | 8.6 | NOT RUN | No code changed; Pint/PHPUnit and frontend checks not run; last known full MariaDB suite Stage 8.5 PASS, 338 tests / 2419 assertions |
| R-8.6-DOCS | 8.6 | PASS | `docs/DEPLOYMENT.md` added and six required docs updated with PASS/BLOCKED/NOT RUN criteria, commands, outcomes, files, assumptions, risks, verification and rollback notes |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-8.7-LANG-LIST | 8.7 | BLOCKED | No owner-selected languages in repo; only `en`/`sn` partial i18n foundation (11 keys each), `LocaleProvider` unmounted, 0 `t()` calls; six languages not claimed |
| R-8.7-LABELS | 8.7 | PASS | 63 unlabelled controls wrapped in `<Label>` across POS/tasks/payroll/HR; static scan reports 0 unlabelled labels |
| R-8.7-ERRORS | 8.7 | PASS | Login error id/role/describedby; ProductForm and 10 page banners switch alert/status; shared success state announces |
| R-8.7-KEYBOARD-FOCUS | 8.7 | PASS | Global `:focus-visible` ring uses `--ring`; shared Button/Input rings retained; runtime tab order NOT RUN |
| R-8.7-SEMANTICS | 8.7 | PASS | `CardTitle` and state headings are `<h2>`; 99 table headers have scope (0 missing); tab buttons expose `aria-pressed` |
| R-8.7-CONTRAST | 8.7 | PASS | Theme tokens replace low-contrast green/slate/emerald classes in shared states and dashboard/charts; other green banner classes remain a reported gap |
| R-8.7-RESPONSIVE | 8.7 | PASS | Five IntelligenceCharts tables wrapped in `overflow-x-auto`; existing page tables already scrollable; narrow grid blocks remain a reported gap |
| R-8.7-POS-TOUCH | 8.7 | PASS | Eight POS controls use `min-h-11` (44 px); real-device confirmation NOT RUN |
| R-8.7-TESTS | 8.7 | PASS | `npm run typecheck`, `npm run lint` (2 pre-existing warnings), `npm run build` PASS; backend tests NOT RUN because no backend change |
| R-8.7-RUNTIME | 8.7 | NOT RUN | Browser runtime, screen-reader and real-device verification unavailable in this environment |
| R-8.7-DOCS | 8.7 | PASS | Six required docs updated with criteria statuses, exact commands/outcomes, files, no migrations, assumptions, risks, verification and rollback notes |

| Req ID | Stage | Status | Notes |
|---|---|---|---|
| R-8.8-DEPS | 8.8 | PASS | `composer validate` valid, `composer install --dry-run` nothing to change, `npm ci --dry-run` PASS; no dependency file changed by this stage |
| R-8.8-CLEAN | 8.8 | PASS | New `torqueerp_stage88_acceptance_test`; `migrate:fresh --force` 69 migrations DONE, `migrate:status` PASS, migrations count 69; no production/shared DB touched |
| R-8.8-BUILD | 8.8 | PASS | Frontend typecheck exit 0, lint 0 errors/2 pre-existing warnings, build exit 0 (337.31 kB main, 111.38 kB gzip, 6.11 s) |
| R-8.8-FULL-TESTS | 8.8 | PASS | Full MariaDB suite on clean DB: 338 tests / 2419 assertions / 159361 ms |
| R-8.8-TENANT-SECURITY | 8.8 | PASS | Branch isolation, context, access control, branch users and security regression: 44 tests / 136 assertions |
| R-8.8-FINANCE | 8.8 | PASS | Financial reconciliation plus adjacent finance/report suites: 23 tests / 129 assertions; no double-counting regression |
| R-8.8-SEO | 8.8 | PASS | Public SEO, catalogue, CMS and reservation suites: 34 tests / 385 assertions |
| R-8.8-OFFLINE | 8.8 | PASS | Offline recovery implemented and verified: `SyncRecoveryTest` + `SyncTest`, 5 tests / 16 assertions |
| R-8.8-PERFORMANCE | 8.8 | PASS local / BLOCKED prod | Bounded perf/sales suites 23 tests / 176 assertions; Stage 8.2 measurements retained (80.98→24.89 ms, 34→4 queries); production-like traffic not measured |
| R-8.8-RESTORE | 8.8 | PASS drill | Stage 8.5 manifest valid, 2 artifacts checksum PASS, 14 counts + 7 invariants PASS, `RESTORE VERIFICATION PASS` read-only |
| R-8.8-A11Y-MOBILE | 8.8 | PASS static / NOT RUN runtime | 0 unlabelled labels, 99 headers with scope, 8 POS 44 px targets, focus-visible present; browser/screen-reader/device checks NOT RUN |
| R-8.8-LANGUAGE | 8.8 | BLOCKED | No owner language list; en/sn 11 keys each, LocaleProvider unmounted, 0 `t()` calls |
| R-8.8-PRODUCTION-HOST | 8.8 | BLOCKED | Local health HTTP 200; HTTPS probe 000 / `SSL_ERROR_SYSCALL`; no production vhost, TLS, secret store or real origin |
| R-8.8-OPERATIONS | 8.8 | BLOCKED / NOT RUN | No TMS worker/cron, uptime monitor or pager webhook; failed-job paging file-only |
| R-8.8-ADVISORIES | 8.8 | BLOCKED | `composer audit --locked` exit 4: laravel/framework 1, symfony/http-foundation 4, http-kernel 1, routing 1 advisories; no dependency change approved |
| R-8.8-LEGAL-ASSUMPTIONS | 8.8 | BLOCKED | Statutory/tax/payroll, depreciation, COGS and forecast assumptions unconfirmed by owner/advisers |
| R-8.8-BROWSER | 8.8 | NOT RUN | No browser/DevTools, screen reader, live provider or mobile device verification |
| R-8.8-OVERALL | 8.8 | PARTIAL / BLOCKED | No executed criterion failed, but G5-G12 remain open; NOT production-ready per `docs/RELEASE_CHECKLIST.md` |
| R-8.8-DOCS | 8.8 | PASS | New `docs/RELEASE_CHECKLIST.md` with release gates, exact commands, verification and rollback plan; seven required docs updated; no code, test, dependency or migration change |
