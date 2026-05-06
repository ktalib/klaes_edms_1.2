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
        Schema::connection('sqlsrv')->create('file_number_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_uuid', 36)->unique()->index();
            $table->string('prefix', 20)->index();
            $table->string('land_use', 20);
            $table->integer('year')->index();
            $table->integer('serial_number')->index();
            $table->string('session_id', 100)->nullable();
            $table->unsignedBigInteger('reserved_by')->nullable(); // user_id
            $table->string('reserved_by_name', 255)->nullable();
            $table->string('reserved_by_ip', 50)->nullable();
            $table->timestamp('reserved_at');
            $table->timestamp('expires_at')->index();
            $table->enum('status', ['reserved', 'confirmed', 'released', 'expired'])->default('reserved')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('generated_file_number', 255)->nullable(); // The actual file number generated
            $table->text('metadata')->nullable(); // JSON field for additional data
            $table->timestamps();

            // Composite index for quick lookups
            $table->index(['prefix', 'year', 'serial_number']);
            $table->index(['status', 'expires_at']);
        });

        // Add comment to table
        DB::connection('sqlsrv')->statement("
            EXEC sp_addextendedproperty 
                @name = N'MS_Description', 
                @value = N'Stores temporary reservations of file numbers to prevent race conditions when multiple users generate file numbers simultaneously', 
                @level0type = N'SCHEMA', @level0name = 'dbo',
                @level1type = N'TABLE',  @level1name = 'file_number_reservations'
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('file_number_reservations');
    }
};
