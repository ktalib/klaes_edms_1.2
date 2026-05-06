# MLS File Number Generator - SQL Scripts

## Overview

This directory contains SQL Server scripts for setting up the MLS File Number Generator database tables.

## Files

1. **`00_complete_setup.sql`** - **RECOMMENDED** - Complete script that runs everything in order
2. `01_create_mls_file_no_table.sql` - Creates the mls_file_no table
3. `02_create_mls_serial_control_table.sql` - Creates the mls_serial_control table
4. `03_seed_mls_serial_control.sql` - Seeds initial data

## Quick Start (Recommended)

### Option 1: Run Complete Setup Script

1. Open SQL Server Management Studio (SSMS)
2. Connect to your database server
3. Select your database (e.g., `KLAES`)
4. Open `00_complete_setup.sql`
5. Click **Execute** or press `F5`

This single script will:
- Create both tables with all indexes
- Seed initial data for all land-use categories
- Display verification results

### Option 2: Run Scripts Individually

If you prefer to run scripts one at a time:

```sql
-- Step 1: Create mls_file_no table
:r 01_create_mls_file_no_table.sql
GO

-- Step 2: Create mls_serial_control table
:r 02_create_mls_serial_control_table.sql
GO

-- Step 3: Seed initial data
:r 03_seed_mls_serial_control.sql
GO
```

Or execute each file separately in SSMS.

## Tables Created

### 1. `mls_file_no`
Central registry for all MLS-generated file numbers.

**Columns:**
- `id` - Primary key
- `land_use` - Land use category (RES, COM, IND, etc.)
- `year` - Year of generation
- `serial_number` - Serial number for that land use/year
- `full_file_number` - Complete file number (e.g., RES-2026-565, COM-2026-77)
- `file_name`, `plot_no`, `tp_no`, `location` - Property details
- `tracking_id` - Integration with grouping system
- `created_by`, `created_at`, `updated_at` - Audit fields

### 2. `mls_serial_control`
Manages serial number allocation per land use and year.

**Columns:**
- `id` - Primary key
- `land_use` - Land use category
- `year` - Year
- `last_serial` - Last used serial number
- `is_initialized` - Whether initialized
- `is_locked` - Whether locked (prevents modification)
- `initialized_by`, `initialized_at` - Audit fields

## Initial Data Seeded

The setup script pre-populates serial control data for the current year:

| Land Use | Last Serial | Status |
|----------|-------------|---------|
| RES | 564 | Initialized, Unlocked |
| COM | 76 | Initialized, Unlocked |
| IND | 0 | Initialized, Unlocked |
| AG | 0 | Initialized, Unlocked |
| RES-RC | 0 | Initialized, Unlocked |
| COM-RC | 0 | Initialized, Unlocked |
| AG-RC | 0 | Initialized, Unlocked |
| IND-RC | 0 | Initialized, Unlocked |
| CON-RES | 0 | Initialized, Unlocked |
| CON-COM | 0 | Initialized, Unlocked |
| CON-IND | 0 | Initialized, Unlocked |
| CON-AG | 0 | Initialized, Unlocked |
| CON-RES-RC | 0 | Initialized, Unlocked |
| CON-COM-RC | 0 | Initialized, Unlocked |
| CON-AG-RC | 0 | Initialized, Unlocked |

## Verification Queries

After running the setup, verify the installation:

```sql
-- Check table creation
SELECT 
    TABLE_NAME,
    TABLE_TYPE
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_NAME IN ('mls_file_no', 'mls_serial_control');

-- View serial control data
SELECT 
    land_use,
    year,
    last_serial,
    is_initialized,
    is_locked,
    initialized_by
FROM dbo.mls_serial_control
ORDER BY land_use;

-- Check indexes
SELECT 
    t.name AS TableName,
    i.name AS IndexName,
    i.type_desc AS IndexType
FROM sys.indexes i
INNER JOIN sys.tables t ON i.object_id = t.object_id
WHERE t.name IN ('mls_file_no', 'mls_serial_control')
ORDER BY t.name, i.name;
```

## Troubleshooting

### Tables Already Exist

If tables already exist and you want to recreate them:

```sql
-- Drop tables (WARNING: This deletes all data!)
DROP TABLE IF EXISTS dbo.mls_file_no;
DROP TABLE IF EXISTS dbo.mls_serial_control;
GO

-- Then run setup script again
```

### Permission Issues

Ensure your SQL Server user has these permissions:
- `CREATE TABLE`
- `CREATE INDEX`
- `INSERT`, `UPDATE`, `SELECT`
- `EXECUTE` (for stored procedures if used)

### Different Year

If you want to seed data for a different year, edit the seeder script:

```sql
-- Change this line in 03_seed_mls_serial_control.sql
DECLARE @currentYear INT = 2027; -- Instead of YEAR(GETDATE())
```

## Next Steps

After running these scripts:

1. **Test the Application** - Navigate to `/mls-fileno` in your browser
2. **Click "Serial Initialization" Tab** - You should see the seeded data
3. **Initialize Serials** - Lock the serial numbers through the UI
4. **Generate File Numbers** - Use the File Generator tab or API

## Support

If you encounter issues:
1. Check SQL Server error logs
2. Verify database connection settings in `.env`
3. Ensure `sqlsrv` connection is properly configured
4. Review Laravel logs at `storage/logs/laravel.log`
