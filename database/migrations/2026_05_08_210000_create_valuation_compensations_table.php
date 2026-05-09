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
        Schema::connection('sqlsrv')->create('valuation_compensations', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('our_ref')->nullable();
            $blueprint->string('your_ref')->nullable();
            $blueprint->date('valuation_date')->nullable();
            $blueprint->string('location')->nullable();
            $blueprint->string('owner_name')->nullable();
            $blueprint->string('building_type')->nullable();
            $blueprint->integer('building_count')->default(0);
            $blueprint->decimal('area_covered', 18, 2)->default(0);
            $blueprint->decimal('rate_of_cost', 18, 2)->default(0);
            $blueprint->decimal('compensation_amount', 18, 2)->default(0);
            $blueprint->string('account_number')->nullable();
            $blueprint->string('phone_number')->nullable();
            $blueprint->text('remarks')->nullable();
            $blueprint->tinyInteger('is_deleted')->default(0);
            $blueprint->unsignedBigInteger('user_id')->nullable();
            $blueprint->timestamps();

            // Index for performance and soft delete filter
            $blueprint->index('is_deleted');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('valuation_compensations');
    }
};
