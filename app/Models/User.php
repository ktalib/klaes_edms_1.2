<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Attendance\UserAbsenceCounter;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\Payroll\RateService;


class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use Notifiable;
    use Impersonate;
    use HasFactory;

    // Specify SQL Server connection
    protected $connection = 'sqlsrv';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'type',
        'phone_number',
        'profile',
        'passport_photo_path',
        'is_pc_access',
        'lang',
        'subscription',
        'subscription_expire_date',
        'parent_id',
        'is_active',
        'assign_role', // stores user_role ids as comma-separated
        'department_id', // correct field for department
        'work_station',
        'user_level',    // new field for user level
        'user_type',     // new field for user type
        'username', // new field for username
        'work_days_per_week',
        'man_hours_per_day',
        'shift_code',
        'auto_deactivate',
        'staff_type_category',
        'workstation_payment_structure_id',
        'base_salary_override',
        'base_salary_source',
        'rank',
        'signature',
        'user_actions',
        'dfr_permissions',
        'fr_permissions',
    ];

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmail);
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected $casts = [
        'email_verified_at' => 'datetime',
        'base_salary_override' => 'float',
        'workstation_payment_structure_id' => 'integer',
        'is_pc_access' => 'boolean',
        'auto_deactivate' => 'boolean',
    ];

    protected $appends = ['name'];
    protected array $permissionCache = [];

    protected static function booted(): void
    {
        static::created(function (User $user) {
            static::ensurePayrollRate($user);
        });

        static::updated(function (User $user) {
            if (
                ($user->wasChanged('work_station') && $user->work_station)
                || $user->wasChanged('workstation_payment_structure_id')
            ) {
                static::ensurePayrollRate($user);
            }

            if ($user->wasChanged('is_active') && (int) $user->is_active === 1) {
                UserAbsenceCounter::query()
                    ->where('user_id', $user->id)
                    ->update([
                        'consecutive_absences' => 0,
                        'monthly_absences' => 0,
                        'last_absent_date' => null,
                        'month_key' => Carbon::now()->format('Y-m'),
                    ]);
            }
        });
    }

    protected static function ensurePayrollRate(User $user): void
    {
        if (!$user->work_station) {
            return;
        }

        app(RateService::class)->syncBaselineRate($user, auth()->id());
    }

    public function paymentStructure()
    {
        return $this->belongsTo(\App\Models\Payroll\WorkstationPaymentStructure::class, 'workstation_payment_structure_id');
    }

    public function canImpersonate()
    {
        // Example: Only admins can impersonate others
        return $this->type == 'super admin';
    }

    /**
     * Whether this user is a Super Admin — by account type or assigned role
     * (handles the "supper admin" spelling used across the app).
     */
    public function isSuperAdmin(): bool
    {
        $type      = strtolower((string) $this->type);
        $roleNames = array_map('strtolower', $this->assignedRoleNames());

        return in_array($type, ['super admin', 'supper admin'], true)
            || in_array('super admin', $roleNames, true)
            || in_array('supper admin', $roleNames, true);
    }

    public function totalUser()
    {
        return User::where('parent_id', $this->id)->count();
    }

    public function getNameAttribute()
    {
        return ucfirst($this->first_name) . ' ' . ucfirst($this->last_name);
    }


    public function totalContact()
    {
        return Contact::where('parent_id', '=', parentId())->count();
    }

    public function roleWiseUserCount($role)
    {
        return User::where('type', $role)->where('parent_id', parentId())->count();
    }
    
    public static function getDevice($user)
    {
        $mobileType = '/(?:phone|windows\s+phone|ipod|blackberry|(?:android|bb\d+|meego|silk|googlebot) .+? mobile|palm|windows\s+ce|opera mini|avantgo|mobilesafari|docomo)/i';
        $tabletType = '/(?:ipad|playbook|(?:android|bb\d+|meego|silk)(?! .+? mobile))/i';
        if (preg_match_all($mobileType, $user)) {
            return 'mobile';
        } else {
            if (preg_match_all($tabletType, $user)) {
                return 'tablet';
            } else {
                return 'desktop';
            }
        }
    }

    public function totalDocument()
    {
        return Document::where('parent_id', '=', parentId())->count();
    }

    // Modified to handle null subscription
    public function subscriptions()
    {
        return $this->hasOne('App\Models\Subscription', 'id', 'subscription');
    }

    // Modified to handle null subscription
    public function SubscriptionLeftDay()
    {
        // No longer needed for this application
        return '<span class="text-success">' . __('Active') . '</span>';
    }

    /**
     * Get the department that the user belongs to
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user roles assigned to this user
     */
    public function userRoles()
    {
        if(empty($this->assign_role)) {
            return collect([]);
        }
        
        $roleIds = explode(',', $this->assign_role);
        return UserRole::whereIn('id', $roleIds)->get();
    }

    /**
     * Resolve normalized role names assigned to the user (handles IDs, JSON, CSV, etc.)
     */
    public function assignedRoleNames(): array
    {
        if (isset($this->permissionCache['assigned_role_names'])) {
            return $this->permissionCache['assigned_role_names'];
        }

        $raw = trim((string) $this->assign_role);
        if ($raw === '') {
            return $this->permissionCache['assigned_role_names'] = [];
        }

        $names = [];

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $names = array_merge($names, $this->normalizeRoleNames($decoded));
        }

        $sanitized = str_replace([';', '|'], ',', $raw);
        $tokens = collect(explode(',', $sanitized))
            ->map(fn ($token) => trim($token))
            ->filter();

        $stringTokens = $tokens->filter(fn ($token) => !is_numeric($token));
        $numericTokens = $tokens->filter(fn ($token) => is_numeric($token))->map(fn ($id) => (int) $id);

        if ($stringTokens->isNotEmpty()) {
            $names = array_merge($names, $this->normalizeRoleNames($stringTokens->all()));
        }

        if ($numericTokens->isNotEmpty()) {
            $roleNames = UserRole::whereIn('id', $numericTokens->all())->pluck('name')->all();
            $names = array_merge($names, $this->normalizeRoleNames($roleNames));
        }

        return $this->permissionCache['assigned_role_names'] = array_values(array_unique($names));
    }

    protected function normalizeRoleNames(array $values): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => mb_strtolower(trim((string) $value)),
            $values
        )));
    }

    /**
     * Get session locks for this user
     */
    public function sessionLocks()
    {
        return $this->hasMany(UserSessionLock::class);
    }

    // =====================================================================
    // Phase 2: Activity Log Relationships
    // =====================================================================

    /**
     * Get all activity logs for this user
     */
    public function activityLogs()
    {
        return $this->hasMany(UserActivityLog::class, 'user_id', 'id');
    }

    /**
     * Get activity log settings for this user
     */
    public function activityLogSettings()
    {
        return $this->hasOne(UserActivityLogSetting::class, 'user_id', 'id');
    }

    /**
     * Get current session for this user
     */
    public function getCurrentSession()
    {
        return UserActivityLog::getCurrentSessionForUser($this->id);
    }

    /**
     * Get online users count
     */
    public static function getOnlineUsers()
    {
        return UserActivityLog::getOnlineUsers();
    }

    /**
     * Check if user is currently online
     */
    public function isOnline(): bool
    {
        $session = $this->getCurrentSession();
        return $session && $session->isOnline();
    }

    /**
     * Check if user is idle
     */
    public function isIdle(): bool
    {
        $session = $this->getCurrentSession();
        return $session && $session->isIdle();
    }

    /**
     * Get user's session statistics
     */
    public function getSessionStats($days = 30)
    {
        return UserActivityLog::getDurationStatsForUser($this->id, 
            Carbon::now()->subDays($days), 
            Carbon::now()
        );
    }

    /**
     * Check if user has permission.
     *
     * @param  string|array  $abilities
     * @param  array|mixed  $arguments
     */
    public function can($abilities, $arguments = [])
    {
        $roleNames = $this->assignedRoleNames();
        $type = strtolower((string)$this->type);
        if ($type === 'super admin' || $type === 'supper admin' || in_array('super admin', $roleNames) || in_array('supper admin', $roleNames)) {
            return true;
        }

        if (is_array($abilities)) {
            foreach ($abilities as $ability) {
                if ($this->can($ability, $arguments)) {
                    return true;
                }
            }

            return false;
        }

        if ($this->hasDatabasePermission((string) $abilities)) {
            return true;
        }

        $adminPermissions = [
            'manage user',
            'create user',
            'edit user',
            'delete user',
            'show user',
            'manage logged history',
            'delete logged history',
        ];

        if (in_array($abilities, $adminPermissions, true) && in_array($this->type, ['owner', 'admin'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Check a Digital File Request sub-permission.
     * Valid keys: view_requests | make_request | approve_request
     */
    public function hasDfrPermission(string $key): bool
    {
        $type = strtolower((string) $this->type);
        if ($type === 'super admin' || $type === 'supper admin') {
            return true;
        }
        if (empty($this->dfr_permissions)) {
            return false;
        }
        $perms = array_map('trim', explode(',', $this->dfr_permissions));
        return in_array($key, $perms, true);
    }

    /**
     * SCB Monitor (mobile file searcher) — receives File Search Requests.
     */
    public function isScbMonitor(): bool
    {
        return ($this->fr_permissions ?? '') === 'SCB';
    }

    /**
     * Office Priority Search (OFS) requester — a ranked officer whose users.rank
     * matches the hierarchy in config/file_request_priority.php. OFS requests are
     * prioritised, colour-coded on the SCB Feedback table, and floated to the top
     * of the SCB Monitor's mobile list.
     */
    public function isOfs(): bool
    {
        return \App\Models\FileSearchRequest::priorityFor($this->rank) > 0;
    }

    /**
     * The user's rank when it qualifies them as an OFS requester, else null.
     */
    public function ofsRank(): ?string
    {
        return $this->isOfs() ? ($this->rank ?: null) : null;
    }

    /**
     * Lightweight permission lookup compatible with Spatie tables.
     */
    protected function hasDatabasePermission(string $ability): bool
    {
        if ($ability === '') {
            return false;
        }

        if (array_key_exists($ability, $this->permissionCache)) {
            return $this->permissionCache[$ability];
        }

        $tables = config('permission.table_names');
        if (!$tables) {
            return $this->permissionCache[$ability] = false;
        }

        $permissionsTable = $tables['permissions'] ?? 'permissions';
        $modelHasPermissionsTable = $tables['model_has_permissions'] ?? 'model_has_permissions';
        $modelHasRolesTable = $tables['model_has_roles'] ?? 'model_has_roles';
        $roleHasPermissionsTable = $tables['role_has_permissions'] ?? 'role_has_permissions';

        $permissionId = DB::table($permissionsTable)
            ->where('name', $ability)
            ->value('id');

        if (!$permissionId) {
            return $this->permissionCache[$ability] = false;
        }

        $modelType = static::class;

        $hasDirectPermission = DB::table($modelHasPermissionsTable)
            ->where('model_type', $modelType)
            ->where('model_id', $this->id)
            ->where('permission_id', $permissionId)
            ->exists();

        if ($hasDirectPermission) {
            return $this->permissionCache[$ability] = true;
        }

        $roleIds = DB::table($modelHasRolesTable)
            ->where('model_type', $modelType)
            ->where('model_id', $this->id)
            ->pluck('role_id');

        if ($roleIds->isNotEmpty()) {
            $hasRolePermission = DB::table($roleHasPermissionsTable)
                ->where('permission_id', $permissionId)
                ->whereIn('role_id', $roleIds)
                ->exists();

            if ($hasRolePermission) {
                return $this->permissionCache[$ability] = true;
            }
        }

        return $this->permissionCache[$ability] = false;
    }

    public static $systemModules = [
        'user',
        'document',
        'reminder',
        'comment',
        'version',
        'mail',
        'category',
        'tag',
        'contact',
        'note',
        'logged history',
        'pricing transation',
        'account settings',
        'password settings',
        'general settings',
        'company settings',
    ];
}
