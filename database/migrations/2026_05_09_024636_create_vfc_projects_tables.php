<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('sqlsrv')->create('vfc_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_name');
            $table->string('project_code')->unique();
            $table->integer('number_of_items')->default(1);
            $table->string('street')->nullable();
            $table->string('district')->nullable();
            $table->string('lga')->nullable();
            $table->string('state')->default('Kano');
            $table->string('project_type');
            $table->string('project_type_other')->nullable();
            $table->unsignedBigInteger('user_id'); // Creator
            $table->timestamps();
        });

        Schema::connection('sqlsrv')->create('vfc_project_workers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id'); // User assigned as worker
            $table->string('worker_code'); // WRK-PROJECT-001
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('vfc_projects')->onDelete('cascade');
        });

        Schema::connection('sqlsrv')->table('valuation_compensations', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('worker_id')->nullable(); // The worker_code from project_workers
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('valuation_compensations', function (Blueprint $table) {
            $table->dropColumn(['project_id', 'worker_id']);
        });
        Schema::connection('sqlsrv')->dropIfExists('vfc_project_workers');
        Schema::connection('sqlsrv')->dropIfExists('vfc_projects');
    }
};
