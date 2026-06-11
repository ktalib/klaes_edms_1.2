/* =============================================================================
   SLTR Print Label Batches — production fixes
   Run against the SQL Server (sqlsrv) database.

   1. Drop the unique index uq_sltr_plb_prefix_sysbatch on
      sltr_print_label_batches. It enforced UNIQUE(prefix, sys_batch_no) but the
      app always inserts prefix='SLTR', sys_batch_no=0, so every batch after the
      first failed with a duplicate-key 500 error.

   2. Backfill the "Group" (sub_prefix) for every batch that was created before
      sub_prefix was being saved. The value is derived from each batch's own
      files: the most common file_indexings.sub_prefix among that batch's items.

   Idempotent: safe to run more than once. Only rows with a NULL/empty
   sub_prefix are touched, so already-correct values are never overwritten.
   ============================================================================= */

SET NOCOUNT ON;

/* ---- 1. Drop the offending unique index ---------------------------------- */
IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'uq_sltr_plb_prefix_sysbatch'
      AND object_id = OBJECT_ID('dbo.sltr_print_label_batches')
)
BEGIN
    DROP INDEX uq_sltr_plb_prefix_sysbatch ON dbo.sltr_print_label_batches;
    PRINT 'Dropped index uq_sltr_plb_prefix_sysbatch.';
END
ELSE
    PRINT 'Index uq_sltr_plb_prefix_sysbatch not present — nothing to drop.';

/* Also drop the older-named variants if they exist (defensive). */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'uq_sltr_batch_prefix_sysbatchno' AND object_id = OBJECT_ID('dbo.sltr_print_label_batches'))
    DROP INDEX uq_sltr_batch_prefix_sysbatchno ON dbo.sltr_print_label_batches;

IF EXISTS (SELECT 1 FROM sys.objects WHERE name = 'uq_sltr_batch_prefix_sysbatchno' AND type = 'UQ')
    ALTER TABLE dbo.sltr_print_label_batches DROP CONSTRAINT uq_sltr_batch_prefix_sysbatchno;

/* ---- 2. Backfill sub_prefix (Group) on existing batches ------------------ */
/* For each batch missing a sub_prefix, pick the most frequent (and lowest,
   as a tiebreaker) non-empty sub_prefix among its own file_indexings rows. */
;WITH ranked AS (
    SELECT
        bi.batch_id,
        fi.sub_prefix,
        ROW_NUMBER() OVER (
            PARTITION BY bi.batch_id
            ORDER BY COUNT(*) DESC, fi.sub_prefix ASC
        ) AS rn
    FROM dbo.sltr_print_label_batch_items bi
    JOIN dbo.file_indexings fi ON fi.id = bi.file_indexing_id
    WHERE fi.sub_prefix IS NOT NULL AND LTRIM(RTRIM(fi.sub_prefix)) <> ''
    GROUP BY bi.batch_id, fi.sub_prefix
)
UPDATE b
SET b.sub_prefix = r.sub_prefix
FROM dbo.sltr_print_label_batches b
JOIN ranked r ON r.batch_id = b.id AND r.rn = 1
WHERE b.sub_prefix IS NULL OR LTRIM(RTRIM(b.sub_prefix)) = '';

PRINT CAST(@@ROWCOUNT AS VARCHAR(20)) + ' batch(es) backfilled.';
GO
