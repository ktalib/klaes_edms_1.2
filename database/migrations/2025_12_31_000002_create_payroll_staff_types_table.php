<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('payroll_staff_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_payroll_eligible')->default(false);
            $table->timestamps();
        });

        DB::connection('sqlsrv')->table('payroll_staff_types')->insert([
            [
                'code' => 'MDC',
                'name' => 'Mass Data Capture',
                'description' => 'Mass Data Capture workforce',
                'is_payroll_eligible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'MLPP',
                'name' => 'Ministry of Land and Physical Planning',
                'description' => 'Ministry staff (not payroll eligible)',
                'is_payroll_eligible' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('payroll_staff_types');
    }
};
