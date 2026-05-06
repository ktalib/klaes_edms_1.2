-- ============================================================
-- Strip leading zeros from pra: regNo segments (X/Y/Z format),
-- serialNo, pageNo, volumeNo.
-- e.g. regNo  010/010/65  → 10/10/65
--      serialNo 010        → 10
-- Only rows where the value actually differs after stripping.
-- ============================================================

-- Helper: strip leading zeros from a single numeric segment
-- CAST(TRY_CAST(val AS INT) AS VARCHAR) does the job safely.

-- ── PREVIEW: regNo rows with leading zeros in any segment ───
SELECT id, prop_id, regNo, serialNo, pageNo, volumeNo
FROM dbo.pra
WHERE regNo IS NOT NULL
  AND regNo LIKE '%/%'
  AND (
      -- segment starts with '0' followed by a non-zero digit (skip all-zero segments like 000)
      LEFT(regNo, CHARINDEX('/', regNo) - 1) LIKE '0[1-9]%'
      OR SUBSTRING(regNo,
             CHARINDEX('/', regNo) + 1,
             CHARINDEX('/', regNo, CHARINDEX('/', regNo) + 1)
               - CHARINDEX('/', regNo) - 1
         ) LIKE '0[1-9]%'
      OR RIGHT(regNo, LEN(regNo) - CHARINDEX('/', regNo, CHARINDEX('/', regNo) + 1)) LIKE '0[1-9]%'
  )
ORDER BY id;

-- ── UPDATE ──────────────────────────────────────────────────
BEGIN TRANSACTION;

-- 1. Fix regNo (format X/Y/Z — strip leading zeros from each segment)
UPDATE dbo.pra
SET regNo = 
    CAST(TRY_CAST(LEFT(regNo, CHARINDEX('/', regNo) - 1) AS INT) AS VARCHAR)
    + '/'
    + CAST(TRY_CAST(
          SUBSTRING(regNo,
              CHARINDEX('/', regNo) + 1,
              CHARINDEX('/', regNo, CHARINDEX('/', regNo) + 1)
                - CHARINDEX('/', regNo) - 1)
      AS INT) AS VARCHAR)
    + '/'
    + CAST(TRY_CAST(
          RIGHT(regNo, LEN(regNo) - CHARINDEX('/', regNo, CHARINDEX('/', regNo) + 1))
      AS INT) AS VARCHAR),
    updated_at = GETDATE()
WHERE regNo IS NOT NULL
  AND regNo LIKE '%/%/%'
  -- Skip rows where any segment is all zeros (e.g. 000/010/65 — leave 000 alone)
  AND TRY_CAST(LEFT(regNo, CHARINDEX('/', regNo) - 1) AS INT) > 0
  AND TRY_CAST(
          SUBSTRING(regNo,
              CHARINDEX('/', regNo) + 1,
              CHARINDEX('/', regNo, CHARINDEX('/', regNo) + 1)
                - CHARINDEX('/', regNo) - 1)
      AS INT) > 0
  -- Only update rows that actually differ after stripping
  AND regNo <> 
    CAST(TRY_CAST(LEFT(regNo, CHARINDEX('/', regNo) - 1) AS INT) AS VARCHAR)
    + '/'
    + CAST(TRY_CAST(
          SUBSTRING(regNo,
              CHARINDEX('/', regNo) + 1,
              CHARINDEX('/', regNo, CHARINDEX('/', regNo) + 1)
                - CHARINDEX('/', regNo) - 1)
      AS INT) AS VARCHAR)
    + '/'
    + CAST(TRY_CAST(
          RIGHT(regNo, LEN(regNo) - CHARINDEX('/', regNo, CHARINDEX('/', regNo) + 1))
      AS INT) AS VARCHAR);

PRINT CAST(@@ROWCOUNT AS VARCHAR) + ' regNo rows updated.';

-- 2. Fix serialNo (single numeric value, skip all-zero values)
UPDATE dbo.pra
SET serialNo   = CAST(TRY_CAST(serialNo AS INT) AS VARCHAR),
    updated_at = GETDATE()
WHERE serialNo IS NOT NULL
  AND TRY_CAST(serialNo AS INT) IS NOT NULL
  AND TRY_CAST(serialNo AS INT) > 0
  AND serialNo <> CAST(TRY_CAST(serialNo AS INT) AS VARCHAR);

PRINT CAST(@@ROWCOUNT AS VARCHAR) + ' serialNo rows updated.';

-- 3. Fix pageNo (skip all-zero values)
UPDATE dbo.pra
SET pageNo     = CAST(TRY_CAST(pageNo AS INT) AS VARCHAR),
    updated_at = GETDATE()
WHERE pageNo IS NOT NULL
  AND TRY_CAST(pageNo AS INT) IS NOT NULL
  AND TRY_CAST(pageNo AS INT) > 0
  AND pageNo <> CAST(TRY_CAST(pageNo AS INT) AS VARCHAR);

PRINT CAST(@@ROWCOUNT AS VARCHAR) + ' pageNo rows updated.';

-- 4. Fix volumeNo (skip all-zero values)
UPDATE dbo.pra
SET volumeNo   = CAST(TRY_CAST(volumeNo AS INT) AS VARCHAR),
    updated_at = GETDATE()
WHERE volumeNo IS NOT NULL
  AND TRY_CAST(volumeNo AS INT) IS NOT NULL
  AND TRY_CAST(volumeNo AS INT) > 0
  AND volumeNo <> CAST(TRY_CAST(volumeNo AS INT) AS VARCHAR);

PRINT CAST(@@ROWCOUNT AS VARCHAR) + ' volumeNo rows updated.';

-- ── VERIFY: spot-check a sample of fixed rows ────────────────
SELECT TOP 50 id, prop_id, regNo, serialNo, pageNo, volumeNo
FROM dbo.pra
WHERE regNo IS NOT NULL
ORDER BY updated_at DESC;

-- ROLLBACK TRANSACTION;
COMMIT TRANSACTION;
