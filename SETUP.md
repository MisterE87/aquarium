# Aquarium Maintenance Tracker - Setup Guide

## Quick Setup

### 1. Create the Database

Run the SQL schema file to create the database and all tables:

```bash
mysql -u root -p < aquarium_schema.sql
```

When prompted, enter your MySQL root password.

This will:
- Create the `aquarium` database
- Create all required tables (maintenance_activities, maintenance_log, filter_inventory, inventory_transactions)
- Insert initial maintenance activities (A-M)
- Create views and stored procedures
- Initialize filter inventory

### 2. Update Initial Stock Levels

Based on your Excel sheet, update the inventory:

```sql
mysql -u root -p aquarium

UPDATE filter_inventory SET current_stock = 1 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'A');
UPDATE filter_inventory SET current_stock = 4 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'B');
UPDATE filter_inventory SET current_stock = 8 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'C');
UPDATE filter_inventory SET current_stock = 13 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'D');
UPDATE filter_inventory SET current_stock = 52 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'E');
UPDATE filter_inventory SET current_stock = 39 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'F');
UPDATE filter_inventory SET current_stock = 52 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'G');
UPDATE filter_inventory SET current_stock = 3 WHERE activity_id = (SELECT id FROM maintenance_activities WHERE code = 'M');
```

### 3. Import Historical Data (Optional)

Import your existing maintenance records from Excel:

```sql
CALL sp_record_maintenance('E', '2025-04-21', NULL);
CALL sp_record_maintenance('G', '2025-04-27', NULL);
CALL sp_record_maintenance('L', '2025-10-12', NULL);
CALL sp_record_maintenance('D', '2025-10-12', NULL);
CALL sp_record_maintenance('B', '2025-10-29', NULL);
CALL sp_record_maintenance('C', '2025-10-29', NULL);
CALL sp_record_maintenance('K', '2025-11-07', NULL);
CALL sp_record_maintenance('A', '2025-11-14', NULL);
CALL sp_record_maintenance('I', '2025-11-14', NULL);
CALL sp_record_maintenance('J', '2025-11-14', NULL);
CALL sp_record_maintenance('F', '2025-11-14', NULL);
```

### 4. Start PHP Development Server

```bash
# From the project root:
php -S localhost:8000
```

### 5. Access the Dashboard

Open your browser and navigate to:
- **Dashboard**: http://localhost:8000/index.php

## Running API Tests

Automated integration tests exercise all API endpoints via `curl`. They require `curl`, `jq`, `php`, and the MySQL client.

```bash
# From the project root (set DB password if your root user requires one):
AQUARIUM_DB_PASS=your_mysql_password ./tests/api_test.sh
```

The script will:
- Copy [`env.test.php`](env.test.php) to `env.php` for the test run (your real `env.php` is backed up and restored)
- Create a fresh `aquarium_test` database from [`aquarium_schema.sql`](aquarium_schema.sql)
- Start a temporary PHP built-in server and run ~23 assertions

Environment variables (all optional):

| Variable | Default | Purpose |
|----------|---------|---------|
| `AQUARIUM_DB_HOST` | `127.0.0.1` | MySQL host |
| `AQUARIUM_DB_NAME` | `aquarium_test` | Test database name |
| `AQUARIUM_DB_USER` | `root` | MySQL user |
| `AQUARIUM_DB_PASS` | _(empty)_ | MySQL password |
| `AQUARIUM_LOGIN_PASSWORD` | `test-password` | API auth password |
| `AQUARIUM_TEST_PORT` | `8765` | PHP server port |

Tests also run automatically on push/PR via [`.github/workflows/api-tests.yml`](.github/workflows/api-tests.yml).

## Project Structure

```
.
├── env.example.php            # Example environment config (copy to env.php)
├── env.php                    # Local secrets — not committed (DB, login, HMAC)
├── env.test.php               # Test environment config for API tests
├── api.php                    # REST API endpoints
├── index.php                  # Main dashboard page
├── tests/api_test.sh          # Automated API integration tests
├── aquarium_schema.sql        # Database schema and setup
├── README.md                  # Documentation
└── SETUP.md                   # This setup guide
```

## API Endpoints

All endpoints are accessed via `api.php?endpoint=<endpoint_name>`

### GET Endpoints

- **`latest_maintenance`** - Get all activities with status (used by dashboard)
  ```
  GET api.php?endpoint=latest_maintenance
  ```

- **`activities`** - Get all maintenance activities
  ```
  GET api.php?endpoint=activities
  ```

