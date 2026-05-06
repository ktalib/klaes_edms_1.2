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
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'recommended_comment_status')) {
                $table->string('recommended_comment_status', 100)
                    ->nullable()
                    ->after('recommended_road_reservation')
                    ->comment('Adequate or Not Adequate for recommended reservation comment');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'how_many_meters')) {
                $table->string('how_many_meters', 100)
                    ->nullable()
                    ->after('recommended_comment_status')
                    ->comment('Specified meters when recommended comment is Not Adequate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'how_many_meters')) {
                $table->dropColumn('how_many_meters');
            }

            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'recommended_comment_status')) {
                $table->dropColumn('recommended_comment_status');
            }
        });
    }
};
