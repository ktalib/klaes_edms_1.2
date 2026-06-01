<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->create('spa_memos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('spa_application_id')->index();
            $table->string('memo_no', 50)->nullable()->unique(); // SPA/YYYY/####
            $table->unsignedBigInteger('prepared_by')->nullable();  // FK users.id
            $table->string('forwarded_to', 255)->nullable();
            $table->timestamp('forwarded_at')->nullable();
            // pending | approved | rejected
            $table->string('commissioner_decision', 20)->default('pending')->index();
            $table->text('commissioner_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('created_by', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('spa_memos');
    }
};
