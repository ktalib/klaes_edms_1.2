-- CHECK main_application_id for 'NULL' strings
SELECT COUNT(*) as bad_main_app_ids
FROM dbo.subapplications
WHERE main_application_id = 'NULL';

-- Check data type of main_application_id
SELECT 
    c.name AS ColumnName,
    ty.name AS DataType
FROM sys.columns c
JOIN sys.tables t ON c.object_id = t.object_id
JOIN sys.types ty ON c.user_type_id = ty.user_type_id
WHERE t.name = 'subapplications' 
  AND c.name = 'main_application_id';
