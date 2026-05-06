<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('subapplications')) {
            return;
        }

        if (Schema::connection('sqlsrv')->hasColumn('subapplications', 'allocation_ref_no')) {
            return;
        }

        Schema::connection('sqlsrv')->table('subapplications', function (Blueprint $table) {
            $table->string('allocation_ref_no', 255)->nullable()->comment('Allocation reference number for SUA applications');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('subapplications')) {
            return;
        }

        if (!Schema::connection('sqlsrv')->hasColumn('subapplications', 'allocation_ref_no')) {
            return;
        }

        Schema::connection('sqlsrv')->table('subapplications', function (Blueprint $table) {
            $table->dropColumn('allocation_ref_no');
        });
    }
};
