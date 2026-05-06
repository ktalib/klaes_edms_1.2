-- CHECK TABLE SCHEMA
SELECT 
    t.name AS TableName,
    c.name AS ColumnName,
    ty.name AS DataType,
    c.max_length,
    c.is_nullable
FROM sys.columns c
JOIN sys.tables t ON c.object_id = t.object_id
JOIN sys.types ty ON c.user_type_id = ty.user_type_id
WHERE t.name = 'subapplications' 
  AND c.name IN ('main_application_id', 'is_deleted', 'id', 'is_sua_unit');
