<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JointSiteInspectionReport extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'joint_site_inspection_reports';

    protected $fillable = [
        'application_id',
        'sub_application_id',
        'file_number',
        'inspection_date',
        'lkn_number',
        'applicant_name',
        'applicant_address',
        'location',
        'location_components',
        'applicant_address_components',
        'plot_number',
        'scheme_number',
        'available_on_ground',
        'boundary_description',
        'sections_count',
        'unit_dimension',
        'unit_number',
        'inspection_officer_id',
        'source',
        'purpose',
        'private_layout_number',
        'conformity',
        'accessibility',
        'existing_road_reservation',
        'recommended_road_reservation',
        'observed_road_category',
        'recommended_comment_status',
        'how_many_meters',
        'adequate_size_requirement',
        'land_traversing_utility',
        'existing_site_length',
        'existing_site_width',
        'existing_site_area',
        'recommended_site_length',
        'recommended_site_width',
        'recommended_site_area',
        'road_reservation',
        'prevailing_land_use',
        'applied_land_use',
        'shared_utilities',
        'compliance_status',
        'has_additional_observations',
        'additional_observations',
        'inspection_officer',
        'existing_site_measurement_summary',
        'existing_site_measurement_entries',
        'is_generated',
        'is_submitted',
        'generated_at',
        'submitted_at',
        'generated_by',
        'submitted_by',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'unit_number',
        'boundary_segments',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'available_on_ground' => 'boolean',
        'inspection_officer_id' => 'integer',
        'conformity' => 'boolean',
        'accessibility' => 'boolean',
        'adequate_size_requirement' => 'boolean',
        'land_traversing_utility' => 'boolean',
        'existing_site_length' => 'string',
        'existing_site_width' => 'string',
        'existing_site_area' => 'decimal:2',
        'recommended_site_length' => 'string',
        'recommended_site_width' => 'string',
        'recommended_site_area' => 'decimal:2',
        'has_additional_observations' => 'boolean',
        'is_generated' => 'boolean',
        'is_submitted' => 'boolean',
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'shared_utilities' => 'array',
        'existing_site_measurement_entries' => 'array',
        'location_components' => 'array',
        'applicant_address_components' => 'array',
    ];

    protected function unitNumber(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                $rawValue = $value;

                if ($rawValue === null || $rawValue === '') {
                    return null;
                }

                $stringValue = trim((string) $rawValue);
                return $stringValue === '' ? null : $stringValue;
            },
            set: function ($value) {
                if ($value === null || $value === '') {
                    return ['unit_number' => null];
                }

                $stringValue = trim((string) $value);

                return ['unit_number' => $stringValue === '' ? null : $stringValue];
            }
        );
    }

    protected function boundarySegments(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                return self::prepareBoundarySegments(
                    $value,
                    $attributes['boundary_description'] ?? null,
                    true
                );
            },
            set: function ($value, array $attributes) {
                $prepared = self::prepareBoundarySegments(
                    $value,
                    $attributes['boundary_description'] ?? null
                );

                $description = self::compileBoundaryDescription(
                    $prepared,
                    $attributes['boundary_description'] ?? null
                );

                return $description === null
                    ? ['boundary_description' => null]
                    : ['boundary_description' => $description];
            }
        );
    }

    public static function normalizeBoundarySegments($value): array
    {
        if (is_null($value) || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                return [];
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $directions = ['north', 'east', 'south', 'west'];
        $normalized = [];

        foreach ($value as $key => $segment) {
            $direction = strtolower(trim((string) $key));

            if (!in_array($direction, $directions, true)) {
                continue;
            }

            if (is_array($segment)) {
                $segment = $segment['description'] ?? ($segment['value'] ?? null);
            }

            $text = trim((string) $segment);

            if ($text === '') {
                continue;
            }

            $normalized[$direction] = $text;
        }

        return $normalized;
    }

    public static function parseBoundaryDescriptionSegments(?string $description): array
    {
        if (!$description || !is_string($description)) {
            return [];
        }

        $normalizedDescription = trim(preg_replace('/\s+/', ' ', $description));

        if ($normalizedDescription === '') {
            return [];
        }

        $pattern = '/On the\s+(north|east|south|west)\s*:?[\s-]*(.*?)(?=(?:On the\s+(?:north|east|south|west)\s*:)|$)/is';
        preg_match_all($pattern, $normalizedDescription, $matches, PREG_SET_ORDER);

        $segments = [];

        foreach ($matches as $match) {
            $direction = strtolower($match[1]);
            $text = trim($match[2]);
            $text = preg_replace('/[\s.;,]+$/', '', $text);

            if ($text === '') {
                continue;
            }

            $segments[$direction] = $text;
        }

        if (!empty($segments)) {
            return $segments;
        }

        $fallbackSegments = [];
        $parts = preg_split('/\s*;\s*/', $normalizedDescription);

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/On the\s+(north|east|south|west)\s*:?[\s-]*(.*)/i', $part, $partMatch)) {
                $direction = strtolower($partMatch[1]);
                $text = trim($partMatch[2]);
                $text = preg_replace('/[\s.;,]+$/', '', $text);

                if ($text === '') {
                    continue;
                }

                $fallbackSegments[$direction] = $text;
            }
        }

        return $fallbackSegments;
    }

    public static function prepareBoundarySegments($value, ?string $fallbackDescription = null, bool $includeEmptyKeys = false): array
    {
        $normalized = self::normalizeBoundarySegments($value);

        if (empty($normalized) && $fallbackDescription) {
            $normalized = self::parseBoundaryDescriptionSegments($fallbackDescription);
        }

        return $includeEmptyKeys
            ? self::fillBoundaryDirections($normalized)
            : $normalized;
    }

    public static function compileBoundaryDescription($segments, ?string $fallbackDescription = null): ?string
    {
        $normalized = self::normalizeBoundarySegments($segments);

        if (empty($normalized)) {
            if ($fallbackDescription === null) {
                return null;
            }

            $trimmedFallback = trim(preg_replace('/\s+/', ' ', $fallbackDescription));

            return $trimmedFallback === '' ? null : $trimmedFallback;
        }

        $phrases = [];
        $directions = ['north', 'east', 'south', 'west'];

        foreach ($directions as $direction) {
            if (empty($normalized[$direction])) {
                continue;
            }

            $phrases[] = 'On the ' . ucfirst($direction) . ': ' . $normalized[$direction];
        }

        if (empty($phrases)) {
            return $fallbackDescription ? trim($fallbackDescription) : null;
        }

        $description = implode('; ', $phrases);

        if (!str_ends_with($description, '.')) {
            $description .= '.';
        }

        return $description;
    }

    protected static function fillBoundaryDirections(array $segments): array
    {
        $directions = ['north', 'east', 'south', 'west'];
        $filled = [];

        foreach ($directions as $direction) {
            $filled[$direction] = isset($segments[$direction])
                ? trim((string) $segments[$direction])
                : '';
        }

        return $filled;
    }
}
