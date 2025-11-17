-- Mock data for FlexNet admin/user workflows
INSERT INTO roles (name) VALUES ('admin') ON DUPLICATE KEY UPDATE name=name;

INSERT INTO admins (full_name, email, password_hash, is_active)
VALUES ('Flex Admin', 'admin@flexnet.test', '$2a$10$QryyE9BqF7hG5NtWCXFjvu1.7tXnSwRC9KD8yk5LO90yWsIe4SA9C', 1)
ON DUPLICATE KEY UPDATE email=email;

INSERT INTO admin_roles (admin_id, role_id)
SELECT a.id, r.id FROM admins a CROSS JOIN roles r
WHERE a.email='admin@flexnet.test' AND r.name='admin'
ON DUPLICATE KEY UPDATE admin_id=admin_id;

INSERT INTO locations (name, code, address_line1, city, region, is_active)
VALUES
 ('Citadel Complex Holdings', 'CITADEL-YDE-01', 'Avenue 12', 'Yaounde', 'Centre', 1),
 ('Green Valley Residence', 'GREEN-DLA-02', 'Boulevard 5', 'Douala', 'Littoral', 1)
ON DUPLICATE KEY UPDATE name=name;

INSERT INTO households (location_id, apartment_label, primary_full_name, phone_msisdn, email, login_identifier, pin_hash, is_active, subscription_status)
VALUES
  ((SELECT id FROM locations WHERE code='CITADEL-YDE-01' LIMIT 1), 'Room 12', 'Sunyin Elisbrown', '679690703', 'user1@test.com', 'CITADEL-12', '$2a$10$3jq32MxLqT2PgKzlVA6xruC6pe.UhnUVtpa61JryKYEGE7Jo8Dryi', 1, 'ACTIVE'),
  ((SELECT id FROM locations WHERE code='GREEN-DLA-02' LIMIT 1), 'Apt 4B', 'Jane Doe', '677123456', 'user2@test.com', 'GREEN-4B', '$2a$10$3jq32MxLqT2PgKzlVA6xruC6pe.UhnUVtpa61JryKYEGE7Jo8Dryi', 1, 'PENDING')
ON DUPLICATE KEY UPDATE phone_msisdn=phone_msisdn;

INSERT INTO plans (name, price_xaf, duration_days, is_active)
VALUES ('Standard', 25000, 30, 1)
ON DUPLICATE KEY UPDATE name=name;

INSERT INTO subscriptions (household_id, plan_id, status, start_date, end_date, last_action)
SELECT h.id, p.id, 'ACTIVE', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'ACTIVATE'
FROM households h CROSS JOIN plans p
WHERE h.login_identifier IN ('CITADEL-12', 'GREEN-4B')
ON DUPLICATE KEY UPDATE status='ACTIVE';

INSERT INTO payments (household_id, subscription_id, plan_id, provider, channel, currency_code, amount_xaf, external_id, provider_txn_id, provider_status, status)
SELECT h.id, s.id, p.id, 'FAPSHI', 'MTN_MOMO', 'XAF', p.price_xaf, CONCAT('TEST-', h.id, '-', UNIX_TIMESTAMP()), CONCAT('TXN-', h.id), 'SUCCESS', 'SUCCESS'
FROM households h
JOIN subscriptions s ON s.household_id = h.id
JOIN plans p ON p.id = s.plan_id
ON DUPLICATE KEY UPDATE provider_status='SUCCESS';
