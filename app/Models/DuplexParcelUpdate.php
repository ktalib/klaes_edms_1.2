<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One duplex = one instruction carrying 1..N parcel updates in a declared order.
 *
 * `stages` is the canonical ordered plan captured at Step 1 (tick order = execution
 * order). The stage rows in duplex_parcel_update_stages are built from it and keep
 * the same ranks; nothing downstream may re-order by type.
 */
class DuplexParcelUpdate extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'duplex_parcel_updates';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_CAPTURED  = 'captured';
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_IN_LAND   = 'in_land';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_REJECTED  = 'rejected';

    /** The five parcel updates a duplex may carry, with their display labels. */
    public const TYPES = [
        'merger'            => 'Merger',
        'subdivision'       => 'Subdivision',
        'change_of_purpose' => 'Change of Purpose',
        'extension'         => 'Extension',
        'separation'        => 'Separation',
    ];

    protected $fillable = [
        'duplex_id',
        'applicant_name',
        'file_title',
        'source_file_nos',
        'stages',
        'status',
        'land_use',
        'plot_no',
        'house_no',
        'street_name',
        'district',
        'lga',
        'state',
        'phone',
        'address',
        'land_value',
        'knupda_fee',
        'knupda_status',
        'knupda_remarks',
        'remarks',
        'application_generated_at',
        'recommendation_generated_at',
        'conveyance_generated_at',
        'sent_to_land_at',
        'committed_at',
        'captured_by',
        'updated_by',
        'approved_by',
        'committed_by',
        'is_deleted',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'source_file_nos'             => 'array',
        'stages'                      => 'array',
        'application_generated_at'    => 'datetime',
        'recommendation_generated_at' => 'datetime',
        'conveyance_generated_at'     => 'datetime',
        'sent_to_land_at'             => 'datetime',
        'committed_at'                => 'datetime',
        'deleted_at'                  => 'datetime',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(DuplexParcelUpdateStage::class, 'duplex_parcel_update_id')
            ->orderBy('rank');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DuplexParcelUpdateFile::class, 'duplex_parcel_update_id')
            ->orderBy('sequence');
    }

    /** Not-soft-deleted rows — the same idiom the five single parcel tables use. */
    public function scopeVisible($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
        });
    }

    /** "M(1) S(2) CoP(3)" — the stage plan in execution order, for the listing. */
    public function stageSummary(): string
    {
        $plan = collect($this->stages ?? [])->sortBy('rank');

        return $plan->map(function ($s) {
            $label = self::TYPES[$s['type'] ?? ''] ?? ($s['type'] ?? '?');
            return $label . '(' . ($s['rank'] ?? '?') . ')';
        })->implode('  ');
    }

    /** A single-stage duplex still runs the whole pipeline; wording just changes. */
    public function isSingleStage(): bool
    {
        return count($this->stages ?? []) === 1;
    }
}
