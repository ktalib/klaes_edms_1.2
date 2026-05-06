<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApplicationTypeToApplicationTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add application_type to mother_applications (PRIMARY applications)
        if (Schema::connection('sqlsrv')->hasTable('mother_applications')) {
            Schema::connection('sqlsrv')->table('mother_applications', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('mother_applications', 'application_type')) {
                    $table->string('application_type', 50)->nullable()->after('land_use');
                }
            });
        }

        // Add application_type to subapplications (PUA and SUA applications)
        if (Schema::connection('sqlsrv')->hasTable('subapplications')) {
            Schema::connection('sqlsrv')->table('subapplications', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('subapplications', 'application_type')) {
                    $table->string('application_type', 50)->nullable()->after('land_use');
                }
            });
        }

        // Add application_type to mother_application_draft (PRIMARY drafts)
        if (Schema::connection('sqlsrv')->hasTable('mother_application_draft')) {
            Schema::connection('sqlsrv')->table('mother_application_draft', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('mother_application_draft', 'application_type')) {
                    $table->string('application_type', 50)->nullable()->after('land_use');
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
        // Remove from mother_applications
        if (Schema::connection('sqlsrv')->hasTable('mother_applications')) {
            Schema::connection('sqlsrv')->table('mother_applications', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('mother_applications', 'application_type')) {
                    $table->dropColumn('application_type');
                }
            });
        }

        // Remove from subapplications
        if (Schema::connection('sqlsrv')->hasTable('subapplications')) {
            Schema::connection('sqlsrv')->table('subapplications', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('subapplications', 'application_type')) {
                    $table->dropColumn('application_type');
                }
            });
        }

        // Remove from mother_application_draft
        if (Schema::connection('sqlsrv')->hasTable('mother_application_draft')) {
            Schema::connection('sqlsrv')->table('mother_application_draft', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('mother_application_draft', 'application_type')) {
                    $table->dropColumn('application_type');
                }
            });
        }
    }
}
