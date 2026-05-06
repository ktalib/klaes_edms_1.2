<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        // Only create if table doesn't already exist
        if (!Schema::connection('sqlsrv')->hasTable('attendance_daily_snapshots')) {
            Schema::connection('sqlsrv')->create('attendance_daily_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->date('attendance_date');
                $table->string('shift_code', 32);
                $table->enum('status', ['present', 'absent']);
                $table->dateTime('login_time')->nullable();
                $table->dateTime('logout_time')->nullable();
                $table->boolean('late_login')->default(false);
                $table->string('reason', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'attendance_date', 'shift_code'], 'attendance_snapshots_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('attendance_daily_snapshots');
    }
};
