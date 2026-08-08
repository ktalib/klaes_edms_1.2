<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records which system a file number was commissioned from.
 *
 * The Lands One Stop Shop has no commissioning writer of its own — it deep-links
 * into the MLS generator page and posts to the same endpoint — so an OSS row and
 * an MLS row were previously indistinguishable. The MLS file list worked around
 * that by hiding source IN ('OP Resettlement','OP Direct Allocation'), but the
 * generator writes those same values for its own OP allocations, so files
 * genuinely commissioned in MLS File Commissioning (e.g. RES-2026-3312) were
 * hidden and could not be searched for.
 *
 *   MLS  commissioned in MLS File Commissioning — visible in the MLS file list
 *   OSS  commissioned from a One Stop Shop entry point — hidden from that list
 *
 * Backfill: only rows carrying a definite OSS marker are set to 'OSS'; the rest
 * are 'MLS'. Historical single-OP OSS commissions left no marker and cannot be
 * distinguished, so they land in 'MLS' and become visible — an accepted
 * trade-off, since the alternative kept real MLS files hidden. Correct any that
 * surface by setting system_sub_type = 'OSS' on the individual row.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * sub_source values written only by OSS entry points. 'OP Change of Ownership'
     * is what the OSS page posts today; 'OP Change of Name' is its earlier label.
     */
    public const OSS_SUB_SOURCES = ['OP Change of Ownership', 'OP Change of Name', 'OSS OP'];

    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('mls_file_no')) {
            return;
        }

        if (!Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'system_sub_type')) {
            Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $t) {
                $t->string('system_sub_type', 20)->nullable();
            });
        }

        // Definite OSS markers: these sub_source values are only ever written by
        // the OSS change-of-ownership page (resources/views/lands_one_stop_shop/
        // applications.blade.php), and op_batch only by OSS batch OP commissioning.
        DB::connection('sqlsrv')->table('mls_file_no')
            ->whereNull('system_sub_type')
            ->where(function ($q) {
                $q->whereIn('sub_source', self::OSS_SUB_SOURCES)
                    ->orWhere(function ($sq) {
                        $sq->whereNotNull('op_batch')
                            ->whereRaw("LTRIM(RTRIM(op_batch)) <> ''");
                    });
            })
            ->update(['system_sub_type' => 'OSS']);

        // Everything else is treated as MLS-commissioned.
        DB::connection('sqlsrv')->table('mls_file_no')
            ->whereNull('system_sub_type')
            ->update(['system_sub_type' => 'MLS']);
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'system_sub_type')) {
            return;
        }

        Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $t) {
            $t->dropColumn(['system_sub_type']);
        });
    }
};
