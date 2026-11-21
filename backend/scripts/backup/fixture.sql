INSERT INTO platforms (id, name, slug, currency_code, created_at, updated_at) VALUES
('01J85PLATFORM0000000000000', 'Backup Drill Platform', 'backup-drill-platform', 'USD', '2026-11-21 05:00:00', '2026-11-21 05:00:00');

INSERT INTO users (id, name, email, password, is_active, is_platform_admin, created_at, updated_at) VALUES
(9001, 'Backup Drill User', 'backup-drill@example.invalid', 'drill-placeholder-not-a-login-secret', 1, 0, '2026-11-21 05:00:00', '2026-11-21 05:00:00');

INSERT INTO branches (id, platform_id, name, code, slug, status, approved_at, timezone, currency_code, host, created_at, updated_at) VALUES
('01J85BRANCH000000000000000', '01J85PLATFORM0000000000000', 'Backup Drill Branch', 'DRILL-BR-01', 'backup-drill-branch', 'active', '2026-11-21 05:00:00', 'Africa/Harare', 'USD', NULL, '2026-11-21 05:00:00', '2026-11-21 05:00:00');

INSERT INTO units (id, branch_id, code, name, symbol, allow_decimal, is_active, created_at, updated_at) VALUES
('01J85UNIT00000000000000000', '01J85BRANCH000000000000000', 'EA', 'Each', 'ea', 0, 1, '2026-11-21 05:00:00', '2026-11-21 05:00:00');

INSERT INTO categories (id, branch_id, parent_id, name, slug, description, is_active, created_at, updated_at) VALUES
('01J85CATEGORY0000000000000', '01J85BRANCH000000000000000', NULL, 'Drill Spares', 'drill-spares', 'Synthetic backup drill category', 1, '2026-11-21 05:00:00', '2026-11-21 05:00:00');

INSERT INTO products (id, branch_id, category_id, base_unit_id, sku, name, description, barcode, selling_price_minor, currency_code, track_stock, is_active, created_at, updated_at) VALUES
('01J85PRODUCT00000000000000', '01J85BRANCH000000000000000', '01J85CATEGORY0000000000000', '01J85UNIT00000000000000000', 'DRILL-SPARE-001', 'Drill Spare', 'Synthetic backup drill product', NULL, 1000, 'USD', 1, 1, '2026-11-21 05:00:00', '2026-11-21 05:00:00');

INSERT INTO customers (id, branch_id, name, phone, email, address, notes, is_active, created_at, updated_at) VALUES
('01J85CUSTOMER0000000000000', '01J85BRANCH000000000000000', 'Drill Customer', '0000000000', 'drill-customer@example.invalid', 'Drill Address', 'Synthetic backup drill customer', 1, '2026-11-21 05:00:00', '2026-11-21 05:00:00');

INSERT INTO stock_levels (id, branch_id, product_id, quantity_on_hand, quantity_reserved, reorder_level, created_at, updated_at) VALUES
('01J85STOCKLEVEL00000000000', '01J85BRANCH000000000000000', '01J85PRODUCT00000000000000', 8.0000, 0.0000, 2.0000, '2026-11-21 05:00:00', '2026-11-21 05:00:00');

INSERT INTO stock_movements (id, branch_id, product_id, unit_id, movement_type, source, source_id, quantity, unit_quantity, unit_conversion_factor, balance_after, reason, idempotency_key, request_hash, opening_marker, reverses_movement_id, actor_id, created_at) VALUES
('01J85MOVOPEN00000000000000', '01J85BRANCH000000000000000', '01J85PRODUCT00000000000000', '01J85UNIT00000000000000000', 'opening', 'fixture', 'DRILL-OPEN-001', 10.0000, 10.0000, 1.000000, 10.0000, 'Drill opening stock', 'drill-open-001', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'opening', NULL, 9001, '2026-11-21 05:01:00'),
('01J85MOVSALE00000000000000', '01J85BRANCH000000000000000', '01J85PRODUCT00000000000000', '01J85UNIT00000000000000000', 'sale', 'sale', '01J85SALE00000000000000000', -2.0000, -2.0000, 1.000000, 8.0000, 'Drill sale', 'drill-sale-001', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', NULL, NULL, 9001, '2026-11-21 05:02:00');

INSERT INTO sales (id, branch_id, customer_id, sale_number, status, payment_status, currency_code, subtotal_minor, discount_minor, tax_minor, total_minor, paid_minor, idempotency_key, request_hash, actor_id, created_at, updated_at) VALUES
('01J85SALE00000000000000000', '01J85BRANCH000000000000000', '01J85CUSTOMER0000000000000', 'DRILL-SALE-0001', 'posted', 'paid', 'USD', 2000, 0, 0, 2000, 2000, 'drill-checkout-001', 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc', 9001, '2026-11-21 05:02:00', '2026-11-21 05:02:00');

INSERT INTO sale_items (id, branch_id, sale_id, product_id, product_name, sku, unit_id, unit_code, quantity, unit_price_minor, discount_minor, tax_minor, total_minor, currency_code, created_at) VALUES
('01J85SALEITEM0000000000000', '01J85BRANCH000000000000000', '01J85SALE00000000000000000', '01J85PRODUCT00000000000000', 'Drill Spare', 'DRILL-SPARE-001', '01J85UNIT00000000000000000', 'EA', 2.0000, 1000, 0, 0, 2000, 'USD', '2026-11-21 05:02:00');

INSERT INTO payments (id, branch_id, sale_id, method, amount_minor, currency_code, reference, status, idempotency_key, request_hash, actor_id, created_at) VALUES
('01J85PAYMENT00000000000000', '01J85BRANCH000000000000000', '01J85SALE00000000000000000', 'cash', 2000, 'USD', 'DRILL-PAY-001', 'recorded', 'drill-payment-001', 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd', 9001, '2026-11-21 05:02:00');

INSERT INTO receipts (id, branch_id, sale_id, receipt_number, issued_at, print_count, created_at, updated_at) VALUES
('01J85RECEIPT00000000000000', '01J85BRANCH000000000000000', '01J85SALE00000000000000000', 'DRILL-RCPT-0001', '2026-11-21 05:02:00', 0, '2026-11-21 05:02:00', '2026-11-21 05:02:00');

INSERT INTO audit_logs (id, branch_id, user_id, action, entity_type, entity_id, ip_address, user_agent, `before`, `after`, created_at) VALUES
('01J85AUDIT0000000000000000', '01J85BRANCH000000000000000', 9001, 'drill.fixture.seeded', 'branch', '01J85BRANCH000000000000000', NULL, 'backup-drill', NULL, '{"synthetic":true}', '2026-11-21 05:00:00');
