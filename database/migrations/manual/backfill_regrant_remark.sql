-- Backfill the Re-grant remark text on existing rows to:
--   "This File has been Re-granted from {see_fileno}"   (when see_fileno is set)
--   "This File has been Re-granted"                     (when see_fileno is NULL/empty)
--
-- Run the SELECTs first to preview, then run the two UPDATEs.

-- 1) Preview: title_status_applications rows that will change
SELECT id, file_no, title_type, see_fileno, remark AS old_remark,
       CASE
           WHEN see_fileno IS NOT NULL AND LTRIM(RTRIM(see_fileno)) <> ''
               THEN 'This File has been Re-granted from ' + see_fileno
           ELSE 'This File has been Re-granted'
       END AS new_remark
FROM [klas].[dbo].[title_status_applications]
WHERE title_type = 'Re-grant';

-- 2) Update title_status_applications
UPDATE [klas].[dbo].[title_status_applications]
SET remark = CASE
    WHEN see_fileno IS NOT NULL AND LTRIM(RTRIM(see_fileno)) <> ''
        THEN 'This File has been Re-granted from ' + see_fileno
    ELSE 'This File has been Re-granted'
END
WHERE title_type = 'Re-grant';

-- 3) Preview: file_indexings rows that will change (see_fileno pulled from the
--    most recent matching Re-grant application for that file number)
SELECT fi.id, fi.file_number, fi.title_status_type, fi.title_status_remark AS old_remark, t.see_fileno,
       CASE
           WHEN t.see_fileno IS NOT NULL AND LTRIM(RTRIM(t.see_fileno)) <> ''
               THEN 'This File has been Re-granted from ' + t.see_fileno
           ELSE 'This File has been Re-granted'
       END AS new_remark
FROM [klas].[dbo].[file_indexings] fi
CROSS APPLY (
    SELECT TOP 1 see_fileno
    FROM [klas].[dbo].[title_status_applications] tsa
    WHERE tsa.file_no = fi.file_number AND tsa.title_type = 'Re-grant'
    ORDER BY tsa.id DESC
) t
WHERE fi.title_status_type = 'regranted';

-- 4) Update file_indexings
UPDATE fi
SET fi.title_status_remark = CASE
    WHEN t.see_fileno IS NOT NULL AND LTRIM(RTRIM(t.see_fileno)) <> ''
        THEN 'This File has been Re-granted from ' + t.see_fileno
    ELSE 'This File has been Re-granted'
END
FROM [klas].[dbo].[file_indexings] fi
CROSS APPLY (
    SELECT TOP 1 see_fileno
    FROM [klas].[dbo].[title_status_applications] tsa
    WHERE tsa.file_no = fi.file_number AND tsa.title_type = 'Re-grant'
    ORDER BY tsa.id DESC
) t
WHERE fi.title_status_type = 'regranted';
