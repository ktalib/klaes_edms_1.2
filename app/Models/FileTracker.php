<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FileTracker extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'file_tracker';

    protected $fillable = [
        'tracking_id',
        'file_number',
        'file_title',
        'file_type',
        'priority',
        'created_by',
        'created_by_name',
        'department',
        'destination',
        'description',
        'status',
        'date_created',
        'deadline',
        'movement_log',
        'current_office_code',
        'current_office_name',
        'total_offices',
        'completed_offices',
        'notes',
        'origin_office_code',
        'origin_office_name',
        'origin_office_department',
        'receiving_office_code',
        'receiving_office_name',
        'receiving_officer_id',
        'receiving_officer_name',
        'assignment_status',
        'assignment_accepted_at',
        'module',
        'workflow_type',
        'workflow_step',
        'workflow_config',
    ];

    protected $casts = [
        'date_created' => 'datetime',
        'deadline' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'movement_log' => 'array',
        'assignment_accepted_at' => 'datetime',
        'workflow_config' => 'array',
    ];

    // Priority constants
    const PRIORITY_LOW = 'LOW';
    const PRIORITY_MEDIUM = 'MEDIUM';
    const PRIORITY_HIGH = 'HIGH';

    // Status constants
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CANCELLED = 'CANCELLED';

    // Assignment status constants
    const ASSIGNMENT_PENDING = 'PENDING';
    const ASSIGNMENT_ACCEPTED = 'ACCEPTED';
    const ASSIGNMENT_REJECTED = 'REJECTED';

    // Workflow type constants
    const WORKFLOW_KANGIS_APPROVAL = 'kangis_approval_flow';
    const WORKFLOW_KANGIS_NEW = 'kangis_approval_flow'; // Alias for generateKangisTrackingSheet
    const WORKFLOW_KANGIS_3STEP = 'kangis_3step'; // Backward compatibility alias
    const WORKFLOW_CROSS_MODULE_REQUEST = 'cross_module_request'; // Land ↔ KANGIS bilateral request

    // Workflow step purposes
    const PURPOSE_SUBMISSION = 'submission';
    const PURPOSE_RECOMMENDATION = 'recommendation';
    const PURPOSE_APPROVAL = 'approval';
    const PURPOSE_PROCESSING = 'processing';
    const PURPOSE_DISPATCH = 'dispatch'; // Backward compatibility alias

    // Workflow office codes
    const OFFICE_KANGIS_REGISTRY = 'KRE';
    const OFFICE_DIRECTOR_GIS = 'DGIS';
    const OFFICE_DG_KANGIS = 'DG';
    const OFFICE_DEPARTMENT_QUEUE = 'DEPARTMENT_QUEUE';

    /**
     * KANGIS approval workflow definition.
     * Each step defines source -> destination office and purpose.
     */ 
              

    const KANGIS_4STEP_DEFINITION = [
        1 => [
            'from_code' => self::OFFICE_KANGIS_REGISTRY,
            'from_name' => 'KANGIS Registry',
            'to_code' => self::OFFICE_DIRECTOR_GIS,
            'to_name' => 'Director GIS',
            'purpose' => self::PURPOSE_SUBMISSION,
            'label' => 'Submission',
        ],
        2 => [
            'from_code' => self::OFFICE_DIRECTOR_GIS,
            'from_name' => 'Director GIS',
            'to_code' => self::OFFICE_DG_KANGIS,
            'to_name' => 'Director General, KANGIS',
            'purpose' => self::PURPOSE_RECOMMENDATION,
            'label' => 'Recommendation',
        ],
        3 => [
            'from_code' => self::OFFICE_DG_KANGIS,
            'from_name' => 'Director General, KANGIS',
            'to_code' => self::OFFICE_DEPARTMENT_QUEUE,
            'to_name' => 'Department Queue',
            'purpose' => self::PURPOSE_APPROVAL,
            'label' => 'Approval',
        ],
        4 => [
            'from_code' => self::OFFICE_DEPARTMENT_QUEUE,
            'from_name' => 'Department Queue',
            'to_code' => null,
            'to_name' => null,
            'purpose' => self::PURPOSE_PROCESSING,
            'label' => 'Processing',
        ],
    ];

    // Backward compatibility alias used by existing controllers/views.
    const KANGIS_3STEP_DEFINITION = self::KANGIS_4STEP_DEFINITION;

    protected $appends = [
        'can_edit',
    ];

    /**
     * Generate a unique tracking ID
     */
    public static function generateTrackingId()
    {
        $timestamp = now()->format('ymdHis');
        $random = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4));
        return "TRK-{$timestamp}-{$random}";
    }

    /**
     * Add a movement log entry
     */
    public function addMovementLog($officeCode, $officeName, $logInTime, $logInDate, $notes = null, $userId = null, $userName = null, array $options = [])
    {
        $currentLog = $this->movement_log ?: [];

        // Generate log ID
        $logId = 'LOG-' . now()->format('YmdHis') . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);

        $receivingOfficerId = $options['receiving_officer_id'] ?? null;
        $receivingOfficerName = $options['receiving_officer_name'] ?? null;
        $status = strtolower((string) ($options['status'] ?? ''));
        $requiresAcceptance = $options['requires_acceptance'] ?? ($receivingOfficerId !== null);

        if (!in_array($status, ['pending_acceptance', 'active', 'completed'], true)) {
            $status = $requiresAcceptance ? 'pending_acceptance' : 'active';
        }

        $acceptedById = $options['accepted_by'] ?? null;
        $acceptedByName = $options['accepted_by_name'] ?? null;
        $acceptanceNote = $options['acceptance_note'] ?? null;
        $acceptanceSource = $options['acceptance_source'] ?? null;

        $logEntry = [
            'log_id' => $logId,
            'office_code' => $officeCode,
            'office_name' => $officeName,
            'log_in_time' => $logInTime,
            'log_in_date' => $logInDate,
            'notes' => $notes,
            'user_id' => $userId ?: auth()->id(),
            'user_name' => $userName ?: auth()->user()->name ?? 'System',
            'timestamp' => now()->toISOString(),
            'status' => $status,
            'receiving_officer_id' => $receivingOfficerId,
            'receiving_officer_name' => $receivingOfficerName,
            'receiving_office_code' => $options['receiving_office_code'] ?? $officeCode,
            'receiving_office_name' => $options['receiving_office_name'] ?? $officeName,
        ];

        // Add purpose field for workflow-aware movements
        if (!empty($options['purpose'])) {
            $logEntry['purpose'] = $options['purpose'];
        }

        if ($status === 'active' && $acceptedById) {
            $logEntry['accepted_by'] = (int) $acceptedById;
            $logEntry['accepted_by_name'] = $acceptedByName ?? $receivingOfficerName ?? 'System';
            $logEntry['accepted_at'] = now()->toIso8601String();

            if ($acceptanceNote) {
                $logEntry['acceptance_note'] = $acceptanceNote;
            }

            if ($acceptanceSource) {
                $logEntry['acceptance_source'] = $acceptanceSource;
            }
        }

        if (!empty($options['acceptance_override_by'])) {
            $logEntry['acceptance_override_by'] = (int) $options['acceptance_override_by'];
            $logEntry['acceptance_override_by_name'] = $options['acceptance_override_by_name'] ?? null;
        }

        $currentLog[] = $logEntry;

        $this->movement_log = $currentLog;
        $this->current_office_code = $officeCode;
        $this->current_office_name = $officeName;
        $this->completed_offices = count($currentLog);

        return $this->save();
    }

    /**
     * Complete the current movement log entry
     */
    public function completeCurrentMovement($logOutTime = null, $notes = null)
    {
        $currentLog = $this->movement_log ?: [];

        if (!empty($currentLog)) {
            $lastIndex = count($currentLog) - 1;
            $currentLog[$lastIndex]['log_out_time'] = $logOutTime ?: now()->format('H:i');
            $currentLog[$lastIndex]['log_out_date'] = now()->format('Y-m-d');
            $currentLog[$lastIndex]['status'] = 'completed';
            if ($notes) {
                $currentLog[$lastIndex]['completion_notes'] = $notes;
            }

            $this->movement_log = $currentLog;
            return $this->save();
        }

        return false;
    }

    /**
     * Get the current active movement
     */
    public function getCurrentMovement()
    {
        $log = $this->movement_log ?: [];

        foreach (array_reverse($log) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $status = strtolower($entry['status'] ?? '');
            if ($status === 'active') {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Check if tracker is overdue
     */
    public function getIsOverdueAttribute()
    {
        return $this->deadline && Carbon::parse($this->deadline)->isPast();
    }

    /**
     * Get days until deadline
     */
    public function getDaysUntilDeadlineAttribute()
    {
        if (!$this->deadline) {
            return null;
        }

        return Carbon::now()->diffInDays(Carbon::parse($this->deadline), false);
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentageAttribute()
    {
        if ($this->total_offices === 0) {
            return 0;
        }

        return round(($this->completed_offices / $this->total_offices) * 100);
    }

    /**
     * Scope for active trackers
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for completed trackers
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for overdue trackers
     */
    public function scopeOverdue($query)
    {
        return $query->where('deadline', '<', now())
            ->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for priority filtering
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for user filtering
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope for department filtering
     */
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Get user who created this tracker
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function getCanEditAttribute(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        if ($user->type === 'super admin') {
            return true;
        }

        return (int) $this->created_by === (int) $user->id;
    }

    /**
     * Check if this tracker uses the KANGIS approval workflow.
     */
    public function isKangis3Step(): bool
    {
        return in_array($this->workflow_type, [
            self::WORKFLOW_KANGIS_3STEP,
            self::WORKFLOW_KANGIS_APPROVAL,
        ], true);
    }

    /**
     * Check if this tracker is a New KANGIS file (5-step QR-driven workflow,
     * created via the ?url=new_kangis page or scan-and-log).
     */
    public function isKangisNewFile(): bool
    {
        return $this->workflow_type === 'kangis_new_file'
            || strtolower((string) $this->module) === 'new_kangis';
    }

    /**
     * Get the current workflow step definition.
     */
    public function getCurrentStepDefinition(): ?array
    {
        if (!$this->isKangis3Step() || !$this->workflow_step) {
            return null;
        }

        return self::KANGIS_4STEP_DEFINITION[$this->workflow_step] ?? null;
    }

    /**
     * Get the next workflow step definition, or null if at the end.
     */
    public function getNextStepDefinition(): ?array
    {
        if (!$this->isKangis3Step()) {
            return null;
        }

        $activeStep = (int) ($this->workflow_step ?? 0);

        // For this workflow, clients need the currently actionable step definition.
        if ($activeStep > 0 && isset(self::KANGIS_4STEP_DEFINITION[$activeStep])) {
            return self::KANGIS_4STEP_DEFINITION[$activeStep];
        }

        $nextStep = $activeStep + 1;

        return self::KANGIS_4STEP_DEFINITION[$nextStep] ?? null;
    }

    /**
     * Advance to the next workflow step.
     */
    public function advanceWorkflowStep(): bool
    {
        if (!$this->isKangis3Step()) {
            return false;
        }

        $nextStep = ($this->workflow_step ?? 0) + 1;

        if (!isset(self::KANGIS_4STEP_DEFINITION[$nextStep])) {
            return false;
        }

        $this->workflow_step = $nextStep;

        return true;
    }

    /**
     * Get the full workflow progress for display.
     */
    public function getWorkflowProgress(): array
    {
        if (!$this->isKangis3Step()) {
            return [];
        }

        $currentStep = $this->workflow_step ?? 0;
        $config = $this->workflow_config ?? [];
        $progress = [];

        foreach (self::KANGIS_4STEP_DEFINITION as $step => $def) {
            $status = 'disabled';
            if ($step < $currentStep) {
                $status = 'completed';
            } elseif ($step === $currentStep) {
                $status = 'active';
            }

            $destinationName = $def['to_name'];
            if ($step === 4 && isset($config['current_department'])) {
                $destinationName = $config['current_department'];
            }
            if ($step === 4 && isset($config['destination_office_name'])) {
                $destinationName = $config['destination_office_name'];
            }

   
            $progress[] = [
                'step' => $step,
                'from_code' => $def['from_code'],
                'from_name' => $def['from_name'],
                'to_code' => $step === 3 ? ($config['destination_office_code'] ?? null) : $def['to_code'],
                'to_name' => $destinationName,
                'purpose' => $def['purpose'],
                'label' => $def['label'],
                'status' => $status,
            ];
        }

        return $progress;
    }
}
