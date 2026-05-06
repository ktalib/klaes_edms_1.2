<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection to use for this migration.
     */
    protected $connection = 'sqlsrv';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('duplicate_fileno', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('registry');
            $table->string('file_number');
            $table->string('file_title')->nullable();
            $table->string('plot_number')->nullable();
            $table->string('location')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('file_number', 'duplicate_fileno_file_number_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_fileno');
    }
};
