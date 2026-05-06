<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('action_sheet_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mother_application_id')->nullable();
            $table->unsignedBigInteger('sub_application_id')->nullable();
            $table->string('application_type', 16);
            $table->string('decision', 16);
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamps();

            $table->index(['mother_application_id']);
            $table->index(['sub_application_id']);
            $table->index(['application_type', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('action_sheet_decisions');
    }
};
