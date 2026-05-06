<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->create('sltr_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('sltr_number', 100)->nullable(); // e.g. SLTR-RES-2026-00001
            $table->string('applicant_name', 300)->nullable();
            $table->string('applicant_address', 500)->nullable();
            $table->date('application_date')->nullable();
            $table->string('location', 300)->nullable();
            $table->string('lga', 200)->nullable();
            $table->string('land_use', 200)->nullable();
            $table->string('plot_number', 100)->nullable();
            $table->integer('term')->nullable()->default(40); // years
            $table->decimal('ground_rent', 15, 2)->nullable()->default(0);
            $table->decimal('processing_fee', 15, 2)->nullable()->default(0);
            $table->string('purpose_of_clause', 300)->nullable();
            $table->text('notes')->nullable();

            // Workflow
            $table->string('status', 30)->nullable()->default('pending'); // pending, approved
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();

            // RofO generation
            $table->string('rofo_status', 30)->nullable()->default('pending'); // pending, generated
            $table->timestamp('rofo_generated_at')->nullable();
            $table->integer('rofo_print_count')->nullable()->default(0);
            $table->string('rofo_director_survey', 5)->nullable(); // YES / NO
            $table->string('rofo_licensed_surveyor', 5)->nullable(); // YES / NO
            $table->date('rofo_date_generated')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sltr_recommendations');
    }
};
