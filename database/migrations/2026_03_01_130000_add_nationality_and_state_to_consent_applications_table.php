<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_applications', function (Blueprint $table) {
            $table->string('nationality', 120)->nullable()->after('postal_address_gsm');
            $table->string('state_of_origin', 120)->nullable()->after('nationality');
        });
    }

    public function down(): void
    {
        Schema::table('consent_applications', function (Blueprint $table) {
            $table->dropColumn(['nationality', 'state_of_origin']);
        });
    }
};
