<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('user_absence_counters')) {
            Schema::connection('sqlsrv')->create('user_absence_counters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->unsignedTinyInteger('consecutive_absences')->default(0);
                $table->unsignedTinyInteger('monthly_absences')->default(0);
                $table->string('month_key', 7)->nullable();
                $table->date('last_absent_date')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('user_absence_counters');
    }
};
