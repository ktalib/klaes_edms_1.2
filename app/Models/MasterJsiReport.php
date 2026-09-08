<?php

namespace App\Models;

use App\Models\Concerns\HasBoundarySegments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The Physical Planning site observation behind a parcel update.
 *
 * One form, two categories and five layouts. The category says which register the
 * inspection came from — SPU for the five single workflows, APU for a duplex — and
 * parcel_update_type says which of the Ministry sheets it prints as. The narrative
 * fields are shared; the S/N table rows differ per type and are described once in
 * App\Support\MasterJsiPortionTemplates.
 *
 * An approved report is what Generate Recommendation and Approve rest on, in the
 * place the KNUPDA/Physical Planning handshake used to hold. The question is asked
 * through App\Support\MasterJsiGate, never by reading `status` at a call site — the
 * gate also carries the legacy knupda_status arm that keeps pre-cutover records
 * moving.
 */
class MasterJsiReport extends Model
{
    use HasBoundarySegments;

    protected $connection = 'sqlsrv';

    protected $table = 'master_jsi_reports';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';

    public const CATEGORY_SPU = 'SPU';
    public const CATEGORY_APU = 'APU';

    /** The two categories, with the words the officer picks between. */
    public const CATEGORIES = [
        self::CATEGORY_SPU => 'SPU — Single Parcel Update',
        self::CATEGORY_APU => 'APU — Advance Parcel Update (Duplex)',
    ];

    /**
     * The workflow a report belongs to: slug => [table, label].
     *
     * The slugs are the ones ParcelUpdateNotificationService::typeLabel() already
     * speaks, so a report, a notification and a menu item all name the same thing.
     * subject_type stores the slug rather than a model class: six workflows sit in
     * six tables, and a class name would break the moment one is moved.
     */
    public const SUBJECTS = [
        'subdivision'       => ['plot_subdivision_applications', 'Plot Subdivision'],
        'separation'        => ['plot_separation_applications',  'Plot Separation'],
        'merger'            => ['plot_merger_applications',      'Plot Merger'],
        'extension'         => ['plot_extension_applications',   'Plot Extension'],
        'change_of_purpose' => ['change_of_purpose_applications', 'Change of Purpose'],
        'duplex'            => ['duplex_parcel_updates',         'APU - Advance Parcel Update (Duplex)'],
    ];

    protected $fillable = [
        'jsi_ref',
        'category',
        'parcel_update_type',
        'subject_type',
        'subject_id',
        'duplex_stage_id',
        'file_number',
        'file_title',
        'applicant_name',
        'inspection_date',
        'location',
        'plot_number',
        'district',
        'lga',
        'inspection_officer',
        'inspection_officer_id',
        'available_on_ground',
        'boundary_description',
        'boundary_segments',
        'road_reservation',
        'conformity',
        'prevailing_land_use',
        'existing_land_use',
        'recommended_land_use',
        'existing_purpose',
        'recommended_purpose',
        'number_of_units',
        'average_size',
        'narrative_summary',
        'additional_observations',
        'status',
        'generated_at',
        'generated_by',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_reason',
        'sent_to_deeds_at',
        'sent_to_deeds_by',
        'created_by',
        'updated_by',
        'is_deleted',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'inspection_date'  => 'date',
        'conformity'       => 'boolean',
        'is_deleted'       => 'boolean',
        'number_of_units'  => 'integer',
        'generated_at'     => 'datetime',
        'submitted_at'     => 'datetime',
        'approved_at'      => 'datetime',
        'sent_to_deeds_at' => 'datetime',
        'deleted_at'       => 'datetime',
    ];

    /** The four-direction view of boundary_description the form binds to. */
    protected $appends = ['boundary_segments'];

    public function portions(): HasMany
    {
        return $this->hasMany(MasterJsiPortion::class, 'master_jsi_report_id')
            ->orderBy('sequence')
            ->orderBy('id');
    }

    /** Reports that have not been soft-deleted. */
    public function scopeVisible($query)
    {
        return $query->where(function ($q) {
            $q->where('is_deleted', 0)->orWhereNull('is_deleted');
        });
    }

    public function scopeForSubject($query, string $subjectType, int $subjectId)
    {
        return $query->where('subject_type', $subjectType)->where('subject_id', $subjectId);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function sentToDeeds(): bool
    {
        return $this->sent_to_deeds_at !== null;
    }

    /** "Plot Subdivision", "Change of Purpose" — the label the sheet and the menu use. */
    public function typeLabel(): string
    {
        return self::SUBJECTS[$this->parcel_update_type][1]
            ?? ucfirst(str_replace('_', ' ', (string) $this->parcel_update_type));
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? (string) $this->category;
    }

    /**
     * Next free MJSI-<year>-<serial>.
     *
     * Same shape as DuplexHoldingNumberService::allocateDuplexId(): the serial is
     * the highest issued this year plus one, and a deleted report does not free its
     * number — the counter only moves forward, so a reference on a printed sheet is
     * never re-issued to a different inspection.
     */
    public static function allocateRef(?int $year = null): string
    {
        $year = $year ?: (int) date('Y');
        $max  = 0;

        $existing = static::where('jsi_ref', 'LIKE', 'MJSI-' . $year . '-%')->pluck('jsi_ref');

        foreach ($existing as $ref) {
            if (preg_match('/-(\d+)$/', (string) $ref, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('MJSI-%d-%04d', $year, $max + 1);
    }
}
