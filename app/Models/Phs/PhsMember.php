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
        'phone',
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

    /** Returns the access_role as an array (supports comma-separated storage). */
    public function accessRoles(): array
    {
        return array_filter(array_map('trim', explode(',', $this->access_role ?? '')));
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->accessRoles(), true);
    }

    /** Regular users with search_only or super admins can run searches. */
    public function canSearch(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        $roles = $this->accessRoles();
        return !empty(array_intersect($roles, ['search_only', 'report_viewer', 'analytics_viewer']));
    }

    public function canViewReports(): bool
    {
        return $this->isSuperAdmin() || !empty(array_intersect($this->accessRoles(), ['report_viewer', 'analytics_viewer']));
    }
}
