<?php

namespace App\Services;

use App\Http\Controllers\MlsFileNoController;
use App\Models\ChangeOfPurposeApplication;
use App\Models\DuplexParcelUpdate;
use App\Models\DuplexParcelUpdateFile;
use App\Models\DuplexParcelUpdateStage;
use App\Models\PlotApplicationSize;
use App\Models\PlotExtensionApplication;
use App\Models\PlotMergerApplication;
use App\Models\PlotSeparationApplication;
use App\Models\PlotSubdivisionApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a duplex's holding numbers into real file numbers.
 *
 * The one rule this service exists to honour: there is only ONE commissioning
 * engine. Every file number is still minted by MlsFileNoController
 * (generateBatch / generateMlsFileNumber), because that is where lineage
 * (prop_id, parent_prop_id, related_fileno), decommissioning, PRA rows and the six
 * fixes from the 2026-07-10 parcel-update audit live. Re-implementing any of it
 * here would re-earn every one of those defects.
 *
 * How the chain resolves
 * ----------------------
 * Stages run in RANK order. Stage 1 consumes the duplex's real source files.
 * Every later stage consumes the real file numbers the previous stage just
 * produced — so by the time a stage runs, its input is always a real registry
 * file, which is exactly what the commissioning engine expects.
 *
 * Holding numbers are therefore a planning device only: they let the officer lay
 * out the whole duplex before anything is committed, and at commit each holding
 * row is stamped with the real number it became.
 *
 * Decommissioning falls out of this for free. Each stage decommissions its own
 * input through the existing path, so the original sources are retired by stage 1
 * and every intermediate file is retired by the stage that consumes it.
 */
class DuplexCommitService
{
    public function __construct(
        protected DuplexHoldingNumberService $holding
    ) {}

    /**
     * Commit a duplex. Returns a per-stage summary of what was created and retired.
     *
     * @param array $meta commissioned_by / commission_date / commission_time / customer_type
     */
    public function commit(DuplexParcelUpdate $duplex, array $meta = []): array
    {
        if ($duplex->status !== DuplexParcelUpdate::STATUS_IN_LAND) {
            throw new \RuntimeException(
                'Only a duplex sitting with Land can be commissioned. Current status: ' . $duplex->status
            );
        }

        $stages = $duplex->stageRows()->get(); // the relation already orders by rank

        if ($stages->isEmpty()) {
            throw new \RuntimeException('This duplex has no stages to commission.');
        }

        // Guard the namespace before anything is written: a holding number that has
        // leaked into the registry means a coding slip, and it must surface as a
        // refusal rather than as a poisoned index.
        foreach ($duplex->files as $file) {
            if ($file->holding_no) {
                $this->holding->assertNotInRegistry($file->holding_no);
            }
        }

        $summary = [];

        // Stage 1's input is the real source file(s) the officer picked.
        $carry = array_values(array_filter((array) ($duplex->source_file_nos ?? [])));

        foreach ($stages as $stage) {
            if (empty($carry)) {
                throw new \RuntimeException(
                    "Stage {$stage->rank} ({$stage->label()}) has no input file to work from."
                );
            }

            $result = $this->commitStage($duplex, $stage, $carry, $meta);

            $summary[] = [
                'rank'           => $stage->rank,
                'type'           => $stage->type,
                'label'          => $stage->label(),
                'inputs'         => $carry,
                'files'          => $result['files'],
                'decommissioned' => $carry,
            ];

            // What this stage produced becomes the next stage's input.
            $carry = $result['files'];
        }

        $duplex->update([
            'status'       => DuplexParcelUpdate::STATUS_COMMITTED,
            'committed_at' => now(),
            'committed_by' => Auth::id(),
            'updated_by'   => Auth::id(),
        ]);

        Log::info('Duplex committed', [
            'duplex_id' => $duplex->duplex_id,
            'stages'    => count($summary),
        ]);

        return $summary;
    }

