---
description: Clear application caches and run SQL Server migrations.
---

1. Clear the configuration cache:
// turbo
`php artisan config:clear`

2. Clear the application cache:
// turbo
`php artisan cache:clear`

3. Check the migration status for the SQL Server connection:
`php artisan migrate:status --database=sqlsrv`

4. Run pending migrations for SQL Server:
`php artisan migrate --database=sqlsrv`
