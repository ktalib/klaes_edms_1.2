<?php

namespace App\Http\Controllers;

use App\Models\ApplicationMother;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Dedicated update flow for KANGIS indexed files.
 *
 * Why this exists rather than reusing the create/edit pages:
 *
 * Most physical KANGIS folders carry the same printed file number, so one file
 * number legitimately maps to several physical files. They are told apart by
 * kangis_fileno_placeholder, and file_indexings.file_number carries an "_N" suffix
 * because that column is uniquely indexed. That is why the create path deliberately
 * skips the duplicate check (FileIndexingController::store, "$isKangisVariant"), and
 * why store() always calls KangisFileNoPlaceholderService::resolveForNewRecord() —
 * which unconditionally allocates the *next* suffix. There is no "resolve for an
 * existing record" counterpart, so saving an existing KANGIS variant through the
 * create form mints yet another variant instead of updating the one in hand.
 *
 * This controller is that missing counterpart:
 *   - file_number is pinned to the stored value and never re-resolved
 *   - no duplicate check runs (the row already exists)
 *   - the placeholder is mirrored into kangis_grouping, which
 *     FileIndexingController::update() does not do
 *
 * Everything else is delegated to FileIndexingController::update() so the two paths
 * cannot drift, and nothing in the existing create/edit workflow is modified.
 */