    /**
     * Run one stage: materialise the application row the commissioning engine reads
     * its lineage from, commission, then stamp the results onto the holding rows.
     */
    protected function commitStage(
        DuplexParcelUpdate $duplex,
        DuplexParcelUpdateStage $stage,
        array $inputs,
        array $meta
    ): array {
        $appId = $this->materialiseApplication($duplex, $stage, $inputs);

        // The relation already orders by sequence; adding it again would duplicate
        // the ORDER BY column, which SQL Server rejects outright.
        $holdings = $stage->files()
            ->where('role', DuplexParcelUpdateFile::ROLE_HOLDING)
            ->get();

        $files = match ($stage->type) {
            'change_of_purpose' => $this->commissionChangeOfPurpose($duplex, $stage, $inputs, $appId, $meta),
            default             => $this->commissionStandard($duplex, $stage, $inputs, $appId, $holdings->count(), $meta),
        };

        // Stamp each holding row with the real number it became. A holding row that
        // now carries final_file_no is what the Land screen renders as
        // "holding -> new file", and it is the audit trail afterwards.
        foreach ($holdings->values() as $i => $row) {
            if (isset($files[$i])) {
                $row->update([
                    'final_file_no' => $files[$i],
                    'role'          => DuplexParcelUpdateFile::ROLE_RESULT,
                ]);
            }
        }

        $stage->update([
            'status'       => DuplexParcelUpdateStage::STATUS_DONE,
            'completed_at' => now(),
            'updated_by'   => Auth::id(),
        ]);

        return ['files' => $files];
    }

    /**
     * Create the row in the existing parcel-update table that the commissioning
     * engine resolves lineage from (it looks the mother/sources up by app id).
     *
     * These rows are real applications — the duplex genuinely performed them — so
     * they are written as approved and tagged with the duplex reference, which
     * keeps the single-workflow registers a truthful record of what happened.
     */
    protected function materialiseApplication(
        DuplexParcelUpdate $duplex,
        DuplexParcelUpdateStage $stage,
        array $inputs
    ): ?int {
        $primary = $inputs[0];
        $tag     = "[Duplex {$duplex->duplex_id} · stage {$stage->rank}]";
        $payload = (array) ($stage->payload ?? []);

        $common = [
            'file_no'        => $primary,
            'file_title'     => $duplex->file_title ?: $duplex->applicant_name,
            'applicant_name' => $duplex->applicant_name,
            'plot_no'        => $duplex->plot_no,
            'house_no'       => $duplex->house_no,
            'street_name'    => $duplex->street_name,
            'district'       => $duplex->district,
            'lga'            => $duplex->lga,
            'state'          => $duplex->state,
            'land_use'       => explode('-', (string) $primary)[0],
            'remarks'        => $tag,
            'knupda_status'  => $duplex->knupda_status,
            'knupda_fee'     => $duplex->knupda_fee,
            'land_value'     => $duplex->land_value,
            'captured_by'    => $duplex->captured_by,
            'updated_by'     => Auth::id(),
        ];

        $plots = (array) ($payload['plots'] ?? []);

        switch ($stage->type) {
            case 'subdivision':
                $app = PlotSubdivisionApplication::create($common + [
                    'num_plots' => $stage->outputCount(),
                    'status'    => PlotSubdivisionApplication::STATUS_APPROVED,
                ]);
                $this->writeSizes($app->id, 'subdivision', 'subdivision_fragment', $plots);
                return $app->id;

            case 'separation':
                $app = PlotSeparationApplication::create($common + [
                    'num_plots' => $stage->outputCount(),
                    'status'    => PlotSeparationApplication::STATUS_APPROVED,
                ]);
                $this->writeSizes($app->id, 'separation', 'separation_fragment', $plots);
                return $app->id;

            case 'merger':
                $app = PlotMergerApplication::create($common + [
                    'temp_file_no' => $stage->input_holding_no ?: $duplex->duplex_id,
                    'num_plots'    => max(2, count($inputs)),
                    'status'       => PlotMergerApplication::STATUS_APPROVED,
                ]);

                // Merger lineage is read back off these rows: the engine collects
                // every source_file_no to build parent_prop_id and related_fileno.
                foreach (array_values($inputs) as $i => $sourceFileNo) {
                    PlotApplicationSize::create([
                        'application_id'    => $app->id,
                        'application_type'  => 'merger',
                        'plot_number'       => $plots[$i]['plot_no'] ?? ('Plot ' . ($i + 1)),
                        'source_file_no'    => $sourceFileNo,
                        'source_file_title' => $plots[$i]['file_title'] ?? '—',
                        'plot_size'         => $plots[$i]['size'] ?? 0,
                        'type'              => 'merger_source',
                    ]);
                }
                return $app->id;

            case 'extension':
                $app = PlotExtensionApplication::create([
                    'file_no'        => $primary,
                    'applicant_name' => $duplex->applicant_name,
                    'land_use'       => explode('-', (string) $primary)[0],
                    'plot_no'        => $duplex->plot_no,
                    'district'       => $duplex->district,
                    'lga'            => $duplex->lga,
                    'status'         => PlotExtensionApplication::STATUS_APPROVED,
                    'captured_by'    => $duplex->captured_by,
                    'updated_by'     => Auth::id(),
                    'remarks'        => $tag,
                ]);
                return $app->id;

            case 'change_of_purpose':
                $app = ChangeOfPurposeApplication::create([
                    'applicant_name' => $duplex->applicant_name,
                    'file_no'        => $primary,
                    'purpose'        => strtoupper((string) ($payload['new_land_use'] ?? '')),
                    'land_use'       => explode('-', (string) $primary)[0],
                    'district'       => $duplex->district,
                    'lga'            => $duplex->lga,
                    'plot_no'        => $duplex->plot_no,
                    'status'         => ChangeOfPurposeApplication::STATUS_APPROVED,
                    'knupda_status'  => $duplex->knupda_status,
                    'knupda_fee'     => $duplex->knupda_fee,
                    'remarks'        => $tag,
                    'captured_by'    => $duplex->captured_by,
                    'updated_by'     => Auth::id(),
                ]);
                return $app->id;
        }

        return null;
    }

