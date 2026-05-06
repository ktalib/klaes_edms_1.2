<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('notifications')) {
            Schema::connection('sqlsrv')->create('notifications', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('module', 100)->nullable();
                $table->string('name', 150)->nullable();
                $table->string('type', 100)->nullable();
                $table->string('title', 255)->nullable();
                $table->string('subject', 255)->nullable();
                $table->text('message')->nullable();
                $table->text('body')->nullable();
                $table->json('short_code')->nullable();
                $table->json('data')->nullable();
                $table->boolean('enabled_email')->default(false);
                $table->boolean('enabled_sms')->default(false);
                $table->boolean('is_read')->default(false);
                $table->dateTime('read_at')->nullable();
                $table->timestamps();

                $table->index('parent_id');
                $table->index('user_id');
                $table->index('module');
                $table->index('is_read');
                $table->index('type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('notifications')) {
            Schema::connection('sqlsrv')->drop('notifications');
        }
    }
};
