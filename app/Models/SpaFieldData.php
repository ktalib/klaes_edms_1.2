<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaFieldData extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_field_data';

    protected $fillable = [
        'spa_application_id', 'file_number', 'surveyor_id',
        'inspection_date', 'coordinates', 'parcel_geometry', 'findings', 'photos',
        'status', 'created_by',
    ];

    protected $casts = [
        'coordinates'     => 'array',
        'parcel_geometry' => 'array',
        'photos'          => 'array',
        'inspection_date' => 'date',
    ];

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }

    public function surveyor()
    {
        return $this->belongsTo(\App\Models\User::class, 'surveyor_id');
    }
}
