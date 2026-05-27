<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MlsFileNo extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'mls_file_no';

    protected $fillable = [
        'land_use',
        'year',
        'serial_number',
        'full_file_number',
        'file_name',
        'plot_no',
        'tp_no',
        'location',
        'lga',
        'district',
        'tracking_id',
        'customer_type',
        'file_option',
        'batch_no',
        'created_by',
        'commissioning_date',
        'commissioning_time',
        'is_deleted',
        'purpose_id',
        'source',
        'sub_source',
        'source_instrument_capture_id',
        'source_pra_id'
    ];

    public function purpose()
    {
        return $this->belongsTo(Purpose::class, 'purpose_id');
    }

    protected $casts = [
        'is_deleted' => 'boolean',
        'year' => 'integer',
        'serial_number' => 'integer'
    ];

    /**
     * Generate formatted file number
     */
    public static function generateFileNumber($landUse, $year, $serial)
    {
        return "{$landUse}-{$year}-{$serial}";
    }

    /**
     * Scope to filter by land use
     */
    public function scopeByLandUse($query, $landUse)
    {
        return $query->where('land_use', $landUse);
    }

    /**
     * Scope to filter by year
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to get only active (non-deleted) records
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
        });
    }

    /**
     * Check if file number exists
     */
    public static function fileNumberExists($fileNumber)
    {
        return self::where('full_file_number', $fileNumber)->exists();
    }
}
