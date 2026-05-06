<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        // Add merger_group_id only if the column doesn't already exist
        if (!Schema::connection('sqlsrv')->hasColumn('pra', 'merger_group_id')) {
            Schema::connection('sqlsrv')->table('pra', function (Blueprint $table) {
                $table->string('merger_group_id', 36)->nullable()->after('system_source');
            });
        }

        // Add is_merger_op flag so queries can quickly identify OP rows in a merger set
        if (!Schema::connection('sqlsrv')->hasColumn('pra', 'is_merger_op')) {
            Schema::connection('sqlsrv')->table('pra', function (Blueprint $table) {
                $table->tinyInteger('is_merger_op')->default(0)->nullable()->after('merger_group_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('pra', function (Blueprint $table) {
            $table->dropColumn(['merger_group_id', 'is_merger_op']);
        });
    }
};
