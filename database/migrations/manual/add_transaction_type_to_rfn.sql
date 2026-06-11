-- ============================================================================
-- add_transaction_type_to_rfn.sql
-- ----------------------------------------------------------------------------
-- Adds transaction_type column to [related_file_number] and back-fills it.
--
-- Rules (evaluated top-down, first match wins):
--   1. comment LIKE 'MINISTRY%'                       -> "Land & Physical Planning Recertification"
--   2. comment LIKE 'KANGIS%'                         -> "KANGIS Recertification"
--   3. same related_fileno under > 1 distinct parent  -> "Subdivision"
--   4. related's purpose prefix <> parent's prefix    -> "Change of Purpose"
--   5. else NULL
--
-- Purpose prefix = the segment before the first '-' (or ' ' for "KN 36").
--
-- Idempotent: safe to re-run.
-- Target: SQL Server.
-- ============================================================================

SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

-- ---------------------------------------------------------------------------
-- 1) Add column if missing
-- ---------------------------------------------------------------------------
IF COL_LENGTH('dbo.related_file_number', 'transaction_type') IS NULL
BEGIN
    ALTER TABLE [dbo].[related_file_number]
        ADD [transaction_type] NVARCHAR(60) NULL;
    PRINT 'Added column transaction_type.';
END
GO

-- Clear before re-applying so re-runs reflect current rules
UPDATE [dbo].[related_file_number] SET transaction_type = NULL;
GO

-- ---------------------------------------------------------------------------
-- 2) Phase 1 — Recertification (from existing comment)
-- ---------------------------------------------------------------------------
UPDATE [dbo].[related_file_number]
SET    transaction_type = 'Land & Physical Planning Recertification'
WHERE  comment LIKE 'MINISTRY OF LAND AND PHYSICAL%';

UPDATE [dbo].[related_file_number]
SET    transaction_type = 'KANGIS Recertification'
WHERE  comment LIKE 'KANGIS RECERTIFICATION%';

-- Parent file_number is KANGIS legacy (KNML/MLKN/KNGP) — the parent itself
-- is the KANGIS file being recertified. Label as KANGIS Recertification so
-- Change of Purpose doesn't mis-flag it.
UPDATE [dbo].[related_file_number]
SET    transaction_type = 'KANGIS Recertification'
WHERE  transaction_type IS NULL
  AND  (UPPER(file_number) LIKE 'KNML%' OR UPPER(file_number) LIKE 'MLKN%' OR UPPER(file_number) LIKE 'KNGP%');

PRINT CONCAT('After Phase 1 (recertification): ',
    (SELECT COUNT(*) FROM [dbo].[related_file_number] WHERE transaction_type IS NOT NULL),
    ' rows labelled.');
GO

-- ---------------------------------------------------------------------------
-- 3a) Phase 2a — Merger: a single parent links >1 distinct related_filenos
--     (multiple files merged into one)
-- ---------------------------------------------------------------------------
;WITH MultiRfnPerParent AS (
    SELECT file_number
    FROM   [dbo].[related_file_number]
    WHERE  related_fileno IS NOT NULL AND LTRIM(RTRIM(related_fileno)) <> ''
       AND file_number    IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    GROUP BY file_number
    HAVING COUNT(DISTINCT related_fileno) > 1
)
UPDATE r
SET    r.transaction_type = 'Merger'
FROM   [dbo].[related_file_number] r
JOIN   MultiRfnPerParent m ON m.file_number = r.file_number
WHERE  r.transaction_type IS NULL;

PRINT CONCAT('Phase 2a (merger): ', @@ROWCOUNT, ' rows labelled.');

UPDATE [dbo].[related_file_number]
SET    comment = CONCAT('MERGED INTO ', file_number)
WHERE  transaction_type = 'Merger' AND comment IS NULL;
GO

-- ---------------------------------------------------------------------------
-- 3b) Phase 2b — Subdivision: a single related_fileno appears under >1 distinct
--     parents (one file split into many)
-- ---------------------------------------------------------------------------
;WITH MultiParent AS (
    SELECT related_fileno
    FROM   [dbo].[related_file_number]
    WHERE  related_fileno IS NOT NULL AND LTRIM(RTRIM(related_fileno)) <> ''
       AND file_number    IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    GROUP BY related_fileno
    HAVING COUNT(DISTINCT file_number) > 1
)
UPDATE r
SET    r.transaction_type = 'Subdivision'
FROM   [dbo].[related_file_number] r
JOIN   MultiParent m ON m.related_fileno = r.related_fileno
WHERE  r.transaction_type IS NULL;

PRINT CONCAT('Phase 2 (subdivision): ', @@ROWCOUNT, ' rows labelled.');

-- Backfill Subdivision comment text where missing
UPDATE [dbo].[related_file_number]
SET    comment = CONCAT('SUBDIVISION FROM ', file_number)
WHERE  transaction_type = 'Subdivision' AND comment IS NULL;
GO

