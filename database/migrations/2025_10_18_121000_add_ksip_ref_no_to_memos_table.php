<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('memos', 'allocation_ref_no')) {
            Schema::connection('sqlsrv')->table('memos', function (Blueprint $table) {
                $table->string('allocation_ref_no', 255)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('memos', 'allocation_ref_no')) {
            Schema::connection('sqlsrv')->table('memos', function (Blueprint $table) {
                $table->dropColumn('allocation_ref_no');
            });
        }
    }
};
