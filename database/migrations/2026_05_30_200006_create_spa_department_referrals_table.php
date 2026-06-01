<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->create('spa_department_referrals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('spa_application_id')->index();
            $table->string('file_number', 255)->nullable();
            $table->string('department', 30);  // cadastral | physical_planning | survey
            $table->unsignedBigInteger('referred_by')->nullable(); // FK users.id
            $table->date('referral_date')->nullable();
            $table->date('response_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('response_notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending | responded | approved | rejected
            $table->string('created_by', 255)->nullable();
            $table->timestamps();

            $table->index(['spa_application_id', 'department']);
            $table->index('department');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('spa_department_referrals');
    }
};
