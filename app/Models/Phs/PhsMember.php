<?php

namespace App\Models\Phs;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PhsMember extends Authenticatable
{
    use Notifiable;

    protected $connection = 'sqlsrv';
    protected $table = 'phs_members';

    protected $fillable = [
        'phs_institution_id',
        'name',
        'email',
        'password',
        'job_title',
        'department',
        'user_type',
        'access_role',
        'tokens_used',
        'allocated_tokens',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'allocated_tokens' => 'integer',
        'tokens_used' => 'integer',
    ];

    public function institution()
    {
        return $this->belongsTo(PhsInstitution::class, 'phs_institution_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->user_type === 'super_admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Regular users with search_only or super admins can run searches. */
    public function canSearch(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        return in_array($this->access_role, ['search_only', 'report_viewer', 'analytics_viewer'], true);
    }

    public function canViewReports(): bool
    {
        return $this->isSuperAdmin() || in_array($this->access_role, ['report_viewer', 'analytics_viewer'], true);
    }
}
