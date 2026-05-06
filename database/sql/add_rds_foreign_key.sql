-- Optional: Add foreign key constraint to rds_tracking table
-- Run this script ONLY after verifying the registered_instruments table structure

-- Step 1: Check if registered_instruments table exists and has primary key
SELECT 
    TC.TABLE_NAME,
    KCU.COLUMN_NAME,
    TC.CONSTRAINT_NAME,
    TC.CONSTRAINT_TYPE
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS AS TC
INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE AS KCU
    ON TC.CONSTRAINT_NAME = KCU.CONSTRAINT_NAME
WHERE TC.TABLE_NAME = 'registered_instruments'
    AND TC.CONSTRAINT_TYPE = 'PRIMARY KEY';

-- Step 2: If the above query shows a primary key on 'id' column, run this:
-- (Uncomment the lines below to execute)

/*
ALTER TABLE rds_tracking
ADD CONSTRAINT fk_rds_instrument
FOREIGN KEY (instrument_id)
REFERENCES registered_instruments(id)
ON DELETE CASCADE;
*/

-- Step 3: Verify the foreign key was created
/*
SELECT 
    FK.name AS ForeignKeyName,
    TP.name AS ParentTable,
    CP.name AS ParentColumn,
    TR.name AS ReferencedTable,
    CR.name AS ReferencedColumn
FROM sys.foreign_keys AS FK
INNER JOIN sys.foreign_key_columns AS FKC ON FK.object_id = FKC.constraint_object_id
INNER JOIN sys.tables AS TP ON FKC.parent_object_id = TP.object_id
INNER JOIN sys.columns AS CP ON FKC.parent_object_id = CP.object_id AND FKC.parent_column_id = CP.column_id
INNER JOIN sys.tables AS TR ON FKC.referenced_object_id = TR.object_id
INNER JOIN sys.columns AS CR ON FKC.referenced_object_id = CR.object_id AND FKC.referenced_column_id = CR.column_id
WHERE TP.name = 'rds_tracking';
*/

-- Alternative: If registered_instruments uses different column name for ID
-- First find the actual primary key column name:
/*
SELECT COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_NAME = 'registered_instruments'
    AND CONSTRAINT_NAME LIKE 'PK_%';
*/

-- Then use that column name in the foreign key (replace 'actual_pk_column' with the result):
/*
ALTER TABLE rds_tracking
ADD CONSTRAINT fk_rds_instrument
FOREIGN KEY (instrument_id)
REFERENCES registered_instruments(actual_pk_column)
ON DELETE CASCADE;
*/
