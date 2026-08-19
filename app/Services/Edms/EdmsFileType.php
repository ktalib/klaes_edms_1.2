<?php

namespace App\Services\Edms;

/**
 * EdmsFileType
 *
 * The master folders that sit between a registry and its file-number folders in
 * every EDMS tree:
 *
 *   EDMS/{TREE}/{Registry_Slug}/{File_Type_Folder}/{FILE NUMBER}/{PAPER}/{file}
 *
 * Until now a registry folder held file-number folders directly ("naked", as the
 * registry calls it). A file's nature — regular, a subdivision mother, a merger
 * child, a temporary number — was nowhere on disk, so nobody browsing the tree
 * could tell them apart. These folders make that visible.
 *
 * NULL is a real, supported state: a file nobody has classified yet keeps the
 * legacy layout, directly under its registry. Nothing is moved until an operator
 * picks a type, so every path stored before this feature still resolves — see
 * EdmsDocumentPathResolver::candidates(), which offers both layouts.
 *
 * The key (`subdivision_mother`) is what the database stores; the folder
 * (`Subdivision/Children`) is what the disk gets. Two levels deep on purpose:
 * the grouping folders (Merger, Subdivision, Extension, Change_of_Purpose) are
 * how the registry describes them, and browsing them as one unit is the point.
 *
 * NOTE: file_indexings already has an unrelated `file_type` column holding the
 * applicant type (Individual / Corporate / Government). This concept lives in
 * `edms_file_type` and the two must not be confused.
 */
class EdmsFileType
{
    /** The column that carries the type on file_indexings, scannings and pagetypings. */
    public const COLUMN = 'edms_file_type';

    /**
     * key => [label, folder, group]
     *
     * `group` is the display heading the option is listed under; a type with no
     * group stands alone.
     */
    private const TYPES = [
        'regular' => [
            'label'  => 'Regular',
            'folder' => 'Regular',
            'group'  => null,
        ],
        'merger_children' => [
            'label'  => 'Merger — Children',
            'folder' => 'Merger/Children',
            'group'  => 'Merger',
        ],
        'merger_new' => [
            'label'  => 'Merger — New File',
            'folder' => 'Merger/New_File',
            'group'  => 'Merger',
        ],
        'subdivision_mother' => [
            'label'  => 'Subdivision — Mother',
            'folder' => 'Subdivision/Mother',
            'group'  => 'Subdivision',
        ],
        'subdivision_children' => [
            'label'  => 'Subdivision — Children',
            'folder' => 'Subdivision/Children',
            'group'  => 'Subdivision',
        ],
        'extension_old' => [
            'label'  => 'Extension — Old',
            'folder' => 'Extension/Old',
            'group'  => 'Extension',
        ],
        'extension_new' => [
            'label'  => 'Extension — New',
            'folder' => 'Extension/New',
            'group'  => 'Extension',
        ],
        'temporary' => [
            'label'  => 'Temporary File',
            'folder' => 'Temporary_File',
            'group'  => null,
        ],
        'change_of_purpose_old' => [
            'label'  => 'Change of Purpose — Old',
            'folder' => 'Change_of_Purpose/Old',
            'group'  => 'Change of Purpose',
        ],
        'change_of_purpose_new' => [
            'label'  => 'Change of Purpose — New',
            'folder' => 'Change_of_Purpose/New',
            'group'  => 'Change of Purpose',
        ],
        'regrant' => [
            'label'  => 'Regrant',
            'folder' => 'Regrant',
            'group'  => null,
        ],
        'resettlement' => [
            'label'  => 'Resettlement',
            'folder' => 'Resettlement',
            'group'  => null,
        ],
        'surrender' => [
            'label'  => 'Surrender',
            'folder' => 'Surrender',
            'group'  => null,
        ],
        'separation' => [
            'label'  => 'Separation',
            'folder' => 'Separation',
            'group'  => null,
        ],
        'title_status_litigation_old' => [
            'label'  => 'Litigation — Old',
            'folder' => 'Title_Status/Litigation/Old',
            'group'  => 'Title Status',
        ],
        'title_status_litigation_new' => [
            'label'  => 'Litigation — New',
            'folder' => 'Title_Status/Litigation/New',
            'group'  => 'Title Status',
        ],
        'title_status_revocation_old' => [
            'label'  => 'Revocation — Old',
            'folder' => 'Title_Status/Revocation/Old',
            'group'  => 'Title Status',
        ],
        'title_status_revocation_new' => [
            'label'  => 'Revocation — New',
            'folder' => 'Title_Status/Revocation/New',
            'group'  => 'Title Status',
        ],
        'title_status_cancellation_old' => [
            'label'  => 'Cancellation — Old',
            'folder' => 'Title_Status/Cancellation/Old',
            'group'  => 'Title Status',
        ],
        'title_status_cancellation_new' => [
            'label'  => 'Cancellation — New',
            'folder' => 'Title_Status/Cancellation/New',
            'group'  => 'Title Status',
        ],
        'title_status_withdrawal_old' => [
            'label'  => 'Withdrawal — Old',
            'folder' => 'Title_Status/Withdrawal/Old',
            'group'  => 'Title Status',
        ],
        'title_status_withdrawal_new' => [
            'label'  => 'Withdrawal — New',
            'folder' => 'Title_Status/Withdrawal/New',
            'group'  => 'Title Status',
        ],
        'title_status_amendment_old' => [
            'label'  => 'Amendment — Old',
            'folder' => 'Title_Status/Amendment/Old',
            'group'  => 'Title Status',
        ],
        'title_status_amendment_new' => [
            'label'  => 'Amendment — New',
            'folder' => 'Title_Status/Amendment/New',
            'group'  => 'Title Status',
        ],
    ];

