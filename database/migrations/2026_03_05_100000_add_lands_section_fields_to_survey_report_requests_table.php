<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('sqlsrv')->table('survey_report_requests', function (Blueprint $table) {
            $table->date('lands_section_date')->nullable()->after('serial_no');
            $table->string('lands_section_name')->nullable()->after('lands_section_date');
            $table->string('lands_section_rank')->nullable()->after('lands_section_name');
            $table->string('lands_section_signature')->nullable()->after('lands_section_rank');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('survey_report_requests', function (Blueprint $table) {
            $table->dropColumn([
                'lands_section_date',
                'lands_section_name',
                'lands_section_rank',
                'lands_section_signature',
            ]);
        });
    }
};
