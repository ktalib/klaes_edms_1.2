<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrects system_sub_type for OSS change-of-ownership commissions.
 *
 * The 2026_08_07_120000 backfill knew about sub_source = 'OP Change of Name' but
 * not 'OP Change of Ownership', which is what the OSS page actually posts today
 * (resources/views/lands_one_stop_shop/applications.blade.php). Those rows were
 * therefore defaulted to 'MLS' and started appearing in the MLS file list —
 * RES-2026-3311 was the reported case.
 *
 * Runs after 120000 and is safe to re-run: it only promotes rows to 'OSS', never
 * back to 'MLS', so a hand-corrected row is never undone.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /** Keep in step with the 2026_08_07_120000 migration's list. */
    private const OSS_SUB_SOURCES = ['OP Change of Ownership', 'OP Change of Name', 'OSS OP'];

    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'system_sub_type')) {
            return;
        }

        DB::connection('sqlsrv')->table('mls_file_no')
            ->whereIn('sub_source', self::OSS_SUB_SOURCES)
            ->where(function ($q) {
                $q->whereNull('system_sub_type')
                    ->orWhere('system_sub_type', '<>', 'OSS');
            })
            ->update(['system_sub_type' => 'OSS']);
    }

    public function down(): void
    {
        // Not reversed: the previous values were wrong, and 120000's down()
        // drops the column outright.
    }
};
