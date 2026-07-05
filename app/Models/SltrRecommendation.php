<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SltrRecommendation extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'sqlsrv';
    protected $table = 'sltr_recommendations';

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';

    const ROFO_PENDING   = 'pending';
    const ROFO_GENERATED = 'generated';

    const LAND_USE_OPTIONS = [
        'Residential',
        'Commercial',
        'Industrial',
        'Agricultural',
        'Institutional',
        'Mixed Use',
        'Public Use',
        'Recreational',
    ];

    const PURPOSE_OPTIONS = [
        'RESIDENTIAL (VERY HIGH DENSITY)',
        'RESIDENTIAL (HIGH DENSITY)',
        'RESIDENTIAL (MEDIUM DENSITY)',
        'RESIDENTIAL (LOW DENSITY)',
        'COMMERCIAL',
        'COMMERCIAL (GENERAL)',
        'COMMERCIAL (RETAIL)',
        'INDUSTRIAL (LIGHT)',
        'INDUSTRIAL (HEAVY)',
        'AGRICULTURAL',
        'INSTITUTIONAL',
        'MIXED USE',
        'PUBLIC USE',
        'RECREATIONAL',
    ];

    protected $fillable = [
        'sltr_number',
        'applicant_name',
        'applicant_address',
        'application_date',
        'location',
        'lga',
        'land_use',
        'plot_number',
        'page_application',
        'page_survey',
        'page_planning',
        'term',
        'ground_rent',
        'processing_fee',
        'purpose_of_clause',
        'notes',
        'status',
        'approved_at',
        'approved_by',
        'rofo_status',
        'rofo_generated_at',
        'rofo_print_count',
        'rofo_director_survey',
        'rofo_licensed_surveyor',
        'rofo_date_generated',
        'created_by',
        'updated_by',
        'sltr_rofo_serial_no',
        'printed_at',
    ];

    protected $casts = [
        'application_date'   => 'date',
        'approved_at'        => 'datetime',
        'rofo_generated_at'  => 'datetime',
        'rofo_date_generated'=> 'date',
        'printed_at'         => 'datetime',
        'ground_rent'        => 'decimal:2',
        'processing_fee'     => 'decimal:2',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
