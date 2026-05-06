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
            // ── Agricultural-specific field ──
            $table->string('nature_of_agricultural', 500)->nullable();

            // ── Business Location Address builder sub-fields (agricultural) ──
            $table->string('agr_biz_plot', 100)->nullable();
            $table->string('agr_biz_street', 255)->nullable();
            $table->string('agr_biz_street_other', 255)->nullable();
            $table->string('agr_biz_district', 255)->nullable();
            $table->string('agr_biz_district_other', 255)->nullable();
            $table->string('agr_biz_lga', 255)->nullable();
            $table->string('agr_biz_state', 255)->nullable();

            // ── Correspondence Address builder sub-fields (agricultural) ──
            $table->string('agr_corr_plot', 100)->nullable();
            $table->string('agr_corr_street', 255)->nullable();
            $table->string('agr_corr_street_other', 255)->nullable();
            $table->string('agr_corr_district', 255)->nullable();
            $table->string('agr_corr_district_other', 255)->nullable();
            $table->string('agr_corr_lga', 255)->nullable();
            $table->string('agr_corr_state', 255)->nullable();
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('oss_applications', function (Blueprint $table) {
            $table->dropColumn([
                'nature_of_agricultural',
                'agr_biz_plot', 'agr_biz_street', 'agr_biz_street_other',
                'agr_biz_district', 'agr_biz_district_other', 'agr_biz_lga', 'agr_biz_state',
                'agr_corr_plot', 'agr_corr_street', 'agr_corr_street_other',
                'agr_corr_district', 'agr_corr_district_other', 'agr_corr_lga', 'agr_corr_state',
            ]);
        });
    }
};
