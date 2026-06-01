<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaApplication extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_applications';

    protected $fillable = [
        'file_number', 'tracking_id', 'file_indexing_id', 'is_indexed',
        'owner_name', 'phone', 'location', 'district', 'lga',
        'land_use_type', 'existing_use', 'proposed_use',
        'scenario', 'status', 'created_by', 'photos',
    ];

    protected $casts = [
        'is_indexed' => 'boolean',
        'photos'     => 'array',
    ];

    public function fieldData()
    {
        return $this->hasMany(SpaFieldData::class, 'spa_application_id');
    }

    public function notices()
    {
        return $this->hasMany(SpaNotice::class, 'spa_application_id');
    }

    public function firstNotice()
    {
        return $this->hasOne(SpaNotice::class, 'spa_application_id')->where('notice_type', 'first')->latest();
    }

    public function secondNotice()
    {
        return $this->hasOne(SpaNotice::class, 'spa_application_id')->where('notice_type', 'second')->latest();
    }

    public function bills()
    {
        return $this->hasMany(SpaBill::class, 'spa_application_id');
    }

    public function payments()
    {
        return $this->hasMany(SpaPayment::class, 'spa_application_id');
    }

    public function departmentReferrals()
    {
        return $this->hasMany(SpaDepartmentReferral::class, 'spa_application_id');
    }

    public function memos()
    {
        return $this->hasMany(SpaMemo::class, 'spa_application_id');
    }

    public function certificate()
    {
        return $this->hasOne(SpaCertificate::class, 'spa_application_id');
    }
}
