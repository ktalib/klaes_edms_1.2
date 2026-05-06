<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add middle_name to mother_applications table if it exists
        if (Schema::connection('sqlsrv')->hasTable('mother_applications')) {
            Schema::connection('sqlsrv')->table('mother_applications', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('mother_applications', 'middle_name')) {
                    $table->string('middle_name', 100)->nullable()->after('first_name')->comment('Middle name for individual applicants');
                }
            });
        }

        // Add middle_name to subapplications table if it exists
        if (Schema::connection('sqlsrv')->hasTable('subapplications')) {
            Schema::connection('sqlsrv')->table('subapplications', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('subapplications', 'middle_name')) {
                    $table->string('middle_name', 100)->nullable()->after('first_name')->comment('Middle name for individual applicants');
                }
            });
        }

        // Add middle_name to mother_application_draft table if it exists
        if (Schema::connection('sqlsrv')->hasTable('mother_application_draft')) {
            Schema::connection('sqlsrv')->table('mother_application_draft', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('mother_application_draft', 'middle_name')) {
                    $table->string('middle_name', 100)->nullable()->after('first_name')->comment('Middle name for individual applicants');
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
        // Remove middle_name from mother_applications table
        if (Schema::connection('sqlsrv')->hasTable('mother_applications')) {
            Schema::connection('sqlsrv')->table('mother_applications', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('mother_applications', 'middle_name')) {
                    $table->dropColumn('middle_name');
                }
            });
        }

        // Remove middle_name from subapplications table
        if (Schema::connection('sqlsrv')->hasTable('subapplications')) {
            Schema::connection('sqlsrv')->table('subapplications', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('subapplications', 'middle_name')) {
                    $table->dropColumn('middle_name');
                }
            });
        }

        // Remove middle_name from mother_application_draft table
        if (Schema::connection('sqlsrv')->hasTable('mother_application_draft')) {
            Schema::connection('sqlsrv')->table('mother_application_draft', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('mother_application_draft', 'middle_name')) {
                    $table->dropColumn('middle_name');
                }
            });
        }
    }
};