class KangisFileIndexUpdateController extends Controller
{
    /**
     * Render the KANGIS update form (a clone of the create form, prefilled).
     */
    public function edit(Request $request, $id)
    {
        try {
            $record = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', (int) $id)
                ->first();

            if (!$record) {
                return redirect()->route('kangis.indexed-files')
                    ->with('error', 'KANGIS indexed file not found.');
            }

            // Attach the related-file rows the form's edit mode reads
            // (window.editingRecord.related_details).
            $relatedDetails = DB::connection('sqlsrv')
                ->table('file_indexing_links')
                ->where('file_indexing_id', $record->id)
                ->get()
                ->map(function ($link) {
                    return [
                        'file_number' => $link->file_number,
                        'related_fileno' => $link->file_number,
                        'file_title' => $link->file_title ?? null,
                        'plot_number' => $link->plot_number ?? null,
                        'tp_no' => $link->tp_no ?? null,
                        'lpkn_no' => $link->lpkn_no ?? null,
                        'location' => $link->location ?? null,
                        'entity_type' => $link->entity_type ?? null,
                        'entity_name' => $link->entity_name ?? null,
                        'customer_name' => $link->customer_name ?? null,
                        'land_use_type' => $link->land_use_type ?? null,
                        'district' => $link->district ?? null,
                        'lga' => $link->lga ?? null,
                    ];
                })
                ->values()
                ->all();

            // The shared form partials read a handful of properties that are NOT columns
            // on file_indexings ($record->has_related_file, $record->land_use, ...) and
            // read them unguarded, inside branches that only run when $record is set.
            // No page ever set $record before this one, so those branches had never
            // executed; on a plain stdClass row they raise "Undefined property", which
            // Laravel promotes to an ErrorException and 500s the page.
            //
            // Fluent returns null for anything absent, so the partials stay untouched and
            // any property they reach for in future degrades to "empty" instead of fatal.
            $attributes = (array) $record;
            $attributes['related_details'] = $relatedDetails;
            $record = new \Illuminate\Support\Fluent($attributes);

            $PageTitle = 'Update KANGIS File Index';
            $PageDescription = 'Update an existing KANGIS indexed file';

            $availableApplications = $this->getAvailableApplications();
            $registries = \App\Models\Registry::orderBy('name')->get();
            $lgas = \App\Models\Lga::orderBy('name')->pluck('name');
            $districts = \App\Models\District::orderBy('name')->pluck('name');
            $physicalRegistries = \App\Models\PhysicalRegistry::orderBy('name')->get();
            $landUseTypes = \App\Models\LandUseType::orderBy('name')->get()->pluck('name', 'name');
            $streetNames = \App\Models\StreetName::orderBy('name')->get();

            $backButton = [
                'label' => 'Back to KANGIS Indexed Files',
                'route' => route('kangis.indexed-files'),
            ];

            $isNewKnMode = false;
            $prefillFileNumber = '';
            $prefillTrackingId = '';
            $returnTo = (string) $request->query('return_to', route('kangis.indexed-files'));

            return view('fileindexing.addons.kangis_update_indexing', compact(
                'record',
                'PageTitle',
                'PageDescription',
                'availableApplications',
                'registries',
                'lgas',
                'districts',
                'physicalRegistries',
                'landUseTypes',
                'streetNames',
                'backButton',
                'isNewKnMode',
                'prefillFileNumber',
                'prefillTrackingId',
                'returnTo'
            ));
        } catch (Throwable $e) {
            Log::error('KangisFileIndexUpdate::edit - failed to load form', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('kangis.indexed-files')
                ->with('error', 'Error loading KANGIS update form: ' . $e->getMessage());
        }
    }

    /**
     * Save the KANGIS variant. file_number is pinned; the rest is delegated to the
     * shared update path, then the KANGIS grouping row is brought back in step.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $fileId = (int) $id;

        $existing = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('id', $fileId)
            ->first();

        if (!$existing) {
            return response()->json([
                'success' => false,
                'message' => 'KANGIS indexed file not found.',
            ], 404);
        }

        $pinnedFileNumber = trim((string) $existing->file_number);

        // Pin the identity fields before validation. The "_N" suffix distinguishes this
        // physical folder from its same-numbered siblings and must survive every edit;
        // letting the form change it would drag the whole collision/cascade-rename
        // problem back into a flow whose entire purpose is to avoid it.
        $request->merge([
            'file_number' => $pinnedFileNumber,
            'general_registry' => $request->input('general_registry') ?: ($existing->general_registry ?? 'KANGIS'),
        ]);

        // Delegate the actual save. Reused rather than copied so this path picks up
        // every fix made to the shared one (bills, links, PRA, prop_id, entity/customer).
        $response = app(FileIndexingController::class)->update($request, $fileId);

        $payload = $response->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $response;
        }

        // The one thing the shared update path does not do: keep kangis_grouping in step.
        $placeholder = trim((string) $request->input('kangis_fileno_placeholder', ''));
        if ($placeholder !== '') {
            $this->syncKangisGrouping($pinnedFileNumber, $placeholder);
        }

        $payload['message'] = 'KANGIS file index updated successfully.';
        $payload['kangis'] = [
            'id' => $fileId,
            'file_number' => $pinnedFileNumber,
            'placeholder' => $placeholder,
            'redirect_url' => route('kangis.indexed-files'),
        ];

        return response()->json($payload, $response->getStatusCode());
    }

    /**
     * Mirror the placeholder onto this variant's own kangis_grouping row.
     *
     * Matching is on the FULL file number first ("KNML 1_2"), because store() clones a
     * grouping row per variant with kangis_awaiting_fileno set to the suffixed number.
     * Only when no such row exists does this fall back to the bare number — the case
     * where this record is the first (unsuffixed) one for that file number.
     *
     * The base-number fallback is deliberately guarded: stripping "_N" first and
     * updating the base row would stamp one variant's placeholder onto a different
     * physical file.
     */
    private function syncKangisGrouping(string $fileNumber, string $placeholder): void
    {
        try {
            if (!Schema::connection('sqlsrv')->hasTable('kangis_grouping')) {
                return;
            }

            $payload = ['kangis_fileno_placeholder' => $placeholder];

            $optional = [
                'kangis_fileno_resolved' => $fileNumber,
                'updated_at' => now(),
            ];

            foreach ($optional as $column => $value) {
                if (Schema::connection('sqlsrv')->hasColumn('kangis_grouping', $column)) {
                    $payload[$column] = $value;
                }
            }

            if (Schema::connection('sqlsrv')->hasColumn('kangis_grouping', 'updated_by')) {
                $payload['updated_by'] = Auth::user()->name ?? Auth::id();
            }

            $affected = DB::connection('sqlsrv')
                ->table('kangis_grouping')
                ->whereRaw('UPPER(LTRIM(RTRIM(kangis_awaiting_fileno))) = UPPER(?)', [$fileNumber])
                ->update($payload);

            if ($affected === 0) {
                $baseFileNumber = preg_replace('/_\d+$/', '', $fileNumber);

                // Only fall back when this record IS the bare one — never map a suffixed
                // variant onto the base row.
                if ($baseFileNumber === $fileNumber) {
                    $affected = DB::connection('sqlsrv')
                        ->table('kangis_grouping')
                        ->whereRaw('UPPER(LTRIM(RTRIM(kangis_awaiting_fileno))) = UPPER(?)', [$baseFileNumber])
                        ->update($payload);
                }
            }

            Log::info('KangisFileIndexUpdate::syncKangisGrouping', [
                'file_number' => $fileNumber,
                'placeholder' => $placeholder,
                'rows_updated' => $affected,
            ]);
        } catch (Throwable $e) {
            // A grouping mirror failure must not fail the save — the authoritative row
            // in file_indexings is already committed at this point.
            Log::warning('KangisFileIndexUpdate::syncKangisGrouping failed', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getAvailableApplications()
    {
        return ApplicationMother::on('sqlsrv')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('file_indexings')
                    ->whereRaw('file_indexings.main_application_id = mother_applications.id');
            })
            ->select('id', 'fileno', 'np_fileno', 'first_name', 'middle_name', 'surname', 'corporate_name', 'applicant_type')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
    }
}
