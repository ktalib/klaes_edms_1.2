-- Normalize category values for duplicate_fileno records
-- Run after adding the `category` column to the table.

-- Temp Files
UPDATE df
SET category = 'Temp Files'
FROM duplicate_fileno AS df
WHERE (df.category IS NULL OR LTRIM(RTRIM(df.category)) = '')
  AND df.comment LIKE '[[]TEMPORARY]%';

-- W/C/R Files (Withdrawn / Revoked / Cancelled)
UPDATE df
SET category = 'W/C/R Files'
FROM duplicate_fileno AS df
WHERE (df.category IS NULL OR LTRIM(RTRIM(df.category)) = '')
  AND (
        df.comment LIKE '[[]WRC]%'
        OR df.comment LIKE '[[]WITHDRAWN]%'
      );

-- Default remaining records to Duplicate Files
UPDATE df
SET category = 'Duplicate Files'
FROM duplicate_fileno AS df
WHERE (df.category IS NULL OR LTRIM(RTRIM(df.category)) = '')
  AND (
        df.comment LIKE '[[]DUPLICATE]%'
        OR df.comment IS NULL
        OR df.comment = ''
        OR LEFT(df.comment, 1) <> '['
      );
