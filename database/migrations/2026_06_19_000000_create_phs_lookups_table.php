<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (!Schema::connection('sqlsrv')->hasTable('phs_lookups')) {
            Schema::connection('sqlsrv')->create('phs_lookups', function (Blueprint $table) {
                $table->id();
                $table->string('type', 50);   // job_title | department
                $table->string('name', 255);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['type', 'name']);
            });
        }

        // Seed common defaults only when empty so re-running never clobbers
        // values that organizations have since contributed via the "Other" box.
        if (DB::connection('sqlsrv')->table('phs_lookups')->count() === 0) {
            $now = now();

            $jobTitles = [
                'Managing Director', 'Director', 'Company Secretary', 'Legal Officer',
                'Compliance Officer', 'Credit Officer', 'Relationship Manager',
                'Solicitor', 'Partner', 'Associate', 'Administrator', 'Accountant',
            ];

            $departments = [
                'Legal', 'Compliance', 'Credit', 'Operations', 'Risk Management',
                'Administration', 'Finance', 'Corporate Services', 'Company Secretariat',
            ];

            $rows = [];
            foreach ($jobTitles as $name) {
                $rows[] = ['type' => 'job_title', 'name' => $name, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now];
            }
            foreach ($departments as $name) {
                $rows[] = ['type' => 'department', 'name' => $name, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now];
            }

            DB::connection('sqlsrv')->table('phs_lookups')->insert($rows);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('phs_lookups');
    }
};
