<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandOfficer extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'signing_officers';

    protected $fillable = [
        'name',
        'rank',
        'signature_file',
        'user_id',
        'verification_code',
        'verification_code_expires_at',
        'notification_type',
    ];

    protected $casts = [
        'verification_code_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
