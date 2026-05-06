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
        Schema::connection('sqlsrv')->create('land_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('file_number')->index();
            $table->string('applicant_name')->nullable();
            $table->string('purpose_of_clause')->nullable();
            $table->string('location')->nullable();
            $table->decimal('area_sqm', 18, 2)->nullable();
            $table->string('term')->nullable();
            $table->decimal('ground_rent', 18, 2)->nullable();
            $table->date('effective_date')->nullable();
            $table->decimal('premium', 18, 2)->nullable();
            $table->string('development_period')->nullable();
            $table->decimal('preparation_fees', 18, 2)->nullable();
            $table->string('land_use')->nullable();
            $table->date('meeting_date')->nullable();
            $table->text('recommendation')->nullable();
            $table->string('plot_number')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->datetime('created_at')->nullable()->default(DB::raw('GETDATE()'));
            $table->datetime('updated_at')->nullable()->default(DB::raw('GETDATE()'));
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('land_recommendations');
    }
};
