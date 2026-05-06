<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('deeds_bill_balances_metadata')) {
            return;
        }

        if (!Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', 'district')) {
            return;
        }

        DB::connection('sqlsrv')->statement(
            "ALTER TABLE dbo.deeds_bill_balances_metadata ALTER COLUMN district NVARCHAR(255) NULL"
        );
    }

    public function down(): void
    {
        // Intentionally left as a no-op: reverting to NOT NULL would require backfilling
        // existing rows that may legitimately have NULL district values.
    }
};
