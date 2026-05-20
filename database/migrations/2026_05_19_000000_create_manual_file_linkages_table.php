<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::connection('sqlsrv')->hasTable('manual_file_linkages')) {
            Schema::connection('sqlsrv')->create('manual_file_linkages', function (Blueprint $table) {
                $table->id();
                $table->string('workflow_type', 100); // 'Subdivision', 'Merger', 'Plot Extension', 'Change of Purpose'
                $table->text('old_file_numbers'); // JSON array of old file numbers
                $table->string('new_file_number', 255);
                $table->string('prop_id', 50)->nullable();
                $table->string('applicant_name', 255)->nullable();
                $table->text('remarks')->nullable();
                $table->string('processed_by', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('manual_file_linkages');
    }
};
