<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * File Request (FR) raised from web Quick Search to the SCB Monitor (the
 * mobile-only file searcher). The monitor runs the physical search and feeds
 * back Found / Not Found.
 */
class FileSearchRequest extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'file_search_requests';

    const STATUS_PENDING   = 'PENDING';
    const STATUS_SEARCHING = 'SEARCHING';
    const STATUS_FOUND     = 'FOUND';
    const STATUS_NOT_FOUND = 'NOT_FOUND';
    const STATUS_CLOSED    = 'CLOSED';

    const SOURCE_QUICK_SEARCH = 'QUICK_SEARCH';
    const SOURCE_DFR          = 'DFR';

    protected $fillable = [
        'request_no',
        'file_number',
        'file_title',
        'requester_user_id',
        'assigned_monitor_id',
        'status',
        'resolved_status',
        'current_location',
        'feedback_note',
        'responded_by',
        'responded_at',
        'source',
        'front_desk_acted_at',
        'front_desk_acted_by',
    ];

    protected $casts = [
        'responded_at'        => 'datetime',
        'front_desk_acted_at' => 'datetime',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_monitor_id');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Generate a sequential request number: FR-2026-00001
     */
    public static function generateRequestNo(): string
    {
        $prefix = 'FR-' . now()->format('Y') . '-';

        $last = static::where('request_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('request_no');

        $next = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    public function scopePending($q)   { return $q->where('status', self::STATUS_PENDING); }
    public function scopeOpen($q)      { return $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_SEARCHING]); }
}
