<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Online Legal Search portal lets guests search and only requires a portal
 * account (online_ls guard) at the moment they unlock a full result. Records are
 * therefore keyed on `online_ls_user_id`, not the legacy staff `user_id`.
 *
 * This relaxes two NOT NULL constraints that block the public flow:
 *   - legal_search_online_payments.user_id  → nullable (payments use online_ls_user_id)
 *   - online_ls_search_logs.online_ls_user_id → nullable (guest searches are logged anonymously)
 *
 * Uses raw ALTER COLUMN so it works without the doctrine/dbal package on SQL Server.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('sqlsrv');

        if ($conn->hasTable('legal_search_online_payments') && $conn->hasColumn('legal_search_online_payments', 'user_id')) {
            DB::connection('sqlsrv')->statement(
                'ALTER TABLE legal_search_online_payments ALTER COLUMN user_id BIGINT NULL'
            );
        }

        if ($conn->hasTable('online_ls_search_logs') && $conn->hasColumn('online_ls_search_logs', 'online_ls_user_id')) {
            DB::connection('sqlsrv')->statement(
                'ALTER TABLE online_ls_search_logs ALTER COLUMN online_ls_user_id BIGINT NULL'
            );
        }
    }

    public function down(): void
    {
        $conn = Schema::connection('sqlsrv');

        // Revert to NOT NULL. Note: this will fail if null rows exist; that is the
        // expected guard against silently dropping guest data.
        if ($conn->hasTable('online_ls_search_logs') && $conn->hasColumn('online_ls_search_logs', 'online_ls_user_id')) {
            DB::connection('sqlsrv')->statement(
                'ALTER TABLE online_ls_search_logs ALTER COLUMN online_ls_user_id BIGINT NOT NULL'
            );
        }

        if ($conn->hasTable('legal_search_online_payments') && $conn->hasColumn('legal_search_online_payments', 'user_id')) {
            DB::connection('sqlsrv')->statement(
                'ALTER TABLE legal_search_online_payments ALTER COLUMN user_id BIGINT NOT NULL'
            );
        }
    }
};
