-- Add Vendor/Purchaser and generic party columns to pra table if missing
IF COL_LENGTH('dbo.pra','Vendor') IS NULL
BEGIN
    ALTER TABLE dbo.pra ADD Vendor NVARCHAR(255) NULL;
END

IF COL_LENGTH('dbo.pra','Purchaser') IS NULL
BEGIN
    ALTER TABLE dbo.pra ADD Purchaser NVARCHAR(255) NULL;
END

IF COL_LENGTH('dbo.pra','party_1') IS NULL
BEGIN
    ALTER TABLE dbo.pra ADD party_1 NVARCHAR(255) NULL;
END

IF COL_LENGTH('dbo.pra','party_2') IS NULL
BEGIN
    ALTER TABLE dbo.pra ADD party_2 NVARCHAR(255) NULL;
END

IF COL_LENGTH('dbo.pra','party_3') IS NULL
BEGIN
    ALTER TABLE dbo.pra ADD party_3 NVARCHAR(255) NULL;
END

PRINT 'Vendor/Purchaser and party_1..3 columns ensured on dbo.pra';
