<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        $tables = [
            'plot_subdivision_applications',
            'plot_merger_applications',
            'plot_extension_applications',
            'change_of_purpose_applications'
        ];

        foreach ($tables as $tableName) {
            Schema::connection('sqlsrv')->table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'application_generated_at')) {
                    $table->dateTime('application_generated_at')->nullable();
                }
                if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'recommendation_generated_at')) {
                    $table->dateTime('recommendation_generated_at')->nullable();
                }
                if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'knupda_fee')) {
                    $table->decimal('knupda_fee', 18, 2)->nullable();
                }
                if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'knupda_status')) {
                    $table->string('knupda_status', 50)->default('Pending');
                }
                if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'knupda_remarks')) {
                    $table->text('knupda_remarks')->nullable();
                }

                // Table specific columns
                if ($tableName === 'plot_subdivision_applications') {
                    if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'land_value')) {
                        $table->decimal('land_value', 18, 2)->nullable();
                    }
                }
                if ($tableName === 'change_of_purpose_applications') {
                    if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'land_size')) {
                        $table->decimal('land_size', 18, 2)->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'plot_subdivision_applications',
            'plot_merger_applications',
            'plot_extension_applications',
            'change_of_purpose_applications'
        ];

        foreach ($tables as $tableName) {
            Schema::connection('sqlsrv')->table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'application_generated_at',
                    'recommendation_generated_at',
                    'knupda_fee',
                    'knupda_status',
                    'knupda_remarks'
                ]);
            });
        }
    }
};
