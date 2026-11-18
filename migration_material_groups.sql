-- Migration script to add material_group support
-- This allows F and G to share the same inventory
-- Run this AFTER the initial schema has been set up

-- ================================================================
-- Step 1: Add material_group column to maintenance_activities
-- ================================================================
ALTER TABLE maintenance_activities 
ADD COLUMN material_group VARCHAR(50) NULL COMMENT 'Groups activities that share the same inventory' AFTER is_filter_material,
ADD INDEX idx_material_group (material_group);

-- ================================================================
-- Step 2: Update existing activities with material_group values
-- ================================================================
UPDATE maintenance_activities SET material_group = 'watten' WHERE code = 'A';
UPDATE maintenance_activities SET material_group = 'kool' WHERE code = 'B';
UPDATE maintenance_activities SET material_group = 'nitrax' WHERE code = 'C';
UPDATE maintenance_activities SET material_group = 'blauw_grof' WHERE code = 'D';
UPDATE maintenance_activities SET material_group = 'cirax' WHERE code = 'E';
UPDATE maintenance_activities SET material_group = 'blauw_fijn' WHERE code = 'F';
UPDATE maintenance_activities SET material_group = 'blauw_fijn' WHERE code = 'G';

-- ================================================================
-- Step 3: Create new filter_inventory table structure
-- ================================================================
CREATE TABLE filter_inventory_new (
    id INT PRIMARY KEY AUTO_INCREMENT,
    material_group VARCHAR(50) NOT NULL COMMENT 'Material identifier',
    material_name VARCHAR(100) NOT NULL COMMENT 'Display name',
    current_stock INT NOT NULL DEFAULT 0,
    min_stock_alert INT DEFAULT 2,
    unit VARCHAR(50) DEFAULT 'stuks',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_material (material_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Step 4: Migrate inventory data (merge F and G stocks)
-- ================================================================
INSERT INTO filter_inventory_new (material_group, material_name, current_stock, min_stock_alert, unit)
SELECT 
    ma.material_group,
    ma.description,
    SUM(fi.current_stock) as total_stock,
    MAX(fi.min_stock_alert) as min_alert,
    MAX(fi.unit) as unit
FROM filter_inventory fi
JOIN maintenance_activities ma ON fi.activity_id = ma.id
WHERE ma.material_group IS NOT NULL
GROUP BY ma.material_group, ma.description;

-- ================================================================
-- Step 5: Backup old table and switch to new one
-- ================================================================
RENAME TABLE filter_inventory TO filter_inventory_old;
RENAME TABLE filter_inventory_new TO filter_inventory;

-- ================================================================
-- Step 6: Update inventory_transactions to reference new structure
-- ================================================================
-- Note: This will break the foreign key temporarily
-- You may want to keep the old data for reference but create new transactions going forward
ALTER TABLE inventory_transactions DROP FOREIGN KEY inventory_transactions_ibfk_1;

-- Add a note to old transactions table
ALTER TABLE inventory_transactions ADD COLUMN migration_note VARCHAR(255) DEFAULT 'Pre-migration transaction';

-- ================================================================
-- Verification queries
-- ================================================================
-- Check material groups are set correctly
SELECT code, description, material_group, is_filter_material FROM maintenance_activities ORDER BY code;

-- Check new inventory structure (F and G should share blauw_fijn)
SELECT * FROM filter_inventory ORDER BY material_group;

-- ================================================================
-- IMPORTANT: After verifying everything works, you can drop the old table:
-- DROP TABLE filter_inventory_old;
-- ================================================================
