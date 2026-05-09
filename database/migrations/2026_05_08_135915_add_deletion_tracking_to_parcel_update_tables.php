<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tables = [
            'plot_subdivision_applications',
            'plot_merger_applications',
            'plot_extension_applications',
            'plot_application_sizes'
        ];

        foreach ($tables as $tableName) {
            Schema::connection('sqlsrv')->table($tableName, function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn($table->getTable(), 'is_deleted')) {
                    $table->tinyInteger('is_deleted')->default(0)->nullable();
                }
                if (!Schema::connection('sqlsrv')->hasColumn($table->getTable(), 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
                if (!Schema::connection('sqlsrv')->hasColumn($table->getTable(), 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tables = [
            'plot_subdivision_applications',
            'plot_merger_applications',
            'plot_extension_applications',
            'plot_application_sizes'
        ];

        foreach ($tables as $tableName) {
            Schema::connection('sqlsrv')->table($tableName, function (Blueprint $table) {
                $table->dropColumn(['is_deleted', 'deleted_by', 'deleted_at']);
            });
        }
    }
};


