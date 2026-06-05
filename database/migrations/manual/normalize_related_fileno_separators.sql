-- ============================================================================
-- normalize_related_fileno_separators.sql
-- ----------------------------------------------------------------------------
-- One-shot fix for file-number values stored with the wrong separator.
-- Replaces underscores (_) and forward slashes (/) with dashes (-) in both
--   related_file_number.related_fileno
--   related_file_number.file_number
--
-- Examples handled:
--   RES_RC_1983_1147   ->  RES-RC-1983-1147
--   RES_1982_1309      ->  RES-1982-1309
--   COM/RES/2018/383   ->  COM-RES-2018-383
--   CON/COM/95/12      ->  CON-COM-95-12
--   IND/RC/82/44       ->  IND-RC-82-44
--
-- Safe to re-run (idempotent: rows already normalized are untouched).
-- Target: SQL Server
-- ============================================================================

SET NOCOUNT ON;

-- ---------------------------------------------------------------------------
-- 1) Pre-flight: how many rows are affected?
-- ---------------------------------------------------------------------------
DECLARE @bad_rfn INT = (
    SELECT COUNT(*) FROM [dbo].[related_file_number]
    WHERE related_fileno LIKE '%[_/]%'
);
DECLARE @bad_fn  INT = (
    SELECT COUNT(*) FROM [dbo].[related_file_number]
    WHERE file_number LIKE '%[_/]%'
);
PRINT CONCAT('BEFORE: related_fileno with _ or /: ', @bad_rfn);
PRINT CONCAT('BEFORE: file_number with _ or /:    ', @bad_fn);

-- ---------------------------------------------------------------------------
-- 2) Normalize related_fileno
-- ---------------------------------------------------------------------------
UPDATE [dbo].[related_file_number]
SET    related_fileno = LTRIM(RTRIM(REPLACE(REPLACE(related_fileno, '_', '-'), '/', '-')))
WHERE  related_fileno LIKE '%[_/]%';
PRINT CONCAT('Updated related_fileno on ', @@ROWCOUNT, ' rows.');

-- ---------------------------------------------------------------------------
-- 3) Normalize file_number (parent file number column)
-- ---------------------------------------------------------------------------
UPDATE [dbo].[related_file_number]
SET    file_number = LTRIM(RTRIM(REPLACE(REPLACE(file_number, '_', '-'), '/', '-')))
WHERE  file_number LIKE '%[_/]%';
PRINT CONCAT('Updated file_number on ', @@ROWCOUNT, ' rows.');

-- ---------------------------------------------------------------------------
-- 4) Verify
-- ---------------------------------------------------------------------------
DECLARE @after_rfn INT = (
    SELECT COUNT(*) FROM [dbo].[related_file_number] WHERE related_fileno LIKE '%[_/]%'
);
DECLARE @after_fn  INT = (
    SELECT COUNT(*) FROM [dbo].[related_file_number] WHERE file_number LIKE '%[_/]%'
);
PRINT CONCAT('AFTER:  related_fileno with _ or /: ', @after_rfn);
PRINT CONCAT('AFTER:  file_number with _ or /:    ', @after_fn);

-- Sample post-fix rows that had _ or / in either column
SELECT TOP 20 id, related_fileno, file_number
FROM   [dbo].[related_file_number]
WHERE  id IN (
        SELECT TOP 20 id FROM [dbo].[related_file_number]
        WHERE related_fileno LIKE '%-RC-%' OR related_fileno LIKE 'RES-%' OR related_fileno LIKE 'COM-%'
        ORDER BY id DESC
    )
ORDER BY id DESC;
