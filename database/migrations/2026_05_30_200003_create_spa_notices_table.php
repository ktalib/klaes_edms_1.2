<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->create('spa_notices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('spa_application_id')->index();
            $table->string('file_number', 255)->nullable();
            $table->string('notice_type', 10);          // first | second
            $table->string('recipient_name', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->unsignedBigInteger('served_by')->nullable(); // FK users.id
            $table->date('served_date')->nullable();
            $table->date('scheduled_date')->nullable();  // when 2nd serve should trigger
            $table->boolean('sms_sent')->default(false);
            $table->timestamp('sms_sent_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending | served | failed
            $table->string('created_by', 255)->nullable();
            $table->timestamps();

            $table->index(['spa_application_id', 'notice_type']);
            $table->index('notice_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('spa_notices');
    }
};
