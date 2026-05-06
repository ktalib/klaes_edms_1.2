<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_applications', function (Blueprint $table) {
            $table->date('application_submitted_date')->nullable()->after('application_date');
            $table->string('right_of_occupancy_number', 120)->nullable()->after('application_submitted_date');
            $table->string('purpose_of_right_of_occupancy', 150)->nullable()->after('right_of_occupancy_number');
            $table->string('original_holder_name', 150)->nullable()->after('purpose_of_right_of_occupancy');
            $table->text('correspondence_address')->nullable()->after('original_holder_name');
            $table->string('postal_address_gsm', 150)->nullable()->after('correspondence_address');
            $table->string('nationality_state_of_origin', 150)->nullable()->after('postal_address_gsm');
            $table->string('stage_of_development', 150)->nullable()->after('nationality_state_of_origin');
            $table->string('location_of_right_of_occupancy', 150)->nullable()->after('stage_of_development');
            $table->string('date_of_grant_purpose', 150)->nullable()->after('location_of_right_of_occupancy');
            $table->text('special_mortgage_terms')->nullable()->after('date_of_grant_purpose');
        });
    }

    public function down(): void
    {
        Schema::table('consent_applications', function (Blueprint $table) {
            $table->dropColumn([
                'application_submitted_date',
                'right_of_occupancy_number',
                'purpose_of_right_of_occupancy',
                'original_holder_name',
                'correspondence_address',
                'postal_address_gsm',
                'nationality_state_of_origin',
                'stage_of_development',
                'location_of_right_of_occupancy',
                'date_of_grant_purpose',
                'special_mortgage_terms'
            ]);
        });
    }
};
