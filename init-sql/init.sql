-- KSF Database Initialization
-- This script runs on first MariaDB container start

-- Create additional databases for FA and WP
CREATE DATABASE IF NOT EXISTS ksf_fa;
CREATE DATABASE IF NOT EXISTS ksf_wp;

-- Grant privileges
GRANT ALL PRIVILEGES ON ksf_fa.* TO 'ksf_user'@'%';
GRANT ALL PRIVILEGES ON ksf_wp.* TO 'ksf_user'@'%';
FLUSH PRIVILEGES;

-- FrontAccounting Sample Tables (for testing)
USE ksf_fa;

-- Sample Customers (Debtors)
INSERT INTO debtors_master (debtor_no, name, address1, address2, city, state, zip, country, email, phone, created_at, updated_at)
VALUES 
  (1, 'Test Customer One', '123 Main St', 'Suite 100', 'Anytown', 'ST', '12345', 'USA', 'test1@example.com', '555-0101', NOW(), NOW()),
  (2, 'Test Customer Two', '456 Oak Ave', '', 'Springfield', 'IL', '67890', 'USA', 'test2@example.com', '555-0102', NOW(), NOW()),
  (3, 'Acme Corp', '789 Industry Blvd', '', 'Metro City', 'NY', '10001', 'USA', 'acme@example.com', '555-0103', NOW(), NOW())
ON DUPLICATE KEY UPDATE name=name;

-- Sample Sales Orders
INSERT INTO sales_orders (order_no, debtor_no, ord_date, delivery_date, total, status)
VALUES
  (1, 1, CURDATE(), CURDATE() + INTERVAL 7 DAY, 150.00, 'Completed'),
  (2, 2, CURDATE() - INTERVAL 3 DAY, CURDATE() + INTERVAL 4 DAY, 275.50, 'In Process'),
  (3, 3, CURDATE() - INTERVAL 10 DAY, CURDATE() - INTERVAL 3 DAY, 500.00, 'Completed')
ON DUPLICATE KEY UPDATE order_no=order_no;

-- Sample Invoices (debtor_trans type=10)
INSERT INTO debtor_trans (trans_no, type, debtor_no, reference, trans_date, due_date, total, ov_discount, due_value)
VALUES
  (1001, 10, 1, 'INV-2024-001', CURDATE() - INTERVAL 30 DAY, CURDATE(), 150.00, 0, 0),
  (1002, 10, 2, 'INV-2024-002', CURDATE() - INTERVAL 15 DAY, CURDATE(), 275.50, 0, 275.50),
  (1003, 10, 3, 'INV-2024-003', CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 25 DAY, 500.00, 25.00, 475.00)
ON DUPLICATE KEY UPDATE trans_no=trans_no;

-- Support Tickets Tables
CREATE TABLE IF NOT EXISTS fa_st_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_number VARCHAR(30) UNIQUE,
  subject VARCHAR(255),
  description TEXT,
  type VARCHAR(20) DEFAULT 'Question',
  priority VARCHAR(20) DEFAULT 'Medium',
  status VARCHAR(20) DEFAULT 'New',
  debtor_no INT DEFAULT NULL,
  contact_id INT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO fa_st_tickets (ticket_number, subject, description, type, priority, status, debtor_no, created_by)
VALUES
  ('TKT-20240101-001', 'Need help with order', 'I cannot find my order status', 'Question', 'Medium', 'New', 1, 'test1@example.com'),
  ('TKT-20240101-002', 'Product not working', 'The product I received does not work', 'Issue', 'High', 'In Progress', 2, 'test2@example.com'),
  ('TKT-20240101-003', 'Feature request', 'Can you add export to CSV?', 'Request', 'Low', 'New', 3, 'acme@example.com')
ON DUPLICATE KEY UPDATE subject=subject;

-- Notes Table
CREATE TABLE IF NOT EXISTS fa_st_tickets_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  note TEXT,
  note_type VARCHAR(20) DEFAULT 'Comment',
  created_by VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Warranty Products Table
CREATE TABLE IF NOT EXISTS fa_wm_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku_id VARCHAR(30) UNIQUE,
  provider_type VARCHAR(20) DEFAULT 'Manufacturer',
  provider_name VARCHAR(100),
  term_type VARCHAR(20) DEFAULT 'Fixed',
  term_months INT DEFAULT 12,
  coverage_details TEXT,
  cost_to_provide DECIMAL(15,2) DEFAULT 0,
  max_claims INT DEFAULT 1,
  max_value_per_claim DECIMAL(15,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO fa_wm_products (sku_id, provider_type, provider_name, term_type, term_months, cost_to_provide, max_claims)
VALUES
  ('WARR-BASIC-1Y', 'Manufacturer', 'Basic Industries', 'Fixed', 12, 25.00, 1),
  ('WARR-PREM-2Y', 'Wholesaler', 'Premium Corp', 'Fixed', 24, 45.00, 2),
  ('WARR-EXT-3Y', 'Retailer', 'Extended Services', 'Fixed', 36, 75.00, 3)
ON DUPLICATE KEY UPDATE sku_id=sku_id;

-- Update sequence
ALTER TABLE debtors_master AUTO_INCREMENT = 4;
ALTER TABLE fa_st_tickets AUTO_INCREMENT = 10;