<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Table for the pool of security paper codes
        Schema::connection('sqlsrv')->create('security_paper_codes', function (Blueprint $table) {
            $table->id();
            $table->string('paper_code')->unique();
            $table->boolean('is_used')->default(false);
            $table->string('assigned_to_type')->nullable(); // 'ST' or 'LANDS'
            $table->unsignedBigInteger('assigned_to_id')->nullable();
            $table->string('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });

        // Add security_paper_code to rofo table for ST RoFO
        if (Schema::connection('sqlsrv')->hasTable('rofo')) {
            Schema::connection('sqlsrv')->table('rofo', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('rofo', 'security_paper_code')) {
                    $table->string('security_paper_code')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('security_paper_codes');
        
        if (Schema::connection('sqlsrv')->hasTable('rofo')) {
            Schema::connection('sqlsrv')->table('rofo', function (Blueprint $table) {
                $table->dropColumn('security_paper_code');
            });
        }
    }
};
