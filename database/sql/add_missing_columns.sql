-- Check and add missing columns to mother_applications table

-- Check if scheme_number column exists
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'mother_applications' 
    AND COLUMN_NAME = 'scheme_number'
)
BEGIN
    ALTER TABLE mother_applications 
    ADD scheme_number NVARCHAR(255) NULL;
    PRINT 'Added scheme_number column';
END
ELSE
BEGIN
    PRINT 'scheme_number column already exists';
END

-- Check if property_house_no column exists
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'mother_applications' 
    AND COLUMN_NAME = 'property_house_no'
)
BEGIN
    ALTER TABLE mother_applications 
    ADD property_house_no NVARCHAR(255) NULL;
    PRINT 'Added property_house_no column';
END
ELSE
BEGIN
    PRINT 'property_house_no column already exists';
END

-- Check if applied_file_number column exists
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'mother_applications' 
    AND COLUMN_NAME = 'applied_file_number'
)
BEGIN
    ALTER TABLE mother_applications 
    ADD applied_file_number NVARCHAR(255) NULL;
    PRINT 'Added applied_file_number column';
END
ELSE
BEGIN
    PRINT 'applied_file_number column already exists';
END

-- Check if selected_file_id column exists
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'mother_applications' 
    AND COLUMN_NAME = 'selected_file_id'
)
BEGIN
    ALTER TABLE mother_applications 
    ADD selected_file_id NVARCHAR(255) NULL;
    PRINT 'Added selected_file_id column';
END
ELSE
BEGIN
    PRINT 'selected_file_id column already exists';
END

-- Check if selected_file_type column exists
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'mother_applications' 
    AND COLUMN_NAME = 'selected_file_type'
)
BEGIN
    ALTER TABLE mother_applications 
    ADD selected_file_type NVARCHAR(255) NULL;
    PRINT 'Added selected_file_type column';
END
ELSE
BEGIN
    PRINT 'selected_file_type column already exists';
END

-- Check if selected_file_data column exists
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'mother_applications' 
    AND COLUMN_NAME = 'selected_file_data'
)
BEGIN
    ALTER TABLE mother_applications 
    ADD selected_file_data NTEXT NULL;
    PRINT 'Added selected_file_data column';
END
ELSE
BEGIN
    PRINT 'selected_file_data column already exists';
END

-- Check current columns
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'mother_applications' 
AND COLUMN_NAME IN (
    'scheme_number', 
    'property_house_no', 
    'property_plot_no', 
    'property_street_name',
    'applied_file_number',
    'selected_file_id',
    'selected_file_type',
    'selected_file_data'
)
ORDER BY ORDINAL_POSITION;