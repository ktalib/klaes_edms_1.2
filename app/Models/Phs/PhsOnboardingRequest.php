<?php

namespace App\Models\Phs;

use Illuminate\Database\Eloquent\Model;

class PhsOnboardingRequest extends Model
{
    protected $connection = 'sqlsrv';

    const STATUS_PENDING = 'pending';
    const STATUS_PAYMENT_RECEIVED = 'payment_received';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ACTIVATED = 'activated';

    protected $fillable = [
        'organization_name',
        'organization_type',
        'contact_name',
        'contact_email',
        'phone',
        'address',
        'department',
        'job_title',
        'initial_token_package',
        'additional_notes',
        'status',
        'payment_reference',
        'payment_amount',
        'payment_received_at',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'activation_token',
        'activation_token_expires_at',
        'created_phs_institution_id',
    ];

    protected $casts = [
        'payment_received_at' => 'datetime',
        'approved_at' => 'datetime',
        'activation_token_expires_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(PhsInstitution::class, 'created_phs_institution_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canRegister(): bool
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }

        if (!$this->activation_token || !$this->activation_token_expires_at) {
            return false;
        }

        return now()->isBefore($this->activation_token_expires_at);
    }

    public function generateActivationToken(): void
    {
        $this->activation_token = \Str::random(64);
        $this->activation_token_expires_at = now()->addDays(7);
        $this->save();
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
