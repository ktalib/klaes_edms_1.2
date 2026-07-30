<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lookup table backing the "Officer Rank (Seniority)" dropdown on the user
 * create/edit/profile forms. Previously the options lived only in
 * config/file_request_priority.php; moving them here lets an admin (or the
 * "Other (specify)" flow on the form) add new ranks at runtime.
 *
 * `weight` mirrors config('file_request_priority.ranks') seniority — higher is
 * honored first for File Search Request prioritisation. Runtime-added ranks
 * default to weight 0 (lowest) until an admin assigns a seniority.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->create('officer_ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('weight')->default(0);      // seniority (higher = honored first)
            $table->integer('sort_order')->default(0);  // display order in the dropdown
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed from the existing config so behaviour is unchanged on day one.
        $now = now();
        $seed = [
            ['name' => 'Honorable Commissioner', 'weight' => 100],
            ['name' => 'Permanent Secretary',    'weight' => 90],
            ['name' => 'Director',               'weight' => 80],
            ['name' => 'Deputy Director',        'weight' => 70],
            ['name' => 'Assistant Director',     'weight' => 60],
            ['name' => 'Officer',                'weight' => 10],
        ];

        DB::connection('sqlsrv')->table('officer_ranks')->insert(array_map(function ($row) use ($now) {
            return [
                'name'       => $row['name'],
                'weight'     => $row['weight'],
                'sort_order' => $row['weight'], // seniority order == weight order
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $seed));
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('officer_ranks');
    }
};
