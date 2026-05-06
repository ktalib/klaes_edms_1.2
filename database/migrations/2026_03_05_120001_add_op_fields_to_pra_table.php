<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('pra', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('pra', 'op_serial_number')) {
                $table->string('op_serial_number', 100)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('pra', 'purpose')) {
                $table->string('purpose', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('pra', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('pra', 'op_serial_number')) {
                $table->dropColumn('op_serial_number');
            }
            if (Schema::connection('sqlsrv')->hasColumn('pra', 'purpose')) {
                $table->dropColumn('purpose');
            }
        });
    }
};
