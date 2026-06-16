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
        if (!Schema::connection('sqlsrv')->hasTable('plot_extensions')) {
            Schema::connection('sqlsrv')->create('plot_extensions', function (Blueprint $table) {
                $table->id();
                // Original (selected) file number is retained as-is. NOT unique:
                // a single file may have multiple plot extensions over time.
                $table->string('original_file_no', 100)->index();
                $table->string('tracking_id', 50)->nullable();
                $table->string('file_name', 500)->nullable();
                $table->string('land_use', 50)->nullable();
                $table->unsignedBigInteger('purpose_id')->nullable();
                $table->text('location')->nullable();
                $table->string('lga', 255)->nullable();
                $table->string('district', 255)->nullable();
                $table->string('plot_no', 100)->nullable();
                $table->string('tp_no', 100)->nullable();
                $table->string('customer_type', 100)->nullable();
                $table->string('phone_no', 100)->nullable();
                $table->string('address', 1000)->nullable();
                // Whether the original file was found in the indexing/lookup (auto-populated)
                $table->boolean('is_indexed')->default(0);
                $table->string('created_by', 255);
                $table->boolean('is_deleted')->default(0);
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
        Schema::connection('sqlsrv')->dropIfExists('plot_extensions');
    }
};