- **`inventory`** - Get filter inventory status
  ```
  GET api.php?endpoint=inventory
  ```

- **`maintenance_history`** - Get maintenance history
  ```
  GET api.php?endpoint=maintenance_history&activity_id=1&limit=50
  ```

- **`upcoming_maintenance`** - Get upcoming maintenance
  ```
  GET api.php?endpoint=upcoming_maintenance&days=30
  ```

### POST Endpoints

- **`record_maintenance`** - Record a maintenance activity
  ```
  POST api.php?endpoint=record_maintenance
  Body: {"code": "A", "date": "2025-11-28", "notes": "Optional notes"}
  ```

- **`update_inventory`** - Update inventory stock
  ```
  POST api.php?endpoint=update_inventory
  Body: {"code": "A", "quantity": 5, "type": "purchase", "notes": "Bought new items"}
  ```

## Dashboard Features

The dashboard provides:

- ✅ **Statistics Cards** - Overview of overdue, due soon, and OK activities
- ✅ **Activity Cards** - Color-coded status for each maintenance activity
  - 🔴 Red: Overdue
  - 🟡 Yellow: Due soon (within 7 days)
  - 🟢 Green: OK
  - ⚫ Gray: Never done
- ✅ **Stock Information** - Shows current inventory for filter materials
- ✅ **Auto-refresh Button** - Reload data without page refresh
- ✅ **Mobile Responsive** - Works on all devices

## Next Steps

### Add More Pages

1. **Record Maintenance Page** - Form to log maintenance activities
2. **Inventory Page** - Manage filter material stock
3. **History Page** - View all past maintenance
4. **Settings Page** - Configure alerts and preferences

### Mobile App

The current setup provides a mobile-friendly web interface. For a native mobile app:
- Use the existing API endpoints
- Build with React Native, Flutter, or progressive web app (PWA)
- Add push notifications for due maintenance

## Troubleshooting

### Database Connection Error
- Check MySQL is running: `sudo systemctl status mysql`
- Verify credentials in `env.php`
- Ensure database exists: `mysql -u root -p -e "SHOW DATABASES;"`

### API Returns Empty Data
- Check database has data: `mysql -u root -p aquarium -e "SELECT * FROM maintenance_activities;"`
- Verify stored procedures exist: `SHOW PROCEDURE STATUS WHERE Db = 'aquarium';`

### Dashboard Not Loading
- Check PHP server is running on port 8000
- Open browser console (F12) to see JavaScript errors
- Verify API endpoint returns JSON: `curl http://localhost:8000/api.php?endpoint=latest_maintenance`

### FTP Deploy (GitHub Actions)

The manual workflow (`.github/workflows/manual.yml`) uploads via FTP using repository secrets:

- `FTP_SERVER_PREMIUM`
- `FTP_USERNAME_PREMIUM`
- `FTP_PASSWORD_PREMIUM`
- `FTP_SERVER_DIR_PREMIUM` — remote path on the host (e.g. `/your-domain.example/`)

Public URL (replace with your domain): `https://your-domain.example/index.php`

Production [`env.php`](env.php) must **not** set `BASE_PATH` when hosting at the domain root (leave it unset).

Local dev and CI also leave `BASE_PATH` empty (default).

Renew the SSL certificate for your domain before testing PWA install on Android — WebAPK requires valid HTTPS.

### PWA Install on Android

After deploy, verify PWA assets are served correctly:

```bash
AQUARIUM_PWA_BASE_URL=https://your-domain.example ./tests/pwa_fetch_check.sh
```

Each asset must return HTTP 200 with the expected content type (`application/manifest+json`, `application/javascript`, or `image/png`).

The public manifest URL is [`manifest.json`](manifest.json), which is served dynamically by [`manifest.php`](manifest.php) (Apache rewrites `manifest.json` → `manifest.php`).

On Android Chrome, use `chrome://inspect` and check:
- Application → Manifest: loads from `manifest.json` with icon previews (not broken)
- Application → Service Workers: `sw.js` registered and page controlled
- Tap **Install app** and confirm the installed app opens in standalone mode (no browser URL bar)

Local verification:

```bash
php -S localhost:8000 -t . router.php
# Chrome DevTools → Application → Manifest + Service Workers
```

## Security Notes

⚠️ **Important**: Before deploying to production:
1. Move `env.php` outside web root
2. Use environment variables instead of hardcoded credentials
3. Add authentication to API endpoints
4. Enable HTTPS
5. Add CSRF protection
6. Validate and sanitize all inputs
