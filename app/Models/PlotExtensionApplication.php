<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlotExtensionApplication extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'plot_extension_applications';

    public const STATUS_PENDING    = 'pending';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_REJECTED   = 'rejected';

    protected $fillable = [
        'file_no',
        'file_title',
        'applicant_name',
        'plot_no',
        'house_no',
        'street_name',
        'district',
        'lga',
        'state',
        'residential_address',
        'correspondence_address',
        'nationality',
        'occupation',
        'land_use',
        'location',
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
    ];

    public function plotSizes()
    {
        return $this->hasMany(PlotApplicationSize::class, 'application_id')->where('application_type', 'extension');
    }
}
