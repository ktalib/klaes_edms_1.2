<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration adds fields required for Conversion Applications - Planning Recommendation
     * and a source field to identify which module is using the joint_site_inspection_reports table.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            // Source field to track which module created the record
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'source')) {
                $table->string('source', 100)->nullable()->after('unit_number')
                    ->comment('Module source: ST One Stop Shop, Conversion Applications, etc.');
            }

            // Purpose dropdown (Conversion, Continuation, Extension, Subdivision, Merger, Private Layout)
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'purpose')) {
                $table->string('purpose', 50)->nullable()->after('source')
                    ->comment('Purpose: Conversion, Continuation, Extension, Subdivision, Merger, Private Layout');
            }

            // Private Layout Number (conditional - shown only if Purpose = 'Private Layout')
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'private_layout_number')) {
                $table->string('private_layout_number', 100)->nullable()->after('purpose')
                    ->comment('Required when Purpose = Private Layout');
            }

            // Conformity (Yes/No) - Does proposed use conform to development plan?
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'conformity')) {
                $table->boolean('conformity')->nullable()->after('private_layout_number')
                    ->comment('Does the proposed use conform to the development plan?');
            }

            // Accessibility (Yes/No) - Does site have adequate access?
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'accessibility')) {
                $table->boolean('accessibility')->nullable()->after('conformity')
                    ->comment('Does the site have adequate access?');
            }

            // Existing Road Reservation
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'existing_road_reservation')) {
                $table->string('existing_road_reservation', 255)->nullable()->after('accessibility')
                    ->comment('Existing road setback/reservation');
            }

            // Recommended Road Reservation
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'recommended_road_reservation')) {
                $table->string('recommended_road_reservation', 255)->nullable()->after('existing_road_reservation')
                    ->comment('Recommended road setback/reservation for conversion');
            }

            // Adequate Size Requirement (Yes/No) - Does plot size meet requirements?
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'adequate_size_requirement')) {
                $table->boolean('adequate_size_requirement')->nullable()->after('recommended_road_reservation')
                    ->comment('Does plot size meet requirements for proposed use?');
            }

            // Land Traversing Utility (Yes/No) - Do utilities traverse the land?
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'land_traversing_utility')) {
                $table->boolean('land_traversing_utility')->nullable()->after('adequate_size_requirement')
                    ->comment('Do any utilities traverse the land?');
            }

            // Existing Site Measurement - Length
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'existing_site_length')) {
                $table->decimal('existing_site_length', 10, 2)->nullable()->after('land_traversing_utility')
                    ->comment('Existing site length in meters');
            }

            // Existing Site Measurement - Width
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'existing_site_width')) {
                $table->decimal('existing_site_width', 10, 2)->nullable()->after('existing_site_length')
                    ->comment('Existing site width in meters');
            }

            // Existing Site Measurement - Area (auto-calculated: length × width)
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'existing_site_area')) {
                $table->decimal('existing_site_area', 10, 2)->nullable()->after('existing_site_width')
                    ->comment('Existing site area in m² (auto-calculated from length × width)');
            }

            // Recommended Site Measurement - Length
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'recommended_site_length')) {
                $table->decimal('recommended_site_length', 10, 2)->nullable()->after('existing_site_area')
                    ->comment('Recommended site length in meters');
            }

            // Recommended Site Measurement - Width
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'recommended_site_width')) {
                $table->decimal('recommended_site_width', 10, 2)->nullable()->after('recommended_site_length')
                    ->comment('Recommended site width in meters');
            }

            // Recommended Site Measurement - Area (auto-calculated: length × width)
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'recommended_site_area')) {
                $table->decimal('recommended_site_area', 10, 2)->nullable()->after('recommended_site_width')
                    ->comment('Recommended site area in m² (auto-calculated from length × width)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            $columns = [
                'source',
                'purpose',
                'private_layout_number',
                'conformity',
                'accessibility',
                'existing_road_reservation',
                'recommended_road_reservation',
                'adequate_size_requirement',
                'land_traversing_utility',
                'existing_site_length',
                'existing_site_width',
                'existing_site_area',
                'recommended_site_length',
                'recommended_site_width',
                'recommended_site_area',
            ];

            foreach ($columns as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