    protected function writeSizes(int $appId, string $appType, string $rowType, array $plots): void
    {
        foreach (array_values($plots) as $i => $plot) {
            PlotApplicationSize::create([
                'application_id'   => $appId,
                'application_type' => $appType,
                'plot_number'      => $plot['plot_no'] ?? ('Plot ' . ($i + 1)),
                'plot_size'        => $plot['size'] ?? 0,
                'type'             => $rowType,
            ]);
        }
    }

    /**
     * Subdivision / Separation / Merger / Extension.
     *
     * generateBatch refuses a quantity below 2, so a single-output stage (a merger
     * result, an extension, a one-plot subdivision) goes through the single-file
     * endpoint instead. Both write the same lineage.
     */
    protected function commissionStandard(
        DuplexParcelUpdate $duplex,
        DuplexParcelUpdateStage $stage,
        array $inputs,
        ?int $appId,
        int $quantity,
        array $meta
    ): array {
        $quantity = max(1, $quantity);
        $landUse  = $this->landUseFor($duplex, $stage, $inputs);

        // The single-file path refuses to commission without a tracking id and will not
        // invent one (unlike the batch path, which mints them). Say so here, naming the
        // stage, instead of surfacing the engine's context-free message.
        if ($quantity < 2 && empty($stage->tracking_id)) {
            throw new \RuntimeException(
                "Stage {$stage->rank} ({$stage->label()}) needs a Tracking ID before it can be "
                . 'commissioned. Add it to the stage, or create the grouping record for this file.'
            );
        }
        $payload  = (array) ($stage->payload ?? []);
        $plots    = (array) ($payload['plots'] ?? []);

        $appIdField = match ($stage->type) {
            'subdivision' => 'subdivision_app_id',
            'separation'  => 'separation_app_id',
            'merger'      => 'merger_app_id',
            default       => null,
        };

        $base = [
            'application_type'        => $stage->type,
            'file_option'             => $stage->type,
            'land_use'                => $landUse,
            'customer_type'           => $meta['customer_type'] ?? 'Individual',
            'commissioned_by'         => $meta['commissioned_by'] ?? null,
            'commission_date'         => $meta['commission_date'] ?? now()->toDateString(),
            'commission_time'         => $meta['commission_time'] ?? now()->format('H:i'),
            'allocated_by_filter'     => '',
            'default_allocation_type' => null,
        ];

        if ($appIdField && $appId) {
            $base[$appIdField] = $appId;
        }

        if ($quantity < 2) {
            $plot = $plots[0] ?? [];

            return $this->callSingle($base + [
                'tracking_id'      => $stage->tracking_id,
                'file_name'        => $plot['holder'] ?? $duplex->file_title ?: $duplex->applicant_name,
                'plot_no'          => $plot['plot_no'] ?? $duplex->plot_no,
                'location'         => $duplex->street_name,
                'lga'              => $duplex->lga,
                'district'         => $duplex->district,
                'gender'           => $meta['gender'] ?? 'Male',
                'original_file_no' => $inputs[0],
            ]);
        }

        $entries = [];
        for ($i = 0; $i < $quantity; $i++) {
            $plot = $plots[$i] ?? [];
            $entries[] = [
                'plotNo'    => $plot['plot_no'] ?? $duplex->plot_no,
                'location'  => $duplex->street_name,
                'lga'       => $duplex->lga,
                'district'  => $duplex->district,
                // The holder may differ per plot — subdivided plots usually go to
                // different people, so the stage captured a name per child.
                'file_name' => $plot['holder'] ?? ($duplex->file_title ?: $duplex->applicant_name),
                'phone_no'  => $duplex->phone,
                'address'   => $duplex->address,
                // Only the first child can claim the stage's tracking id; the batch path
                // mints a fresh unique one for every entry that leaves this empty.
                'tracking_id' => $i === 0 ? $stage->tracking_id : null,
            ];
        }

        return $this->callBatch($base + [
            'batch_mode'       => true,
            'batch_quantity'   => $quantity,
            'year'             => (int) date('Y'),
            'serial_start'     => $this->nextSerial($landUse, (int) date('Y')),
            'file_name'        => $duplex->file_title ?: $duplex->applicant_name,
            'location_entries' => $entries,
        ]);
    }

