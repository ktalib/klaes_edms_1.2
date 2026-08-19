<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One stage of a duplex. `rank` is the execution order and comes from the order the
 * officer ticked the types — a type may legitimately appear twice at different ranks.
 *
 * A stage never writes to the registry. It consumes `input_holding_no` (or the
 * duplex's real source files, when it is rank 1) and emits holding numbers into
 * duplex_parcel_update_files.
 */
class DuplexParcelUpdateStage extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'duplex_parcel_update_stages';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_DONE     = 'done';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'duplex_parcel_update_id',
        'duplex_id',
        'type',
        'rank',
        'status',
        'input_holding_no',
        'plot_count',
        'payload',
        'tracking_id',
        'reject_reason',
        'completed_at',
        'captured_by',
        'updated_by',
    ];

    protected $casts = [
        'payload'      => 'array',
        'completed_at' => 'datetime',
    ];

    public function duplex(): BelongsTo
    {
        return $this->belongsTo(DuplexParcelUpdate::class, 'duplex_parcel_update_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DuplexParcelUpdateFile::class, 'duplex_parcel_update_stage_id')
            ->orderBy('sequence');
    }

    public function label(): string
    {
        return DuplexParcelUpdate::TYPES[$this->type] ?? $this->type;
    }

    /** Merger collapses many into one; the rest fan out to plot_count children. */
    public function outputCount(): int
    {
        if (in_array($this->type, ['merger', 'extension'], true)) {
            return 1;
        }

        return max(1, (int) ($this->plot_count ?? 1));
    }
}
