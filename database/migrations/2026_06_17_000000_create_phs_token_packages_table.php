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
        if (!Schema::connection('sqlsrv')->hasTable('phs_token_packages')) {
            Schema::connection('sqlsrv')->create('phs_token_packages', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 50)->unique();
                $table->integer('tokens');
                $table->decimal('price', 18, 2);
                $table->integer('team_members')->default(2);
                $table->string('description', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('display_order')->default(0);
                $table->timestamps();
            });
        }

        // Seed the current packages (matches the previous hardcoded values) only
        // if the table is empty, so re-running never clobbers admin edits.
        if (DB::connection('sqlsrv')->table('phs_token_packages')->count() === 0) {
            $now = now();
            DB::connection('sqlsrv')->table('phs_token_packages')->insert([
                ['name' => 'Starter',      'slug' => 'starter',      'tokens' => 2000,  'price' => 50000,  'team_members' => 2,  'description' => 'For small teams getting started.',      'is_active' => 1, 'display_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Professional', 'slug' => 'professional', 'tokens' => 5000,  'price' => 100000, 'team_members' => 5,  'description' => 'For growing teams with regular searches.', 'is_active' => 1, 'display_order' => 2, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Enterprise',   'slug' => 'enterprise',   'tokens' => 10000, 'price' => 180000, 'team_members' => 10, 'description' => 'For large, high-volume organizations.',   'is_active' => 1, 'display_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('phs_token_packages');
    }
};