    /**
     * Change of Purpose is a rename in place, one file at a time, so each selected
     * child is its own call. The engine keeps the prop_id (same parcel) and writes
     * the "old -> new" PRA row that anchors the timeline.
     */
    protected function commissionChangeOfPurpose(
        DuplexParcelUpdate $duplex,
        DuplexParcelUpdateStage $stage,
        array $inputs,
        ?int $appId,
        array $meta
    ): array {
        $payload    = (array) ($stage->payload ?? []);
        $newLandUse = strtoupper((string) ($payload['new_land_use'] ?? ''));

        if ($newLandUse === '') {
            throw new \RuntimeException("Stage {$stage->rank} (Change of Purpose) has no new land use.");
        }

        // A Change of Purpose is a rename in place, so it always goes through the
        // strict single-file path however many files it covers — and that path will
        // not invent a tracking id.
        if (empty($stage->tracking_id)) {
            throw new \RuntimeException(
                "Stage {$stage->rank} (Change of Purpose) needs a Tracking ID before it can be "
                . 'commissioned. Add it to the stage, or create the grouping record for this file.'
            );
        }

        // Which of the incoming files this CoP applies to. The officer may have
        // subdivided into four and be changing the purpose of only two; the rest
        // pass through untouched and stay as they are.
        $selected = (array) ($payload['applies_to'] ?? []);
        $targets  = [];

        foreach ($inputs as $i => $fileNo) {
            $holdingForIndex = $payload['input_holdings'][$i] ?? null;
            if (empty($selected) || in_array($fileNo, $selected, true)
                || ($holdingForIndex && in_array($holdingForIndex, $selected, true))) {
                $targets[] = $fileNo;
            }
        }

        if (empty($targets)) {
            $targets = $inputs;
        }

        $results   = [];
        $untouched = array_values(array_diff($inputs, $targets));

        foreach ($targets as $fileNo) {
            $results = array_merge($results, $this->callSingle([
                'application_type'          => 'change_of_purpose',
                'file_option'               => 'normal',
                'land_use'                  => $newLandUse,
                'original_file_no'          => $fileNo,
                'change_of_purpose_app_id'  => $appId,
                'tracking_id'               => $stage->tracking_id,
                'file_name'                 => $duplex->file_title ?: $duplex->applicant_name,
                'plot_no'                   => $duplex->plot_no,
                'location'                  => $duplex->street_name,
                'lga'                       => $duplex->lga,
                'district'                  => $duplex->district,
                'customer_type'             => $meta['customer_type'] ?? 'Individual',
                'gender'                    => $meta['gender'] ?? 'Male',
                'commissioned_by'           => $meta['commissioned_by'] ?? null,
                'commission_date'           => $meta['commission_date'] ?? now()->toDateString(),
                'commission_time'           => $meta['commission_time'] ?? now()->format('H:i'),
            ]));
        }

        // Files this stage did not touch still belong to the duplex and must carry
        // forward, or a later stage would silently lose them.
        return array_merge($results, $untouched);
    }

