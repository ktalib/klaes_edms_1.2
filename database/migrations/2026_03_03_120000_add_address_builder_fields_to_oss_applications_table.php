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
            // ── Residential Address builder sub-fields ──
            $table->string('res_addr_plot', 100)->nullable();
            $table->string('res_addr_street', 255)->nullable();
            $table->string('res_addr_street_other', 255)->nullable();
            $table->string('res_addr_district', 255)->nullable();
            $table->string('res_addr_district_other', 255)->nullable();
            $table->string('res_addr_lga', 255)->nullable();
            $table->string('res_addr_state', 255)->nullable();

            // ── Correspondence Address builder sub-fields (residential) ──
            $table->string('res_corr_plot', 100)->nullable();
            $table->string('res_corr_street', 255)->nullable();
            $table->string('res_corr_street_other', 255)->nullable();
            $table->string('res_corr_district', 255)->nullable();
            $table->string('res_corr_district_other', 255)->nullable();
            $table->string('res_corr_lga', 255)->nullable();
            $table->string('res_corr_state', 255)->nullable();

            // ── Business Address builder sub-fields (residential) ──
            $table->string('res_biz_plot', 100)->nullable();
            $table->string('res_biz_street', 255)->nullable();
            $table->string('res_biz_street_other', 255)->nullable();
            $table->string('res_biz_district', 255)->nullable();
            $table->string('res_biz_district_other', 255)->nullable();
            $table->string('res_biz_lga', 255)->nullable();
            $table->string('res_biz_state', 255)->nullable();

            // ── Correspondence Address builder sub-fields (commercial) ──
            $table->string('com_corr_plot', 100)->nullable();
            $table->string('com_corr_street', 255)->nullable();
            $table->string('com_corr_street_other', 255)->nullable();
            $table->string('com_corr_district', 255)->nullable();
            $table->string('com_corr_district_other', 255)->nullable();
            $table->string('com_corr_lga', 255)->nullable();
            $table->string('com_corr_state', 255)->nullable();

            // ── Correspondence Address builder sub-fields (industrial) ──
            $table->string('ind_corr_plot', 100)->nullable();
            $table->string('ind_corr_street', 255)->nullable();
            $table->string('ind_corr_street_other', 255)->nullable();
            $table->string('ind_corr_district', 255)->nullable();
            $table->string('ind_corr_district_other', 255)->nullable();
            $table->string('ind_corr_lga', 255)->nullable();
            $table->string('ind_corr_state', 255)->nullable();
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('oss_applications', function (Blueprint $table) {
            $table->dropColumn([
                // Residential address
                'res_addr_plot', 'res_addr_street', 'res_addr_street_other',
                'res_addr_district', 'res_addr_district_other', 'res_addr_lga', 'res_addr_state',
                // Residential correspondence
                'res_corr_plot', 'res_corr_street', 'res_corr_street_other',
                'res_corr_district', 'res_corr_district_other', 'res_corr_lga', 'res_corr_state',
                // Residential business
                'res_biz_plot', 'res_biz_street', 'res_biz_street_other',
                'res_biz_district', 'res_biz_district_other', 'res_biz_lga', 'res_biz_state',
                // Commercial correspondence
                'com_corr_plot', 'com_corr_street', 'com_corr_street_other',
                'com_corr_district', 'com_corr_district_other', 'com_corr_lga', 'com_corr_state',
                // Industrial correspondence
                'ind_corr_plot', 'ind_corr_street', 'ind_corr_street_other',
                'ind_corr_district', 'ind_corr_district_other', 'ind_corr_lga', 'ind_corr_state',
            ]);
        });
    }
};
