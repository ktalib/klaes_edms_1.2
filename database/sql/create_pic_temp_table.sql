-- Create pic_temp table as an exact copy of the pic table structure
-- This script provides multiple options for creating the copy

-- Option 1: Create table with same structure but no data (recommended for initial setup)
SELECT * INTO pic_temp FROM pic WHERE 1=0;

-- Option 2: Create table with same structure AND copy all existing data
-- SELECT * INTO pic_temp FROM pic;

-- Option 3: Explicit table creation based on pic table structure (if the above doesn't work)
/*
CREATE TABLE pic_temp (
    [id] int IDENTITY(1,1) PRIMARY KEY,
    [mlsFNo] nvarchar(100),
    [kangisFileNo] nvarchar(100),
    [transaction_type] nvarchar(50),
    [serialNo] nvarchar(50),
    [pageNo] nvarchar(50),
    [volumeNo] nvarchar(50),
    [regNo] nvarchar(50),
    [period] nvarchar(50),
    [period_unit] nvarchar(50),
    [Grantor] nvarchar(255),
    [Grantee] nvarchar(255),
    [Assignee] nvarchar(255),
    [property_description] ntext,
    [location] nvarchar(255),
    [transaction_date] date,
    [streetName] nvarchar(255),
    [house_no] nvarchar(50),
    [districtName] nvarchar(100),
    [plot_no] nvarchar(50),
    [lga] nvarchar(100),
    [layout] nvarchar(255),
    [source] nvarchar(100),
    [tp_no] nvarchar(50),
    [lpkn_no] nvarchar(50),
    [approved_plan_no] nvarchar(50),
    [plot_size] nvarchar(100),
    [date_recommended] date,
    [date_approved] date,
    [lease_begins] date,
    [lease_expires] date,
    [surrender_date] date,
    [revoked_date] date,
    [metric_sheet] nvarchar(100),
    [regranted_from] nvarchar(100),
    [date_expired] date,
    [remarks] ntext,
    [created_by] nvarchar(100),
    [created_at] datetime,
    [land_use] nvarchar(100),
    -- Add any additional columns that exist in the original pic table
    [updated_at] datetime DEFAULT GETDATE(),
    [prop_id] nvarchar(100),
    [NewKANGISFileno] nvarchar(100),
    [temp_fileno] nvarchar(100)
);
*/

-- To verify the table was created successfully:
-- SELECT COUNT(*) FROM pic_temp;

-- To check the structure comparison:
-- SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME IN ('pic', 'pic_temp') 
-- ORDER BY TABLE_NAME, ORDINAL_POSITION;