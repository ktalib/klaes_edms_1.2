-- Mark all temp_fileno_sequence rows as used where the TEMP
-- already exists in instrument_capture, pra, or pic.
-- Fixes stale is_used=0 rows that cause duplicate TEMP allocation.

UPDATE dbo.temp_fileno_sequence
SET is_used    = 1,
    updated_at = GETDATE()
WHERE is_used = 0
  AND (
      EXISTS (
          SELECT 1 FROM dbo.instrument_capture ic
          WHERE ic.temp_fileno = 'TEMP-' + RIGHT('00000' + CAST(temp_fileno_sequence.id AS VARCHAR), 5)
      )
      OR EXISTS (
          SELECT 1 FROM dbo.pra p
          WHERE p.temp_fileno = 'TEMP-' + RIGHT('00000' + CAST(temp_fileno_sequence.id AS VARCHAR), 5)
      )
      OR EXISTS (
          SELECT 1 FROM dbo.pic pc
          WHERE pc.temp_fileno = 'TEMP-' + RIGHT('00000' + CAST(temp_fileno_sequence.id AS VARCHAR), 5)
      )
  );

PRINT CAST(@@ROWCOUNT AS VARCHAR) + ' stale sequence rows marked as used.';

-- Verify TEMP-18440 specifically
SELECT id, is_used, created_by, created_at
FROM dbo.temp_fileno_sequence
WHERE id = 18440;