    protected function callBatch(array $payload): array
    {
        $response = app(MlsFileNoController::class)->generateBatch($this->request($payload));
        $data = json_decode($response->getContent(), true) ?: [];

        if (empty($data['success'])) {
            throw new \RuntimeException($data['message'] ?? 'Batch commissioning failed.');
        }

        return array_values((array) ($data['files'] ?? []));
    }

    protected function callSingle(array $payload): array
    {
        $response = app(MlsFileNoController::class)->generateMlsFileNumber($this->request($payload));
        $data = json_decode($response->getContent(), true) ?: [];

        if (empty($data['success'])) {
            throw new \RuntimeException($data['message'] ?? 'Commissioning failed.');
        }

        $fileNo = $data['data']['file_number'] ?? null;

        return $fileNo ? [$fileNo] : [];
    }

    /** An internal request carrying the caller's session, so Auth::id() still resolves. */
    protected function request(array $payload): Request
    {
        $request = Request::create('/internal/duplex-commission', 'POST', $payload);
        $request->setUserResolver(fn () => Auth::user());

        return $request;
    }

    /**
     * A stage's land use is the new purpose for a Change of Purpose, and otherwise
     * whatever the incoming file already carries.
     */
    protected function landUseFor(DuplexParcelUpdate $duplex, DuplexParcelUpdateStage $stage, array $inputs): string
    {
        $payload = (array) ($stage->payload ?? []);

        if (!empty($payload['new_land_use'])) {
            return strtoupper((string) $payload['new_land_use']);
        }

        $parts = explode('-', (string) ($inputs[0] ?? ''));

        // CON-RES-1984-248 keeps its two-part prefix; RES-1994-762 keeps one.
        if (count($parts) >= 3 && !is_numeric($parts[1])) {
            return $parts[0] . '-' . $parts[1];
        }

        return $parts[0] ?: (string) $duplex->land_use;
    }

    /**
     * Starting serial for a batch. generateBatch skips any serial already taken, so
     * this only has to be a sane floor rather than a guaranteed-free number.
     */
    protected function nextSerial(string $landUse, int $year): int
    {
        $max = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('land_use', $landUse)
            ->where('year', $year)
            ->max('serial_number');

        return ((int) $max) + 1;
    }
}
