# Simple File Decommissioning Database Setup

## Quick Setup (3 Steps)

### Step 1: Run the Database Script
Copy and paste this entire script into SQL Server Management Studio and run it:

**File: `database_update_simple.sql`**

This script will:
- Add 4 new columns to your `fileNumber` table
- Create a `decommissioned_files` table 
- Handle the data type mismatch issue automatically
- Create necessary indexes

### Step 2: Update Laravel Models
Run these commands in your terminal:

```bash
cd c:\xampp\htdocs\kangi.com.ng

# Backup current models
copy app\Models\FileNumber.php app\Models\FileNumber_backup.php
copy app\Models\DecommissionedFiles.php app\Models\DecommissionedFiles_backup.php

# Use simple database versions
copy app\Models\FileNumber_simple.php app\Models\FileNumber.php
copy app\Models\DecommissionedFiles_simple.php app\Models\DecommissionedFiles.php
```

### Step 3: Test
Open your browser and go to: `http://localhost/kangi.com.ng/file-decommissioning`

## What the Database Script Does

### Adds to `fileNumber` table:
- `commissioning_date` (DATETIME NULL)
- `decommissioning_date` (DATETIME NULL) 
- `decommissioning_reason` (NVARCHAR(MAX) NULL)
- `is_decommissioned` (BIT NOT NULL DEFAULT 0)

### Creates `decommissioned_files` table:
- `id` (INT IDENTITY PRIMARY KEY)
- `file_number_id` (INT) - matches your fileNumber.id type
- `file_no`, `mls_file_no`, `kangis_file_no`, `new_kangis_file_no` (NVARCHAR)
- `file_name` (NVARCHAR(500))
- `commissioning_date`, `decommissioning_date` (DATETIME)
- `decommissioning_reason` (NVARCHAR(MAX))
- `decommissioned_by` (NVARCHAR(255))
- `created_at`, `updated_at` (DATETIME)

### Creates indexes for performance:
- Index on `is_decommissioned` for fast filtering
- Index on `decommissioning_date` for date queries
- Index on `file_number_id` for joins

## Troubleshooting

### If you get permission errors:
Run SQL Server Management Studio as Administrator

### If foreign key still fails:
The script will continue without the foreign key constraint - the system will still work perfectly

### If you need to start over:
```sql
-- Run this to clean up and start fresh
DROP TABLE decommissioned_files;
ALTER TABLE fileNumber DROP COLUMN commissioning_date;
ALTER TABLE fileNumber DROP COLUMN decommissioning_date;
ALTER TABLE fileNumber DROP COLUMN decommissioning_reason;
ALTER TABLE fileNumber DROP COLUMN is_decommissioned;
```

## That's It!

Once you complete these 3 steps, your file decommissioning system will be using the database instead of files, giving you:

- ✅ Much better performance
- ✅ Data integrity 
- ✅ Concurrent user support
- ✅ Proper audit trail
- ✅ Easy reporting

The system is designed to be simple and robust - it will work even if some advanced features (like foreign keys) can't be created due to permission issues.