<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)->create('spa_applications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('file_number', 255)->nullable();
            $table->string('tracking_id', 100)->nullable()->index();
            $table->unsignedBigInteger('file_indexing_id')->nullable();
            $table->boolean('is_indexed')->default(false);
            $table->string('owner_name', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('lga', 255)->nullable();
            $table->string('land_use_type', 255)->nullable(); // approved use from file indexing
            $table->string('existing_use', 255)->nullable();  // actual use found on ground
            $table->string('proposed_use', 255)->nullable();
            $table->string('scenario', 1)->nullable();        // A = compelled, B = already applied
            // open | in_progress | memo_stage | approved | certificate_issued | closed
            $table->string('status', 50)->default('open');
            $table->string('created_by', 255)->nullable();
            $table->timestamps();

            $table->index('file_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('spa_applications');
    }
};
