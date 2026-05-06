-- Add additional columns to blind_scannings table

-- Add file_number column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'blind_scannings' AND COLUMN_NAME = 'file_number')
BEGIN
    ALTER TABLE blind_scannings ADD file_number nvarchar(255) NULL;
END

-- Add local_pc_path column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'blind_scannings' AND COLUMN_NAME = 'local_pc_path')
BEGIN
    ALTER TABLE blind_scannings ADD local_pc_path nvarchar(max) NULL;
END

-- Add a4_count column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'blind_scannings' AND COLUMN_NAME = 'a4_count')
BEGIN
    ALTER TABLE blind_scannings ADD a4_count int NOT NULL DEFAULT 0;
END

-- Add a3_count column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'blind_scannings' AND COLUMN_NAME = 'a3_count')
BEGIN
    ALTER TABLE blind_scannings ADD a3_count int NOT NULL DEFAULT 0;
END

-- Add total_pages column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'blind_scannings' AND COLUMN_NAME = 'total_pages')
BEGIN
    ALTER TABLE blind_scannings ADD total_pages int NOT NULL DEFAULT 0;
END

PRINT 'Additional columns added to blind_scannings table successfully';