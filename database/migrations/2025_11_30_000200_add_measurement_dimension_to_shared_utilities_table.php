<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('shared_utilities')) {
            return;
        }

        Schema::connection('sqlsrv')->table('shared_utilities', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('shared_utilities', 'measurement_dimension')) {
                $table->string('measurement_dimension', 255)->nullable()->after('dimension');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('shared_utilities')) {
            return;
        }

        Schema::connection('sqlsrv')->table('shared_utilities', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('shared_utilities', 'measurement_dimension')) {
                $table->dropColumn('measurement_dimension');
            }
        });
    }
};
