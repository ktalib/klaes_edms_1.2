<?php

namespace App\Models\Laas;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A public LAAS Portal account. Authenticated by the `laas` guard, which is
 * entirely separate from staff `auth` — see config/auth.php.
 */
class LaasApplicant extends Authenticatable
{
    use Notifiable;

    protected $connection = 'sqlsrv';
    protected $table = 'laas_applicants';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'nin',
        'address',
        'status',
        'phone_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
    ];

    public function applications()
    {
        return $this->hasMany(LaasApplication::class, 'laas_applicant_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
