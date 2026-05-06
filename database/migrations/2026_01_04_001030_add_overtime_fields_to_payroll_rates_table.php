<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('payroll_rates', function (Blueprint $table) {
            $columns = Schema::connection('sqlsrv')->getColumnListing('payroll_rates');
            
            if (!in_array('allow_overtime', $columns)) {
                $table->boolean('allow_overtime')->default(false);
            }
            if (!in_array('overtime_hours_cap', $columns)) {
                $table->decimal('overtime_hours_cap', 6, 2)->nullable();
            }
            if (!in_array('overtime_notes', $columns)) {
                $table->string('overtime_notes', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('payroll_rates', function (Blueprint $table) {
            $table->dropColumn(['allow_overtime', 'overtime_hours_cap', 'overtime_notes']);
        });
    }
};
