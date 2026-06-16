<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phs_onboarding_requests', function (Blueprint $table) {
            $table->string('request_letter_path', 500)->nullable()->after('additional_documents');
        });
    }

    public function down(): void
    {
        Schema::table('phs_onboarding_requests', function (Blueprint $table) {
            $table->dropColumn('request_letter_path');
        });
    }
};
