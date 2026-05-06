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
        Schema::connection('sqlsrv')->create('unit_memo_uploads', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('unit_application_id');
            $table->string('file_path');
            $table->string('original_name');
            $table->bigInteger('uploaded_by');
            $table->datetime('uploaded_at');
            $table->timestamps();

            // Add index on unit_application_id for faster lookups
            $table->index('unit_application_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('unit_memo_uploads');
    }
};
