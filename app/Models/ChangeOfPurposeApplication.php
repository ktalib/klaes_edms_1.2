<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChangeOfPurposeApplication extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'change_of_purpose_applications';

    protected $primaryKey = 'id';

    public $timestamps = true;

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_COMMISSIONED = 'commissioned';

    protected $fillable = [
        'file_no',
        'land_use',
        'purpose',
        'plot_no',
        'plan_no',
        'location',
        'applicant_name',
        'phone',
        'residential_address',
        'status',
        'remarks',
        'captured_by',
        'updated_by',
        'knupda_status',
        'knupda_fee',
        'land_value',
        'knupda_remarks',
        'application_generated_at',
        'recommendation_generated_at',
        'site_plan',
        'is_deleted',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
