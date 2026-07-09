<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalSearchToken extends Model
{
    use HasFactory;

    /**
     * Maximum number of times a single token may be spent per file number.
     */
    public const MAX_USES = 2;

    protected $connection = 'sqlsrv';
    protected $table = 'legal_search_tokens';

    protected $fillable = [
        'token',
        'file_number',
        'applicant_name',
        'client_name',
        'property_location',
        'client_address',
        'payment_reason',
        'amount_paid',
        'receipt_number',
        'date_paid',
        'is_used',
        'usage_count',
        'used_at',
        'created_by'
    ];

    protected $casts = [
        'date_paid' => 'date',
        'is_used' => 'boolean',
        'usage_count' => 'integer',
        'used_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Uses still available on this token.
     */
    public function getRemainingUsesAttribute(): int
    {
        return max(0, self::MAX_USES - (int) $this->usage_count);
    }

    /**
     * Whether the token has been fully spent (no remaining uses).
     */
    public function getIsExhaustedAttribute(): bool
    {
        return $this->remaining_uses <= 0;
    }
}