    /**
     * Older or looser spellings that should land on a known key rather than mint
     * a folder of their own. Includes the folder names themselves, so a value
     * read back off disk round-trips.
     */
    private const ALIASES = [
        'merger/children'       => 'merger_children',
        'merger/new_file'       => 'merger_new',
        'merger new file'       => 'merger_new',
        'merger_new_file'       => 'merger_new',
        'subdivision/mother'    => 'subdivision_mother',
        'subdivision/children'  => 'subdivision_children',
        'extension/old'         => 'extension_old',
        'extension/new'         => 'extension_new',
        'temporary_file'        => 'temporary',
        'temporary file'        => 'temporary',
        'temp'                  => 'temporary',
        'change_of_purpose/old' => 'change_of_purpose_old',
        'change_of_purpose/new' => 'change_of_purpose_new',
        'change of purpose old' => 'change_of_purpose_old',
        'change of purpose new' => 'change_of_purpose_new',
        // Folder round-trips for the split Title Status types.
        'title_status/litigation/old' => 'title_status_litigation_old',
        'title_status/litigation/new' => 'title_status_litigation_new',
        'title_status/revocation/old' => 'title_status_revocation_old',
        'title_status/revocation/new' => 'title_status_revocation_new',
        'title_status/cancellation/old' => 'title_status_cancellation_old',
        'title_status/cancellation/new' => 'title_status_cancellation_new',
        'title_status/withdrawal/old' => 'title_status_withdrawal_old',
        'title_status/withdrawal/new' => 'title_status_withdrawal_new',
        'title_status/amendment/old' => 'title_status_amendment_old',
        'title_status/amendment/new' => 'title_status_amendment_new',
        // Values written before Title Status split into Old/New — a file
        // classified back then was the already-existing file, so it lands on Old.
        'title_status_litigation'   => 'title_status_litigation_old',
        'title_status_revocation'   => 'title_status_revocation_old',
        'title_status_cancellation' => 'title_status_cancellation_old',
        'title_status_withdrawal'   => 'title_status_withdrawal_old',
        'title_status_amendment'    => 'title_status_amendment_old',
        'title_status/litigation'   => 'title_status_litigation_old',
        'title_status/revocation'   => 'title_status_revocation_old',
        'title_status/cancellation' => 'title_status_cancellation_old',
        'title_status/withdrawal'   => 'title_status_withdrawal_old',
        'title_status/amendment'    => 'title_status_amendment_old',
        // The bare words, since a Title Status folder is usually named by its
        // action alone ("Revocation") rather than by the group it sits in.
        'litigation'   => 'title_status_litigation_old',
        'revocation'   => 'title_status_revocation_old',
        'cancellation' => 'title_status_cancellation_old',
        'cancelation'  => 'title_status_cancellation_old',
        'withdrawal'   => 'title_status_withdrawal_old',
        'amendment'    => 'title_status_amendment_old',
        're-grant'     => 'regrant',
        're grant'     => 'regrant',
    ];

