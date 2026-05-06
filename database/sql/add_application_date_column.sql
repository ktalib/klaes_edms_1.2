-- Add application_date column to mother_applications table - COMPLETED
USE klas;

-- This script has been successfully executed
-- Column added: ALTER TABLE mother_applications ADD application_date DATE NULL;
-- Records updated: 7 rows affected
-- Status: ✅ COMPLETED SUCCESSFULLY

-- To verify the column exists, run:
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'mother_applications' AND COLUMN_NAME = 'application_date'

PRINT 'Script execution completed. application_date column is now available in mother_applications table.';