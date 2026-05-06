<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('workstation_payment_structures')) {
            Schema::connection('sqlsrv')->create('workstation_payment_structures', function (Blueprint $table) {
                $table->id();
                $table->string('code');
                $table->string('role_name');
                $table->unsignedTinyInteger('work_days')->nullable();
                $table->decimal('base_salary', 12, 2);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['code', 'work_days', 'effective_from'], 'wps_code_days_from_unique');
            });
        }

        if (!Schema::connection('sqlsrv')->hasTable('workstation_payment_structure_aliases')) {
            Schema::connection('sqlsrv')->create('workstation_payment_structure_aliases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('structure_id')
                    ->constrained('workstation_payment_structures')
                    ->cascadeOnDelete();
                $table->string('alias');
                $table->string('normalized_alias')->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('workstation_payment_structure_aliases');
        Schema::connection('sqlsrv')->dropIfExists('workstation_payment_structures');
    }
};
