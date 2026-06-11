<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQL Server allows only a single NULL in a plain UNIQUE constraint, but every
 * onboarding request starts with activation_token = NULL (the token is only
 * generated on approval). The second pending request therefore violates the
 * unique constraint on (<NULL>).
 *
 * Fix: replace the plain UNIQUE constraint/index with a *filtered* unique index
 * that only applies where activation_token IS NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('phs_onboarding_requests')) {
            return;
        }

        // Drop any unique KEY constraint and any unique index covering
        // activation_token (handles both the hand-written UQ_* name and the
        // Laravel default *_unique name), then create the filtered index.
        DB::connection('sqlsrv')->unprepared(<<<'SQL'
DECLARE @sql NVARCHAR(MAX) = N'';
DECLARE @objid INT = OBJECT_ID('dbo.phs_onboarding_requests');

-- 1) Drop unique KEY constraints on activation_token
SELECT @sql = @sql + N'ALTER TABLE dbo.phs_onboarding_requests DROP CONSTRAINT ' + QUOTENAME(kc.name) + N';'
FROM sys.key_constraints kc
JOIN sys.index_columns ic ON ic.object_id = kc.parent_object_id AND ic.index_id = kc.unique_index_id
JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
WHERE kc.parent_object_id = @objid AND kc.type = 'UQ' AND c.name = 'activation_token';

-- 2) Drop standalone unique indexes on activation_token (not tied to a constraint)
SELECT @sql = @sql + N'DROP INDEX ' + QUOTENAME(i.name) + N' ON dbo.phs_onboarding_requests;'
FROM sys.indexes i
JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
WHERE i.object_id = @objid
  AND i.is_unique = 1
  AND i.is_primary_key = 0
  AND i.is_unique_constraint = 0
  AND c.name = 'activation_token'
  AND i.has_filter = 0;

IF @sql <> N'' EXEC sp_executesql @sql;

-- 3) Create the filtered unique index if it does not already exist
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = @objid AND name = 'UX_phs_onboarding_requests_activation_token'
)
BEGIN
    CREATE UNIQUE INDEX UX_phs_onboarding_requests_activation_token
        ON dbo.phs_onboarding_requests (activation_token)
        WHERE activation_token IS NOT NULL;
END
SQL);
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('phs_onboarding_requests')) {
            return;
        }

        DB::connection('sqlsrv')->unprepared(<<<'SQL'
IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.phs_onboarding_requests')
      AND name = 'UX_phs_onboarding_requests_activation_token'
)
    DROP INDEX UX_phs_onboarding_requests_activation_token ON dbo.phs_onboarding_requests;
SQL);
    }
};
