-- Backfill party_1
UPDATE [klas].[dbo].[file_history_staging]
SET party_1 = COALESCE(
    Grantor, 
    Assignor, 
    Mortgagor, 
    Surrenderor, 
    Lessor, 
    Releasor, 
    Donor,
    Vendor,
    party_1 -- Keep existing if specialized columns are null
)
WHERE party_1 IS NULL;

-- Backfill party_2
UPDATE [klas].[dbo].[file_history_staging]
SET party_2 = COALESCE(
    Grantee, 
    Assignee, 
    Mortgagee, 
    Surrenderee, 
    Lessee, 
    Releasee, 
    Donee,
    Purchaser,
    party_2 -- Keep existing if specialized columns are null
)
WHERE party_2 IS NULL;

-- Backfill party_3
UPDATE [klas].[dbo].[file_history_staging]
SET party_3 = COALESCE(
    Surrenderor_2, 
    Mortgagor_2,
    party_3 -- Keep existing if specialized columns are null
)
WHERE party_3 IS NULL;

-- Drop deprecated columns
ALTER TABLE [klas].[dbo].[file_history_staging] DROP COLUMN SerialNo;
ALTER TABLE [klas].[dbo].[file_history_staging] DROP COLUMN PageNo;
ALTER TABLE [klas].[dbo].[file_history_staging] DROP COLUMN VolNo;
