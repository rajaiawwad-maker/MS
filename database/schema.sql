-- DJ RAK Inventory & Rental Management System
-- Database Schema
-- Version: 1.0
-- Date: 2026-08-29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+03:00";

CREATE DATABASE IF NOT EXISTS `dj_rak_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dj_rak_system`;

-- --------------------------------------------------------
-- Roles Table
-- --------------------------------------------------------
CREATE TABLE `roles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Permissions Table
-- --------------------------------------------------------
CREATE TABLE `permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `permission_name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `perm_name` (`permission_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Role Permissions Table
-- --------------------------------------------------------
CREATE TABLE `role_permissions` (
  `role_id` INT(11) NOT NULL,
  `permission_id` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Users Table
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role_id` INT(11) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Categories Table
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Item Types Table
-- --------------------------------------------------------
CREATE TABLE `item_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `default_rental_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `quantity` INT(11) NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `item_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Inventory Items Table
-- --------------------------------------------------------
CREATE TABLE `inventory_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `item_type_id` INT(11) NOT NULL,
  `serial_number` VARCHAR(150) DEFAULT NULL,
  `asset_code` VARCHAR(100) DEFAULT NULL,
  `purchase_date` DATE DEFAULT NULL,
  `status` ENUM('Available','Booked','Out for Event','Maintenance','Damaged','Lost','Retired') NOT NULL DEFAULT 'Available',
  `location` VARCHAR(200) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `item_type_id` (`item_type_id`),
  KEY `asset_code` (`asset_code`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Clients Table
-- --------------------------------------------------------
CREATE TABLE `clients` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `alt_phone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_name` (`name`),
  KEY `client_phone` (`phone`),
  KEY `client_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Expense Types Table
-- --------------------------------------------------------
CREATE TABLE `expense_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `fixed_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `description` TEXT DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exp_type_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Bookings Table
-- --------------------------------------------------------
CREATE TABLE `bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_number` VARCHAR(30) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `date_from` DATE NOT NULL,
  `date_to` DATE NOT NULL,
  `event_start_time` TIME DEFAULT NULL,
  `event_end_time` TIME DEFAULT NULL,
  `location` VARCHAR(300) NOT NULL,
  `quoted_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `dj_rak_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('Draft','Quotation','Confirmed','Change Requested','Event Completed','Closed','Canceled') NOT NULL DEFAULT 'Draft',
  `payment_status` ENUM('Not Collected','Partially Collected','Fully Collected','Canceled') NOT NULL DEFAULT 'Not Collected',
  `internal_notes` TEXT DEFAULT NULL,
  `customer_confirmation_token` VARCHAR(100) DEFAULT NULL,
  `customer_confirmed_at` DATETIME DEFAULT NULL,
  `customer_response` ENUM('Confirmed','Change Requested','Declined') DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_number` (`booking_number`),
  UNIQUE KEY `confirm_token` (`customer_confirmation_token`),
  KEY `client_id` (`client_id`),
  KEY `date_from` (`date_from`),
  KEY `date_to` (`date_to`),
  KEY `status` (`status`),
  KEY `payment_status` (`payment_status`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Booking Items Table
-- --------------------------------------------------------
CREATE TABLE `booking_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `item_type_id` INT(11) NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 1,
  `rental_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `item_type_id` (`item_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Payments Table
-- --------------------------------------------------------
CREATE TABLE `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` VARCHAR(100) DEFAULT NULL,
  `reference` VARCHAR(200) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `payment_date` (`payment_date`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Expenses Table
-- --------------------------------------------------------
CREATE TABLE `expenses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `expense_type_id` INT(11) NOT NULL,
  `date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `payment_method` VARCHAR(100) DEFAULT NULL,
  `reference` VARCHAR(200) DEFAULT NULL,
  `booking_id` INT(11) DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `expense_type_id` (`expense_type_id`),
  KEY `date` (`date`),
  KEY `booking_id` (`booking_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Audit Logs Table
-- --------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(100) NOT NULL,
  `entity_id` INT(11) DEFAULT NULL,
  `old_value` LONGTEXT DEFAULT NULL,
  `new_value` LONGTEXT DEFAULT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `user_agent` VARCHAR(300) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `entity_type` (`entity_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- System Settings Table
-- --------------------------------------------------------
CREATE TABLE `system_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(150) NOT NULL,
  `setting_value` LONGTEXT DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Foreign Keys
-- --------------------------------------------------------
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `rp_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rp_perm_fk` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

ALTER TABLE `users`
  ADD CONSTRAINT `user_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

ALTER TABLE `item_types`
  ADD CONSTRAINT `it_cat_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inv_it_fk` FOREIGN KEY (`item_type_id`) REFERENCES `item_types` (`id`);

ALTER TABLE `bookings`
  ADD CONSTRAINT `bk_client_fk` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `bk_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `booking_items`
  ADD CONSTRAINT `bi_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bi_item_fk` FOREIGN KEY (`item_type_id`) REFERENCES `item_types` (`id`);

ALTER TABLE `payments`
  ADD CONSTRAINT `pay_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pay_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `expenses`
  ADD CONSTRAINT `exp_type_fk` FOREIGN KEY (`expense_type_id`) REFERENCES `expense_types` (`id`),
  ADD CONSTRAINT `exp_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `exp_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

-- --------------------------------------------------------
-- Seed Data: Roles
-- --------------------------------------------------------
INSERT INTO `roles` (`name`, `description`) VALUES
('Administrator', 'Full access to all system functions.'),
('Booking User', 'Operational access for bookings and clients.'),
('Finance User', 'Financial operations access.'),
('Viewer', 'Read-only management access.');

-- --------------------------------------------------------
-- Seed Data: Permissions
-- --------------------------------------------------------
INSERT INTO `permissions` (`permission_name`, `description`) VALUES
('manage_users', 'Manage system users and permissions'),
('manage_setup', 'Manage categories, item types, expense types'),
('manage_inventory', 'Manage inventory items'),
('manage_clients', 'Add/edit clients'),
('view_clients', 'View client list'),
('create_bookings', 'Create new bookings'),
('edit_bookings', 'Edit existing bookings'),
('cancel_bookings', 'Cancel bookings'),
('view_bookings', 'View bookings list and details'),
('record_payments', 'Record payment transactions'),
('view_financials', 'View financial dashboards and reports'),
('view_dj_rak', 'View DJ RAK information'),
('manage_expenses', 'Add/edit/delete expenses'),
('view_expenses', 'View expense records'),
('view_reports', 'View and generate reports'),
('view_calendar', 'View booking calendar'),
('view_dashboard', 'View dashboard'),
('send_whatsapp', 'Send WhatsApp messages'),
('view_audit_logs', 'View audit logs'),
('manage_settings', 'Configure system settings'),
('override_inventory', 'Override inventory restrictions');

-- --------------------------------------------------------
-- Seed Data: Role Permissions (Administrator = all)
-- --------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM permissions;

-- Booking User permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2, 3), (2, 4), (2, 5), (2, 6), (2, 7), (2, 9), (2, 10),
(2, 13), (2, 15), (2, 16), (2, 17), (2, 18);

-- Finance User permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3, 5), (3, 9), (3, 10), (3, 11), (3, 12), (3, 13),
(3, 14), (3, 15), (3, 17);

-- Viewer permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(4, 5), (4, 9), (4, 11), (4, 14), (4, 15), (4, 16), (4, 17);

-- --------------------------------------------------------
-- Seed Data: Default Admin User (password: admin123)
-- --------------------------------------------------------
INSERT INTO `users` (`name`, `username`, `email`, `password_hash`, `role_id`, `phone`, `active`) VALUES
('System Administrator', 'admin', 'admin@djrak.com', '$2y$10$yL8kQftAWJEwmjqbgg92pe/pEVrDCEIRWaLbIsSeXZl87qWiRSunu', 1, '+966500000000', 1);

-- --------------------------------------------------------
-- Seed Data: Sample Categories
-- --------------------------------------------------------
INSERT INTO `categories` (`name`, `description`) VALUES
('Speakers', 'Main PA speakers and monitors'),
('Subwoofers', 'Low-frequency subwoofers'),
('DJ Controllers', 'DJ controllers and decks'),
('Mixers', 'Audio mixers'),
('Microphones', 'Wired and wireless microphones'),
('Lighting', 'Stage and event lighting'),
('Stands', 'Speaker, lighting, and equipment stands'),
('Cables', 'Audio, power, and data cables'),
('Accessories', 'Adapters, cases, and miscellaneous'),
('DJ Furniture', 'DJ booths, tables, and workstations');

-- --------------------------------------------------------
-- Seed Data: Sample Item Types
-- --------------------------------------------------------
INSERT INTO `item_types` (`category_id`, `name`, `description`, `default_rental_value`, `quantity`) VALUES
(1, 'JBL PRX812W', '12-inch 2-way powered speaker 1500W', 500.00, 4),
(1, 'JBL EON615', '15-inch 2-way powered speaker 1000W', 400.00, 2),
(2, 'JBL PRX818XLF', '18-inch powered subwoofer 1500W', 600.00, 4),
(2, 'RCF SUB 8004-AS', '18-inch high-power subwoofer', 700.00, 2),
(3, 'Pioneer XDJ-XZ', 'All-in-one DJ system for Rekordbox/Serato', 800.00, 1),
(3, 'Pioneer DDJ-1000', '4-channel DJ controller for Rekordbox', 600.00, 2),
(3, 'Pioneer CDJ-3000 (Pair)', 'Pair of professional DJ multi players', 1200.00, 1),
(4, 'Pioneer DJM-900NXS2', '4-channel professional DJ mixer', 500.00, 1),
(4, 'Allen & Heath ZED-16FX', '16-channel mixer with effects', 300.00, 1),
(5, 'Shure SM58 (Wireless)', 'Wireless handheld microphone system', 200.00, 4),
(5, 'Shure SM58 (Wired)', 'Wired dynamic vocal microphone', 50.00, 6),
(6, 'LED Par Can (RGBW)', '18x10W RGBW LED par light', 80.00, 12),
(6, 'Moving Head Beam', '230W 7R moving head beam light', 300.00, 4),
(6, 'Strobe Light', 'High-power LED strobe light', 100.00, 2),
(7, 'Speaker Stand (Adjustable)', 'Heavy-duty adjustable speaker stand', 30.00, 10),
(7, 'Lighting Truss (2m)', '2-meter aluminum lighting truss section', 50.00, 8),
(8, 'XLR Cable (10m)', '10-meter balanced XLR cable', 10.00, 20),
(8, 'Power Cable (5m)', '5-meter IEC power cable', 5.00, 30),
(9, 'DI Box (Active)', 'Active direct injection box', 20.00, 4),
(10, 'DJ Booth Table', 'Portable DJ booth/workstation table', 150.00, 2);

-- --------------------------------------------------------
-- Seed Data: Sample Expense Types
-- --------------------------------------------------------
INSERT INTO `expense_types` (`name`, `fixed_value`, `description`) VALUES
('Transportation', 0.00, 'Vehicle fuel, rental, and transport costs'),
('Fuel', 0.00, 'Fuel and gasoline expenses'),
('Maintenance', 0.00, 'Equipment maintenance and servicing'),
('Equipment Repair', 0.00, 'Repair costs for damaged equipment'),
('Staff', 0.00, 'Staff wages and labor payments'),
('Marketing', 0.00, 'Advertising and promotional expenses'),
('Storage', 0.00, 'Warehouse and storage rental'),
('Insurance', 0.00, 'Equipment and liability insurance'),
('Software', 0.00, 'Software subscriptions and licenses'),
('Other', 0.00, 'Miscellaneous expenses');

-- --------------------------------------------------------
-- Seed Data: Sample Clients
-- --------------------------------------------------------
INSERT INTO `clients` (`name`, `phone`, `alt_phone`, `email`, `address`, `notes`) VALUES
('ABC Events Management', '0501234567', '0507654321', 'contact@abcevents.com', 'Riyadh, Saudi Arabia', 'Regular corporate events client'),
('Mohammed Al-Saud', '0551112233', NULL, 'mohammed@email.com', 'Jeddah, Saudi Arabia', 'Wedding events'),
('Golden Palace Venue', '0532223344', '0112345678', 'bookings@goldenpalace.sa', 'Dammam, Saudi Arabia', 'Venue partnership - preferred client'),
('Sarah Wedding Planner', '0563334455', NULL, 'sarah@weddings.sa', 'Riyadh, Saudi Arabia', 'Plans 2-3 weddings per month'),
('Tech Summit Co.', '0584445566', NULL, 'events@techsummit.com', 'Riyadh, Saudi Arabia', 'Annual tech conference');

-- --------------------------------------------------------
-- Seed Data: System Settings
-- --------------------------------------------------------
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('company_name', 'DJ RAK Entertainment', 'Company display name'),
('company_phone', '+966 50 000 0000', 'Company contact phone'),
('company_email', 'info@djrak.com', 'Company contact email'),
('company_address', 'Riyadh, Saudi Arabia', 'Company address'),
('currency_code', 'JOD', 'Currency code'),
('currency_symbol', 'JOD', 'Currency symbol'),
('date_format', 'd/m/Y', 'Default date display format'),
('timezone', 'Asia/Riyadh', 'Default timezone'),
('booking_prefix', 'BK', 'Booking number prefix'),
('whatsapp_country_code', '966', 'Default country code for WhatsApp links (without +)');

COMMIT;
