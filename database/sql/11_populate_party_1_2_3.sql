-- Populate party_1, party_2, party_3 from available party columns
-- This script will fill empty NULL/blank party_1..3 fields with the first available
-- parties in a priority order. It is safe to re-run.

SET NOCOUNT ON;

-- Ensure target columns exist (skip if missing)
IF COL_LENGTH('dbo.pra','party_1') IS NULL OR COL_LENGTH('dbo.pra','party_2') IS NULL OR COL_LENGTH('dbo.pra','party_3') IS NULL
BEGIN
    PRINT 'party_1/2/3 columns missing on dbo.pra; run the migration script first.';
    RETURN;
END

-- Update rows where any party_1/2/3 is NULL/empty
;WITH to_update AS (
    SELECT p.*
    FROM dbo.pra p
    WHERE (p.party_1 IS NULL OR LTRIM(RTRIM(p.party_1)) = '')
       OR (p.party_2 IS NULL OR LTRIM(RTRIM(p.party_2)) = '')
       OR (p.party_3 IS NULL OR LTRIM(RTRIM(p.party_3)) = '')
)
UPDATE p
SET
    party_1 = ISNULL(NULLIF(LTRIM(RTRIM(p.party_1)),''), v.p1),
    party_2 = ISNULL(NULLIF(LTRIM(RTRIM(p.party_2)),''), v.p2),
    party_3 = ISNULL(NULLIF(LTRIM(RTRIM(p.party_3)),''), v.p3)
FROM dbo.pra p
INNER JOIN to_update tu ON tu.id = p.id
CROSS APPLY (
    SELECT
        MAX(CASE WHEN rn = 1 THEN val END) AS p1,
        MAX(CASE WHEN rn = 2 THEN val END) AS p2,
        MAX(CASE WHEN rn = 3 THEN val END) AS p3
    FROM (
        SELECT val, ROW_NUMBER() OVER (ORDER BY ord) AS rn
        FROM (
            VALUES
                (1, NULLIF(LTRIM(RTRIM(Assignor)),'')),
                (2, NULLIF(LTRIM(RTRIM(Assignee)),'')),
                (3, NULLIF(LTRIM(RTRIM(Mortgagor)),'')),
                (4, NULLIF(LTRIM(RTRIM(Mortgagee)),'')),
                (5, NULLIF(LTRIM(RTRIM(Mortgagor_2)),'')),
                (6, NULLIF(LTRIM(RTRIM(Lessor)),'')),
                (7, NULLIF(LTRIM(RTRIM(Lessee)),'')),
                (8, NULLIF(LTRIM(RTRIM(Grantor)),'')),
                (9, NULLIF(LTRIM(RTRIM(Grantee)),'')),
                (10, NULLIF(LTRIM(RTRIM(Surrenderor)),'')),
                (11, NULLIF(LTRIM(RTRIM(Surrenderee)),'')),
                (12, NULLIF(LTRIM(RTRIM(Donor)),'')),
                (13, NULLIF(LTRIM(RTRIM(Donee)),'')),
                (14, NULLIF(LTRIM(RTRIM(Vendor)),'')),
                (15, NULLIF(LTRIM(RTRIM(Purchaser)),''))
        ) AS cand(ord, val)
        WHERE cand.val IS NOT NULL
    ) AS numbered
) v
;

PRINT 'party_1/2/3 population completed.';
