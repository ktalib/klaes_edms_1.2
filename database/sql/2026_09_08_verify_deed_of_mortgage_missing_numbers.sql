/* ============================================================================
   Confirm "No missing numbers between 89/89/22 and 105/105/22"
   ----------------------------------------------------------------------------
   RUN AGAINST SQL SERVER (the klaes sqlsrv database). Read-only — every query
   here is a SELECT; nothing is inserted, updated or deleted.

   WHAT THE CAPTURE SCREEN CLAIMED
   That the Deed of Mortgage register, measured from the first number KLAES
   issued in it to the number its vault stands on now, has no serial in between
   that no registration holds.

   These four queries are that same claim, taken apart. Query 3 is the one that
   answers it outright.

   HOW THE SCREEN WORKS OUT THE TWO ENDS
     - The RIGHT end is the vault: instrument_number_vaults.current_serial /
       current_volume for 'Deed of Mortgage'.
     - The LEFT end is either instrument_number_vaults.start_serial /
       start_volume where the office has recorded it, or — where it is NULL —
       the LOWEST serial still on file in the register's lowest volume. That
       fallback is a guess, and it is why query 4 matters: a number deleted from
       the very start of the book takes the floor up with it and cannot appear
       in query 3 at all.

   BOTH MORTGAGE TYPES ARE ONE REGISTER. 'Deed of Mortgage' and 'Tripartite
   Mortgage' share a single vault and a single run of numbers, so every query
   below counts both. Leaving one out would invent gaps that are not there.
   ============================================================================ */

/* ----------------------------------------------------------------------------
   1. THE TWO ENDS OF THE CLAIM.
   Expect vault_stands_at to match the second number on screen, and
   floor_in_use to match the first.
   ---------------------------------------------------------------------------- */
SELECT
    v.instrument_type,
    CONCAT(v.current_serial, '/', v.current_serial, '/', v.current_volume) AS vault_stands_at,
    v.start_serial,
    v.start_volume,
    CASE WHEN v.start_serial IS NULL OR v.start_volume IS NULL
         THEN 'inferred from the earliest registration on file'
         ELSE 'recorded by the office'
    END                                                                    AS floor_source,
    COALESCE(
        v.start_serial,
        (SELECT MIN(TRY_CONVERT(int, d.serial_no))
           FROM dbo.deed_registrations d
          WHERE d.instrument_type IN ('Deed of Mortgage', 'Tripartite Mortgage')
            AND TRY_CONVERT(int, d.volume_no) = v.current_volume)
    )                                                                      AS floor_serial_in_use
  FROM dbo.instrument_number_vaults v
 WHERE v.instrument_type = 'Deed of Mortgage';

/* ----------------------------------------------------------------------------
   2. EVERY MORTGAGE REGISTRATION ON FILE, in book order.
   The raw material for the answer — read it if query 3 surprises you.
   ---------------------------------------------------------------------------- */
SELECT
    TRY_CONVERT(int, d.volume_no)                          AS volume,
    TRY_CONVERT(int, d.serial_no)                          AS serial,
    CONCAT(d.serial_no, '/', d.page_no, '/', d.volume_no)  AS registered_as,
    d.instrument_type,
    d.fileno,
    d.status,
    d.is_deleted,
    d.created_at
  FROM dbo.deed_registrations d
 WHERE d.instrument_type IN ('Deed of Mortgage', 'Tripartite Mortgage')
 ORDER BY TRY_CONVERT(int, d.volume_no), TRY_CONVERT(int, d.serial_no);

/* ----------------------------------------------------------------------------
   3. THE ANSWER. Every serial from the floor to the vault, one row each, marked
   HELD or ** MISSING **.

   The screen is right if and only if this returns no ** MISSING ** row. To see
   only the holes, uncomment the WHERE line at the bottom.

   A row-per-serial rather than a count, so a disagreement can be pointed at.
   ---------------------------------------------------------------------------- */
WITH bounds AS (
    SELECT
        v.current_volume AS vol,
        COALESCE(
            v.start_serial,
            (SELECT MIN(TRY_CONVERT(int, d.serial_no))
               FROM dbo.deed_registrations d
              WHERE d.instrument_type IN ('Deed of Mortgage', 'Tripartite Mortgage')
                AND TRY_CONVERT(int, d.volume_no) = v.current_volume)
        )                AS first_serial,
        v.current_serial AS last_serial
      FROM dbo.instrument_number_vaults v
     WHERE v.instrument_type = 'Deed of Mortgage'
),
/* 1..2048, enough for a book of 300. No permanent numbers table needed. */
numbers AS (
    SELECT TOP (2048) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
      FROM sys.all_objects a CROSS JOIN sys.all_objects b
),
expected AS (
    SELECT b.vol, n.n AS serial
      FROM bounds b
      JOIN numbers n
        ON n.n BETWEEN b.first_serial AND b.last_serial
)
SELECT
    CONCAT(e.serial, '/', e.serial, '/', e.vol) AS number,
    CASE WHEN d.id IS NULL THEN '** MISSING **' ELSE 'held' END AS state,
    d.instrument_type,
    d.fileno,
    d.status
  FROM expected e
  LEFT JOIN dbo.deed_registrations d
    ON d.instrument_type IN ('Deed of Mortgage', 'Tripartite Mortgage')
   AND TRY_CONVERT(int, d.volume_no)  = e.vol
   AND TRY_CONVERT(int, d.serial_no)  = e.serial
