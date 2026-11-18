-- MySQL Database Schema for Juwel Aquarium Maintenance Tracker
-- MINIMAL VERSION (No Views or Stored Procedures)
-- Compatible with shared hosting environments with limited privileges
-- Created: 2025-11-28

-- ================================================================
-- Create Database
-- ================================================================
CREATE DATABASE IF NOT EXISTS aquarium CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aquarium;

-- ================================================================
-- Table: maintenance_activities
-- Purpose: Stores all maintenance activity types and their frequencies
-- ================================================================
CREATE TABLE IF NOT EXISTS maintenance_activities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(10) UNIQUE NOT NULL,
    description VARCHAR(255) NOT NULL,
    frequency_weeks INT NOT NULL,
    is_filter_material BOOLEAN DEFAULT FALSE,
    material_group VARCHAR(50) NULL COMMENT 'Groups activities that share the same inventory (e.g., F and G both use "blauw_fijn")',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_material_group (material_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Table: maintenance_log
-- Purpose: Historical record of all maintenance activities performed
-- ================================================================
CREATE TABLE IF NOT EXISTS maintenance_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    activity_id INT NOT NULL,
    performed_date DATE NOT NULL,
    next_due_date DATE,
    days_until_due INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (activity_id) REFERENCES maintenance_activities(id) ON DELETE CASCADE,
    INDEX idx_performed_date (performed_date),
    INDEX idx_next_due_date (next_due_date),
    INDEX idx_activity_date (activity_id, performed_date DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Table: filter_inventory
-- Purpose: Track stock levels of filter materials
-- ================================================================
CREATE TABLE IF NOT EXISTS filter_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    material_group VARCHAR(50) NOT NULL COMMENT 'Material identifier (matches maintenance_activities.material_group)',
    material_name VARCHAR(100) NOT NULL COMMENT 'Display name for the material',
    current_stock INT NOT NULL DEFAULT 0,
    min_stock_alert INT DEFAULT 2,
    unit VARCHAR(50) DEFAULT 'stuks',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_material (material_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Table: inventory_transactions
-- Purpose: Track inventory changes over time (optional but recommended)
-- ================================================================
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    inventory_id INT NOT NULL,
    transaction_type ENUM('purchase', 'usage', 'adjustment') NOT NULL,
    quantity_change INT NOT NULL,
    stock_after INT NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (inventory_id) REFERENCES filter_inventory(id) ON DELETE CASCADE,
    INDEX idx_transaction_date (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Insert initial maintenance activities data
-- ================================================================
INSERT INTO maintenance_activities (code, description, frequency_weeks, is_filter_material, material_group, notes) VALUES
('A', 'watten', 1, TRUE, 'watten', NULL),
('B', 'Kool', 4, TRUE, 'kool', NULL),
('C', 'Nitrax', 8, TRUE, 'nitrax', NULL),
('D', 'Blauw grof', 13, TRUE, 'blauw_grof', NULL),
('E', 'Cirax', 52, TRUE, 'cirax', NULL),
('F', 'Blauw fijn', 39, TRUE, 'blauw_fijn', 'Eerste laag'),
('G', 'Blauw fijn', 52, TRUE, 'blauw_fijn', 'Tweede laag - shares inventory with F'),
('I', 'Profito+EasyCarbo', 1, FALSE, NULL, 'Water additives'),
('J', 'Schoonmaak', 1, FALSE, NULL, 'General cleaning'),
('K', 'Water verversing', 3, FALSE, NULL, 'Water change'),
('L', 'Water meting', 16, FALSE, NULL, 'Water testing'),
('M', 'Filter medium', 3, FALSE, NULL, NULL)
ON DUPLICATE KEY UPDATE code=code;

-- ================================================================
-- Initialize filter inventory for filter materials
-- Note: F and G both use 'blauw_fijn' so they share one inventory record
-- ================================================================
INSERT INTO filter_inventory (material_group, material_name, current_stock, min_stock_alert) VALUES
('watten', 'Watten', 0, 2),
('kool', 'Kool', 0, 2),
('nitrax', 'Nitrax', 0, 2),
('blauw_grof', 'Blauw grof', 0, 2),
('cirax', 'Cirax', 0, 2),
('blauw_fijn', 'Blauw fijn', 0, 2)
ON DUPLICATE KEY UPDATE material_group=material_group;

-- ================================================================
-- Database is ready to use!
-- ================================================================
-- The API (api.php) uses direct SQL queries instead of views/procedures
-- No additional database objects are required