    /** @return string[] every valid key, in display order */
    public static function keys(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * Every folder segment, deepest form — "Regular", "Merger/Children", …
     *
     * @return string[]
     */
    public static function folders(): array
    {
        return array_values(array_column(self::TYPES, 'folder'));
    }

    /**
     * The complete folder skeleton one registry needs, parents included and
     * ordered shallowest-first so a plain foreach can mkdir straight through.
     *
     * @return string[]
     */
    public static function folderSkeleton(): array
    {
        $folders = [];

        foreach (self::folders() as $folder) {
            $parts = explode('/', $folder);
            $path = '';
            foreach ($parts as $part) {
                $path = $path === '' ? $part : $path . '/' . $part;
                $folders[$path] = true;
            }
        }

        return array_keys($folders);
    }

    /**
     * Resolve any stored value (key, label or folder, in any case) to a canonical
     * key, or null when it is empty or unrecognised.
     *
     * Unrecognised is deliberately null rather than an exception: an unclassified
     * file is the normal state, and a junk value must not strand documents in a
     * folder no reader knows about.
     */
    public static function normalize($value): ?string
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $key = strtolower($raw);

        if (isset(self::TYPES[$key])) {
            return $key;
        }

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        // Labels use an em dash; accept a hyphen or any spacing the UI may send.
        $flat = strtolower(preg_replace('/\s+/', ' ', str_replace(['—', '–', '-', '_'], ' ', $raw)));
        foreach (self::TYPES as $candidate => $meta) {
            $labelFlat = strtolower(preg_replace('/\s+/', ' ', str_replace(['—', '–', '-', '_'], ' ', $meta['label'])));
            if ($flat === $labelFlat) {
                return $candidate;
            }
        }

        return null;
    }

    public static function isValid($value): bool
    {
        return self::normalize($value) !== null;
    }

    /** Human label, or null for an unclassified file. */
    public static function label($value): ?string
    {
        $key = self::normalize($value);

        return $key === null ? null : self::TYPES[$key]['label'];
    }

    /**
     * Folder segment for a type, or null when unclassified — and null means
     * "no segment at all", i.e. the legacy layout directly under the registry.
     */
    public static function folder($value): ?string
    {
        $key = self::normalize($value);

        return $key === null ? null : self::TYPES[$key]['folder'];
    }

    public static function group($value): ?string
    {
        $key = self::normalize($value);

        return $key === null ? null : self::TYPES[$key]['group'];
    }

    /**
     * The catalogue as the UI wants it.
     *
     * @return array<int, array{key:string, label:string, folder:string, group:?string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::TYPES as $key => $meta) {
            $options[] = [
                'key'    => $key,
                'label'  => $meta['label'],
                'folder' => $meta['folder'],
                'group'  => $meta['group'],
            ];
        }

        return $options;
    }

    /** Validation rule body for `in:` — the canonical keys only. */
    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::keys());
    }
}
