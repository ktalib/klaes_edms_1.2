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
        if (!Schema::connection('sqlsrv')->hasTable('mother_applications')) {
            return;
        }

        Schema::connection('sqlsrv')->table('mother_applications', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('mother_applications', 'ksip_ref_no')) {
                $table->string('ksip_ref_no', 255)->nullable()->comment('KSIP reference number for primary applications');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('mother_applications')) {
            return;
        }

        Schema::connection('sqlsrv')->table('mother_applications', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('mother_applications', 'ksip_ref_no')) {
                $table->dropColumn('ksip_ref_no');
            }
        });
    }
};
