<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phs_onboarding_requests', function (Blueprint $table) {
            $table->string('lsa_token', 64)->nullable()->unique()->after('request_letter_path');
            $table->string('lsa_signed_document_path', 500)->nullable()->after('lsa_token');
            $table->timestamp('lsa_signed_at')->nullable()->after('lsa_signed_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('phs_onboarding_requests', function (Blueprint $table) {
            $table->dropColumn(['lsa_token', 'lsa_signed_document_path', 'lsa_signed_at']);
        });
    }
};
