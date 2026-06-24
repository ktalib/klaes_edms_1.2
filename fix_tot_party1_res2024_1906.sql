/*
  Fix wrong Party 1 on the Transfer of Title (OP) for RES-2024-1906 (PROD).

  ToT pra row id=168717 (prop_id=120250, TEMP-75419) has
      party_1 / Grantor = 'KANO STATE GOVERNMENT'   <-- wrong (that's the OP grantor)
  Correct value = the OP allottee from pra id=129301 (TEMP-75418) = 'LAMASH'.
  Transfer is FROM Lamash TO Usman Nuhu Alfadarai, so Grantor must be LAMASH.

  Run on the 'klas' PROD database.
*/

-- BEFORE
SELECT 'BEFORE' AS state, id, prop_id, temp_fileno, transaction_type, party_1, Grantor, party_2
FROM   pra WHERE id IN (129301, 168717);

UPDATE pra
SET    party_1    = 'LAMASH',
       Grantor    = 'LAMASH',
       updated_at = GETDATE()
WHERE  id = 168717
  AND  temp_fileno = 'TEMP-75419';   -- safety guard

PRINT 'Rows updated: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

-- AFTER
SELECT 'AFTER' AS state, id, prop_id, temp_fileno, transaction_type, party_1, Grantor, party_2
FROM   pra WHERE id IN (129301, 168717);
