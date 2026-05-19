<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tables = [
            'change_of_purpose_applications',
            'change_of_name_applications',
            'plot_subdivision_applications',
            'plot_merger_applications',
            'plot_extension_applications'
        ];

        foreach ($tables as $tableName) {
            if (Schema::connection('sqlsrv')->hasTable($tableName)) {
                Schema::connection('sqlsrv')->table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'is_deleted')) {
                        $table->tinyInteger('is_deleted')->default(0);
                    }
                    if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'deleted_by')) {
                        $table->unsignedBigInteger('deleted_by')->nullable();
                    }
                    if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'deleted_at')) {
                        $table->dateTime('deleted_at')->nullable();
                    }
                });
            }
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
            'change_of_purpose_applications',
            'change_of_name_applications',
            'plot_subdivision_applications',
            'plot_merger_applications',
            'plot_extension_applications'
        ];

        foreach ($tables as $tableName) {
            if (Schema::connection('sqlsrv')->hasTable($tableName)) {
                Schema::connection('sqlsrv')->table($tableName, function (Blueprint $table) use ($tableName) {
                    $cols = [];
                    if (Schema::connection('sqlsrv')->hasColumn($tableName, 'is_deleted')) {
                        $cols[] = 'is_deleted';
                    }
                    if (Schema::connection('sqlsrv')->hasColumn($tableName, 'deleted_by')) {
                        $cols[] = 'deleted_by';
                    }
                    if (Schema::connection('sqlsrv')->hasColumn($tableName, 'deleted_at')) {
                        $cols[] = 'deleted_at';
                    }
                    if (!empty($cols)) {
                        $table->dropColumn($cols);
                    }
                });
            }
        }
    }
};
