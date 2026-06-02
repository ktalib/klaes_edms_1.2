-- ============================================================================
-- seed_kangis_recert_test_rows.sql
-- ----------------------------------------------------------------------------
-- Seeds 5 MLPP recertification test rows into [fileNumber] to exercise the
-- comment branch in create_related_file_number_table.sql.
--
-- Realistic patterns:
--   * Old KN file numbers are literally "KN <serial>" (KN 36, KN 43, ...)
--   * Recertification parents are MLS-style with "-RC-":
--       RES-RC-YYYY-N (residential)
--       COM-RC-YYYY-N (commercial)
--       IND-RC-YYYY-N (industrial)
--   * related_fileno is a plain string (not a JSON array) — the rebuild
--     wraps non-JSON values automatically before OPENJSON.
--
-- All 5 rows exercise the MLPP branch:
--    comment = "MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION <RC-file>"
--
-- KANGIS recertification rows are not seeded here — that branch is already
-- exercised by real production rows where NewKANGISFileNo is populated.
--
-- After seeding, RE-RUN create_related_file_number_table.sql, then run:
--   SELECT id, file_number, related_fileno, comment
--   FROM related_file_number
--   WHERE file_number LIKE '%-RC-2019-%'
--   ORDER BY id;
--
-- Target: SQL Server
-- ============================================================================

SET NOCOUNT ON;

-- ---------------------------------------------------------------------------
-- 1) Insert 5 MLPP recertification test rows
--    related_fileno is a plain "KN <n>" string, NOT a JSON array.
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[fileNumber]
    (kangisFileNo, NewKANGISFileNo, mlsfNo, FileName, location, SOURCE, related_fileno, created_at, updated_at)
VALUES
    -- 1) Residential RC
    (NULL, NULL, 'RES-RC-2019-632', 'AHMED USMAN BAYERO', 'Tudun Yola, Gwale, Kano', 'TEST', 'KN 36', SYSUTCDATETIME(), SYSUTCDATETIME()),

    -- 2) Commercial RC
    (NULL, NULL, 'COM-RC-2019-632', 'NORTHERN STEEL & ALLIED INDUSTRIES LTD', 'Sharada, Kumbotso, Kano', 'TEST', 'KN 123', SYSUTCDATETIME(), SYSUTCDATETIME()),

    -- 3) Industrial RC
    (NULL, NULL, 'IND-RC-2019-632', 'HAJIYA AISHA MOHAMMED', 'Dorayi Babba, Gwale, Kano', 'TEST', 'KN 218', SYSUTCDATETIME(), SYSUTCDATETIME()),

    -- 4) Another Residential RC
    (NULL, NULL, 'RES-RC-2019-633', 'IBRAHIM SHEHU INUWA', 'Naibawa, Kumbotso, Kano', 'TEST', 'KN 7444', SYSUTCDATETIME(), SYSUTCDATETIME()),

    -- 5) Another Commercial RC
    (NULL, NULL, 'COM-RC-2019-633', 'MUSA ALIYU GARBA', 'Hotoro, Nasarawa, Kano', 'TEST', 'KN 48', SYSUTCDATETIME(), SYSUTCDATETIME());

PRINT CONCAT('Inserted ', @@ROWCOUNT, ' MLPP recertification test rows into fileNumber.');

-- ---------------------------------------------------------------------------
-- 2) Verify
-- ---------------------------------------------------------------------------
SELECT id, mlsfNo, FileName, related_fileno, SOURCE
FROM [dbo].[fileNumber]
WHERE SOURCE = 'TEST'
  AND mlsfNo LIKE '%-RC-2019-%'
ORDER BY id;

PRINT '';
PRINT '=== NEXT STEPS ===';
PRINT '1) Re-run create_related_file_number_table.sql to rebuild staging.';
PRINT '2) Then run:';
PRINT '     SELECT id, file_number, related_fileno, comment';
PRINT '     FROM related_file_number';
PRINT '     WHERE file_number LIKE ''%-RC-2019-%''';
PRINT '     ORDER BY id;';
PRINT '';
PRINT '   You should see 5 rows: one per parent, each linking a single';
PRINT '   "KN <n>" related fileno with the MLPP comment.';
PRINT '';
PRINT '3) On the Legal Search UI, search for one of the parent files (e.g.';
PRINT '   RES-RC-2019-632) and confirm the KN file (KN 36) appears as an';
PRINT '   ORANGE row with "Recertification" instrument type.';

-- ============================================================================
-- CLEANUP (run when finished testing)
-- ----------------------------------------------------------------------------
-- DELETE FROM [dbo].[fileNumber]
-- WHERE SOURCE = 'TEST' AND mlsfNo LIKE '%-RC-2019-%';
--
-- Then re-run create_related_file_number_table.sql to refresh staging.
-- ============================================================================
