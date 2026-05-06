<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('kangis_print_label_batches', function (Blueprint $table) {
            $table->unsignedInteger('sys_batch_no')->nullable()->after('prefix')
                  ->comment('The sys_batch_no from kangis_grouping — unique per prefix');

            $table->unique(['prefix', 'sys_batch_no'], 'uq_kangis_batch_prefix_sysbatchno');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('kangis_print_label_batches', function (Blueprint $table) {
            $table->dropUnique('uq_kangis_batch_prefix_sysbatchno');
            $table->dropColumn('sys_batch_no');
        });
    }
};
