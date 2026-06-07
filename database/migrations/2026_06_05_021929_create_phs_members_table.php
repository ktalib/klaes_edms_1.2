<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->create('phs_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('phs_institution_id');
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->string('job_title', 150)->nullable();
            $table->string('department', 150)->nullable();
            $table->string('user_type', 20)->default('regular_user'); // super_admin | regular_user
            $table->string('access_role', 30)->default('search_only'); // search_only | report_viewer | analytics_viewer
            $table->integer('tokens_used')->default(0);
            $table->string('status', 20)->default('active'); // active | suspended
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('phs_institution_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('phs_members');
    }
};
