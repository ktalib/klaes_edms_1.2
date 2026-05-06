<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        Schema::connection('sqlsrv')->table('oss_applications', function (Blueprint $table) {
            // ── Residential-specific fields ──
            $table->string('age', 10)->nullable()->after('applicant_name');
            $table->string('sex', 20)->nullable()->after('age');
            $table->string('marital_status', 50)->nullable()->after('sex');
            $table->string('husband_name_address', 500)->nullable()->after('marital_status');
            $table->text('residential_address')->nullable()->after('husband_name_address');
            $table->text('correspondence_address')->nullable()->after('residential_address');
            $table->text('business_address')->nullable()->after('correspondence_address');
            $table->string('nationality', 255)->nullable()->after('business_address');
            $table->string('occupation', 255)->nullable()->after('nationality');
            $table->string('annual_income', 100)->nullable()->after('occupation');
            $table->string('prev_allocated', 10)->nullable()->after('annual_income');         // yes/no
            $table->text('prev_allocation_details')->nullable()->after('prev_allocated');      // JSON for up to 3 entries

            // ── Commercial-specific fields ──
            $table->string('home_domicile', 255)->nullable()->after('prev_allocation_details');
            $table->string('occupation_or_business', 255)->nullable()->after('home_domicile');
            $table->string('nature_of_commerce', 500)->nullable()->after('occupation_or_business');
            $table->string('company_registered_under', 500)->nullable()->after('nature_of_commerce');
            $table->string('registration_particulars', 500)->nullable()->after('company_registered_under');
            $table->string('business_location', 500)->nullable()->after('registration_particulars');
            $table->string('annual_income_anticipation', 100)->nullable()->after('business_location');
            $table->string('prev_land_purpose', 500)->nullable()->after('annual_income_anticipation');
            $table->text('intended_activities')->nullable()->after('prev_land_purpose');

            // ── Industrial-specific fields ──
            $table->string('nature_of_occupation', 500)->nullable()->after('intended_activities');
            $table->string('annual_income_turnover', 100)->nullable()->after('nature_of_occupation');
            $table->string('number_of_employees', 50)->nullable()->after('annual_income_turnover');
            $table->string('nature_of_industrial', 500)->nullable()->after('number_of_employees');
            $table->text('waste_disposal_requirements')->nullable()->after('nature_of_industrial');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('oss_applications', function (Blueprint $table) {
            $table->dropColumn([
                'age', 'sex', 'marital_status', 'husband_name_address',
                'residential_address', 'correspondence_address', 'business_address',
                'nationality', 'occupation', 'annual_income', 'prev_allocated',
                'prev_allocation_details', 'home_domicile', 'occupation_or_business',
                'nature_of_commerce', 'company_registered_under', 'registration_particulars',
                'business_location', 'annual_income_anticipation', 'prev_land_purpose',
                'intended_activities', 'nature_of_occupation', 'annual_income_turnover',
                'number_of_employees', 'nature_of_industrial', 'waste_disposal_requirements',
            ]);
        });
    }
};
