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
        Schema::create('grouping', function (Blueprint $table) {
            $table->id();
            $table->string('pseudo_fileno')->nullable();
            $table->string('mls_fileno')->nullable();
            $table->tinyInteger('mapping')->default(0)->comment('0 or 1 mapping status');
            $table->string('group')->nullable();
            $table->string('batch_no')->nullable();
            $table->string('mdc_batch_no')->nullable();
            $table->string('sys_batch_no')->nullable();
            $table->string('shelf_rack')->nullable();
            $table->date('date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('indexed_by')->nullable();
            $table->date('date_index')->nullable();
            $table->year('year')->nullable();
            $table->string('landuse')->nullable();
            
            // System columns
            $table->timestamps(); // created_at and updated_at
            $table->string('updated_by')->nullable();
            $table->softDeletes(); // deleted_at
            $table->string('deleted_by')->nullable();
            
            // Indexes for better performance
            $table->index('pseudo_fileno');
            $table->index('mls_fileno');
            $table->index('mapping');
            $table->index('batch_no');
            $table->index('shelf_rack');
            $table->index('date');
            $table->index('created_by');
            $table->index('indexed_by');
            $table->index('year');
            $table->index('landuse');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grouping');
    }
};