-- WHERE d.id IS NULL          /* uncomment for the holes alone */
 ORDER BY e.serial;

/* Same thing as one line, if you only want the verdict. */
WITH bounds AS (
    SELECT
        v.current_volume AS vol,
        COALESCE(
            v.start_serial,
            (SELECT MIN(TRY_CONVERT(int, d.serial_no))
               FROM dbo.deed_registrations d
              WHERE d.instrument_type IN ('Deed of Mortgage', 'Tripartite Mortgage')
                AND TRY_CONVERT(int, d.volume_no) = v.current_volume)
        )                AS first_serial,
        v.current_serial AS last_serial
      FROM dbo.instrument_number_vaults v
     WHERE v.instrument_type = 'Deed of Mortgage'
),
numbers AS (
    SELECT TOP (2048) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
      FROM sys.all_objects a CROSS JOIN sys.all_objects b
)
SELECT
    CONCAT(b.first_serial, '/', b.first_serial, '/', b.vol) AS measured_from,
    CONCAT(b.last_serial,  '/', b.last_serial,  '/', b.vol) AS measured_to,
    b.last_serial - b.first_serial + 1                      AS serials_in_range,
    (SELECT COUNT(*) FROM dbo.deed_registrations d
      WHERE d.instrument_type IN ('Deed of Mortgage', 'Tripartite Mortgage')
        AND TRY_CONVERT(int, d.volume_no) = b.vol
        AND TRY_CONVERT(int, d.serial_no) BETWEEN b.first_serial AND b.last_serial) AS registrations_held,
    (SELECT COUNT(*)
       FROM numbers n
      WHERE n.n BETWEEN b.first_serial AND b.last_serial
        AND NOT EXISTS (
            SELECT 1 FROM dbo.deed_registrations d
             WHERE d.instrument_type IN ('Deed of Mortgage', 'Tripartite Mortgage')
               AND TRY_CONVERT(int, d.volume_no) = b.vol
               AND TRY_CONVERT(int, d.serial_no) = n.n))                            AS missing_numbers
  FROM bounds b;

/* ----------------------------------------------------------------------------
   4. WHAT THE CLAIM DOES NOT COVER. Read this before treating query 3 as proof
   the register is whole.

   (a) Below the floor. While start_serial is NULL the floor is a guess, so
       nothing beneath the earliest surviving registration is examined at all.
       If the paper book's first KLAES entry is lower than lowest_on_file, the
       difference is a blind spot — record it in start_serial / start_volume and
       run query 3 again.

   (b) Above the vault. Registrations recorded past where the register stands
       are not gaps and are never offered, but they are the sign of a register
       that was re-based and left rows behind on the old numbering. Deed of
       Mortgage carries four, at volume 35.
   ---------------------------------------------------------------------------- */
SELECT
    (SELECT MIN(TRY_CONVERT(int, d.serial_no))
       FROM dbo.deed_registrations d
      WHERE d.instrument_type IN ('Deed of Mortgage', 'Tripartite Mortgage')
        AND TRY_CONVERT(int, d.volume_no) = v.current_volume)   AS lowest_on_file_in_current_volume,
    v.start_serial                                              AS recorded_first_serial,
    CASE WHEN v.start_serial IS NULL
         THEN 'blind below the lowest number on file'
         ELSE 'floor is recorded — nothing is blind'
    END                                                         AS below_the_floor,
    (SELECT COUNT(*)
       FROM dbo.deed_registrations d
      WHERE d.instrument_type IN ('Deed of Mortgage', 'Tripartite Mortgage')
        AND (TRY_CONVERT(int, d.volume_no) > v.current_volume
          OR (TRY_CONVERT(int, d.volume_no) = v.current_volume
              AND TRY_CONVERT(int, d.serial_no) > v.current_serial))) AS stranded_above_the_vault
  FROM dbo.instrument_number_vaults v
 WHERE v.instrument_type = 'Deed of Mortgage';

/* The stranded rows themselves. */
SELECT
    CONCAT(d.serial_no, '/', d.page_no, '/', d.volume_no) AS registered_as,
    d.instrument_type,
    d.fileno,
    d.created_at
  FROM dbo.deed_registrations d
  JOIN dbo.instrument_number_vaults v
    ON v.instrument_type = 'Deed of Mortgage'
 WHERE d.instrument_type IN ('Deed of Mortgage', 'Tripartite Mortgage')
   AND (TRY_CONVERT(int, d.volume_no) > v.current_volume
     OR (TRY_CONVERT(int, d.volume_no) = v.current_volume
         AND TRY_CONVERT(int, d.serial_no) > v.current_serial))
 ORDER BY TRY_CONVERT(int, d.volume_no), TRY_CONVERT(int, d.serial_no);
