<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandRecommendation extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'land_recommendations';
    public $timestamps = true;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';

    const ROFO_PENDING = 'pending';
    const ROFO_GENERATED = 'generated';

    protected $fillable = [
        'file_number',
        'applicant_name',
        'purpose_of_clause',
        'location',
        'lga',
        'area_sqm',
        'term',
        'ground_rent',
        'effective_date',
        'premium',
        'development_period',
        'survey_fees',
        'land_use',
        'land_use_id',
        'purpose_id',
        'meeting_date',
        'recommendation',
        'plot_number',
        'layout_plan_no',
        'development_value',
        'development_charge',
        'tracking_id',
        'status',
        'approved_at',
        'rofo_status',
        'rofo_generated_at',
        'rofo_print_count',
        'rofo_survey_fees',
        'rofo_dev_charge',
        'rofo_director_survey',
        'rofo_licensed_surveyor',
        'rofo_land_use_category',
        'print_count',
        'application_date',
        'applicant_address',
        'created_by',
        'updated_by',
        'edit_reason',
        'land_rofo_serial_no',
        'type',
        'page',
        'page_survey_report',
        'survey_report',
        'improvement',
        'revision_period',
        'time_of_erection',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'meeting_date' => 'date',
        'premium' => 'decimal:2',
        'ground_rent' => 'decimal:2',
        'survey_fees' => 'decimal:2',
        'development_value' => 'decimal:2',
        'development_charge' => 'decimal:2',
        'approved_at' => 'datetime',
        'rofo_generated_at' => 'datetime',
        'application_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
