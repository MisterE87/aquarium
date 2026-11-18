# Juwel Aquarium Maintenance Tracker - Database Schema

## Overview
This database schema is designed to replace your Excel-based aquarium maintenance tracking system with a mobile-friendly app backend.

## Database Structure

### Core Tables

#### 1. `maintenance_activities`
Stores all maintenance activity types with their frequencies.
- **Primary Key**: `id`
- **Unique Key**: `code` (A, B, C, etc.)
- Tracks frequency in weeks
- Flags filter materials vs. regular activities

#### 2. `maintenance_log`
Historical record of all performed maintenance activities.
- Automatically calculates next due date
- Tracks days until next maintenance is due
- Maintains complete history (never delete old records)

#### 3. `filter_inventory`
Current stock levels for all filter materials.
- One record per filter material
- Includes minimum stock alerts
- Auto-updates when maintenance is performed

#### 4. `inventory_transactions` (Optional)
Detailed audit trail of all inventory changes.
- Tracks purchases, usage, and adjustments
- Useful for analyzing consumption patterns

### Views

#### `v_latest_maintenance`
Shows the most recent maintenance for each activity with status indicators:
- **overdue**: Past due date
- **due_soon**: Within 7 days
- **ok**: More than 7 days away

#### `v_inventory_status`
Shows current inventory with stock alerts:
- **out_of_stock**: 0 items
- **low_stock**: At or below minimum threshold
- **in_stock**: Above minimum threshold

### Stored Procedures

#### `sp_record_maintenance(code, date, notes)`
Records a maintenance activity and automatically:
- Calculates next due date
- Updates inventory (decrements by 1 for filter materials)
- Logs inventory transaction

**Example:**
```sql
CALL sp_record_maintenance('A', '2025-11-28', 'Replaced filter watten');
```

#### `sp_update_inventory(code, quantity_change, type, notes)`
Updates inventory stock levels.

**Examples:**
```sql
-- Add 5 watten to inventory
CALL sp_update_inventory('A', 5, 'purchase', 'Bought 5 new watten');

-- Manual adjustment
CALL sp_update_inventory('B', -1, 'adjustment', 'Found damaged item');
```

#### `sp_get_upcoming_maintenance(days_ahead)`
Retrieves all maintenance due within specified days.

**Example:**
```sql
CALL sp_get_upcoming_maintenance(30); -- Next 30 days
```

## Mobile App Integration

### Key API Endpoints to Build

1. **Dashboard**
   - Query: `SELECT * FROM v_latest_maintenance`
   - Shows all activities with status colors

2. **Record Maintenance**
   - Use: `CALL sp_record_maintenance(code, date, notes)`
   - Mobile form with date picker and notes field

3. **Inventory Management**
   - Query: `SELECT * FROM v_inventory_status`
   - Update: `CALL sp_update_inventory(...)`

4. **History View**
   - Query: `SELECT * FROM maintenance_log WHERE activity_id = ? ORDER BY performed_date DESC`

5. **Upcoming Tasks**
   - Query: `CALL sp_get_upcoming_maintenance(30)`

### Status Color Coding for Mobile UI

| Status | Color | Condition |
|--------|-------|-----------|
| Overdue | 🔴 Red | `days_until_due < 0` |
| Due Soon | 🟡 Yellow | `days_until_due <= 7` |
| OK | 🟢 Green | `days_until_due > 7` |

| Stock Status | Color | Condition |
|--------------|-------|-----------|
| Out of Stock | 🔴 Red | `current_stock = 0` |
| Low Stock | 🟡 Yellow | `current_stock <= min_stock_alert` |
| In Stock | 🟢 Green | `current_stock > min_stock_alert` |

## Setup Instructions

1. Create MySQL database:
   ```sql
   CREATE DATABASE aquarium_maintenance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE aquarium_maintenance;
   ```

2. Run the schema file:
   ```bash
   mysql -u your_username -p aquarium_maintenance < aquarium_schema.sql
   ```

3. Update initial inventory levels:
   ```sql
   UPDATE filter_inventory SET current_stock = 4 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'B');
   ```

## Migrating Your Excel Data

To import your existing Excel data:

1. **Maintenance Activities** - Already included in schema

2. **Historical Maintenance**:
   ```sql
   CALL sp_record_maintenance('E', '2025-04-21', NULL);
   CALL sp_record_maintenance('G', '2025-04-27', NULL);
   -- etc.
   ```

3. **Current Inventory** (from your screenshot):
   ```sql
   UPDATE filter_inventory SET current_stock = 1 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'A');
   UPDATE filter_inventory SET current_stock = 4 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'B');
   UPDATE filter_inventory SET current_stock = 8 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'C');
   UPDATE filter_inventory SET current_stock = 13 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'D');
   UPDATE filter_inventory SET current_stock = 52 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'E');
   UPDATE filter_inventory SET current_stock = 39 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'F');
   UPDATE filter_inventory SET current_stock = 52 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'G');
   UPDATE filter_inventory SET current_stock = 3 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'M');
   ```

## Technology Stack Suggestions for Mobile App

- **Backend**: Node.js + Express or Python + FastAPI
- **Database**: MySQL 8.0+
- **Mobile Frontend**: 
  - React Native (iOS + Android)
  - Flutter (iOS + Android)
  - Progressive Web App (HTML5, works on all devices)
- **Authentication**: JWT tokens
- **Hosting**: AWS RDS (MySQL) + AWS Lambda/EC2

## Future Enhancements

- Push notifications for upcoming maintenance
- Charts showing maintenance history
- Water parameter tracking (pH, NO3, etc.)
- Multiple aquarium support
- Fish inventory tracking
- Photo attachments for maintenance logs