-- ---------------------------------------------------------------------------
-- 4) Phase 3 — Change of Purpose
--    Compares LAND USE (not raw prefix) so that RES-RC- and CON-RES- both
--    map to "Residential" and are NOT flagged as Change of Purpose.
--
--    Prefix -> land use mapping (per SKILL.md):
--      RES, RES-RC, CON-RES, CON-RES-RC                 -> Residential
--      COM, COM-RC, CON-COM, CON-COM-RC                 -> Commercial
--      IND, IND-RC, CON-IND, CON-IND-RC                 -> Industrial
--      AG,  AG-RC,  CON-AG,  CON-AG-RC                  -> Agriculture
-- ---------------------------------------------------------------------------
-- Title-match: only flag as Change of Purpose if the related's title in
-- file_indexings matches the parent's file_title in related_file_number.
-- Same owner changing the use of their property; if titles differ, it's
-- a sale or unrelated linkage and should NOT be Change of Purpose.
UPDATE r
SET r.transaction_type = 'Change of Purpose'
FROM [dbo].[related_file_number] r
INNER JOIN [dbo].[file_indexings] fi_rel WITH (NOLOCK)
        ON fi_rel.file_number = r.related_fileno
       AND fi_rel.deleted_at IS NULL
CROSS APPLY (
    SELECT
        CASE
            WHEN UPPER(r.related_fileno) LIKE 'CON-RES-RC-%' OR UPPER(r.related_fileno) LIKE 'CON-RES-%' OR UPPER(r.related_fileno) LIKE 'RES-RC-%' OR UPPER(r.related_fileno) LIKE 'RES-%' THEN 'Residential'
            WHEN UPPER(r.related_fileno) LIKE 'CON-COM-RC-%' OR UPPER(r.related_fileno) LIKE 'CON-COM-%' OR UPPER(r.related_fileno) LIKE 'COM-RC-%' OR UPPER(r.related_fileno) LIKE 'COM-%' THEN 'Commercial'
            WHEN UPPER(r.related_fileno) LIKE 'CON-IND-RC-%' OR UPPER(r.related_fileno) LIKE 'CON-IND-%' OR UPPER(r.related_fileno) LIKE 'IND-RC-%' OR UPPER(r.related_fileno) LIKE 'IND-%' THEN 'Industrial'
            WHEN UPPER(r.related_fileno) LIKE 'CON-AG-RC-%'  OR UPPER(r.related_fileno) LIKE 'CON-AG-%'  OR UPPER(r.related_fileno) LIKE 'AG-RC-%'  OR UPPER(r.related_fileno) LIKE 'AG-%'  THEN 'Agriculture'
            ELSE NULL
        END AS related_use,
        CASE
            WHEN UPPER(r.file_number) LIKE 'CON-RES-RC-%' OR UPPER(r.file_number) LIKE 'CON-RES-%' OR UPPER(r.file_number) LIKE 'RES-RC-%' OR UPPER(r.file_number) LIKE 'RES-%' THEN 'Residential'
            WHEN UPPER(r.file_number) LIKE 'CON-COM-RC-%' OR UPPER(r.file_number) LIKE 'CON-COM-%' OR UPPER(r.file_number) LIKE 'COM-RC-%' OR UPPER(r.file_number) LIKE 'COM-%' THEN 'Commercial'
            WHEN UPPER(r.file_number) LIKE 'CON-IND-RC-%' OR UPPER(r.file_number) LIKE 'CON-IND-%' OR UPPER(r.file_number) LIKE 'IND-RC-%' OR UPPER(r.file_number) LIKE 'IND-%' THEN 'Industrial'
            WHEN UPPER(r.file_number) LIKE 'CON-AG-RC-%'  OR UPPER(r.file_number) LIKE 'CON-AG-%'  OR UPPER(r.file_number) LIKE 'AG-RC-%'  OR UPPER(r.file_number) LIKE 'AG-%'  THEN 'Agriculture'
            ELSE NULL
        END AS parent_use
) p
WHERE r.transaction_type IS NULL
  AND p.related_use IS NOT NULL
  AND p.parent_use  IS NOT NULL
  AND p.related_use <> p.parent_use
  AND r.file_title       IS NOT NULL AND LTRIM(RTRIM(r.file_title)) <> ''
  AND fi_rel.file_title  IS NOT NULL AND LTRIM(RTRIM(fi_rel.file_title)) <> ''
  AND UPPER(LTRIM(RTRIM(r.file_title))) = UPPER(LTRIM(RTRIM(fi_rel.file_title)));

PRINT CONCAT('Phase 3 (change of purpose): ', @@ROWCOUNT, ' rows labelled.');

-- Backfill Change of Purpose comment text where missing
UPDATE [dbo].[related_file_number]
SET    comment = CONCAT('CHANGE OF PURPOSE FROM ', file_number)
WHERE  transaction_type = 'Change of Purpose' AND comment IS NULL;
GO

-- ---------------------------------------------------------------------------
-- 5) Summary
-- ---------------------------------------------------------------------------
SELECT
    COALESCE(transaction_type, '(none)') AS transaction_type,
    COUNT(*) AS rows
FROM [dbo].[related_file_number]
GROUP BY transaction_type
ORDER BY rows DESC;
