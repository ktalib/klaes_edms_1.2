<?php

namespace App\Console\Commands;

use App\Models\ValuationReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill valuation_reports address component columns from the concatenated address.
 *
 * The create/edit modal (resources/views/valuation_reports/partials/modal.blade.php) bound its
 * House No. / Street / Town / L.G.A inputs to address-builder-only properties (addr_house_no,
 * addr_street, addr_district, addr_lga) and never mirrored them onto the persisted columns.
 * Reports saved before that fix therefore have empty property_no, street_name, town_city and
 * lga, which print as blank lines c), d), g) and h) on the valuation report template.
 *
 * The values are still recoverable: concatAddress() wrote them into `address` as
 *   "No. {house} {street}, {district}, {lga}, {state}"
 *
 * Parsing strategy: find the comma-separated token that matches a known LGA (StatLGAs) and read
 * outwards from it — the token before it is the town/district, everything before that is the
 * house number + street. This survives records with a missing state or an extra leading segment.
 * Rows where no known LGA appears are left untouched and reported as unparsed.
 *
 * Idempotent: only empty columns are written; a column already holding a value is never
 * overwritten.
 */
class BackfillValuationReportAddressParts extends Command
{
    protected $signature = 'valuation:backfill-address-parts
                            {--apply : Write the values. Without this flag the command only reports.}
                            {--with-street : Also recover property_no and street_name (legacy street text is free-form and splits unreliably).}
                            {--id= : Restrict to a single valuation report id.}';

    protected $description = 'Recover town_city and lga (optionally property_no/street_name) on valuation_reports from the address string';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $withStreet = (bool) $this->option('with-street');

        $columns = $withStreet
            ? ['property_no', 'street_name', 'town_city', 'lga']
            : ['town_city', 'lga'];

        $lgaNames = DB::connection('sqlsrv')->table('StatLGAs')
            ->pluck('LGAName')
            ->map(fn ($n) => strtoupper(trim((string) $n)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($lgaNames)) {
            $this->error('No rows in StatLGAs — cannot identify LGA tokens.');
            return self::FAILURE;
        }

        $query = ValuationReport::query()
            ->whereNotNull('address')
            ->where('address', '<>', '')
            ->where(function ($q) use ($columns) {
                foreach ($columns as $column) {
                    $q->orWhereNull($column)->orWhere($column, '');
                }
            });

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        $reports = $query->orderBy('id')->get();

        if ($reports->isEmpty()) {
            $this->info('Nothing to backfill.');
            return self::SUCCESS;
        }

        $this->info(($apply ? 'Applying' : 'DRY RUN — ') . " {$reports->count()} candidate report(s).");

        $updated = 0;
        $unparsed = [];
        $rows = [];

        foreach ($reports as $report) {
            $parts = $this->parse($report->address, $lgaNames);

            if ($parts === null) {
                $unparsed[] = "#{$report->id}  {$report->address}";
                continue;
            }

            // Only fill what is actually empty.
            $changes = [];
            foreach ($columns as $column) {
                if (trim((string) $report->{$column}) === '' && $parts[$column] !== '') {
                    $changes[$column] = $parts[$column];
                }
            }

            if (empty($changes)) {
                continue;
            }

            $row = [$report->id];
            foreach ($columns as $column) {
                $row[] = $changes[$column] ?? '·';
            }
            $rows[] = $row;

            if ($apply) {
                $report->forceFill($changes)->save();
            }

            $updated++;
        }

        if (!empty($rows)) {
            $this->table(array_merge(['id'], $columns), $rows);
        }

        if (!empty($unparsed)) {
            $this->warn(count($unparsed) . ' report(s) had no recognisable LGA in the address and were skipped:');
            foreach ($unparsed as $line) {
                $this->line('  ' . $line);
            }
        }

        $this->info(($apply ? 'Updated ' : 'Would update ') . "{$updated} report(s).");

        if (!$apply && $updated > 0) {
            $this->comment('Re-run with --apply to write the values.');
        }

        return self::SUCCESS;
    }

    /**
     * Split "No. 125 Sagagi Qtrs, Sabon Gari, Fagge, Kano" into its component columns.
     *
     * @param  array<int,string>  $lgaNames  Upper-cased known LGA names.
     * @return array{property_no:string,street_name:string,town_city:string,lga:string}|null
     */
    private function parse(?string $address, array $lgaNames): ?array
    {
        $tokens = array_values(array_filter(
            array_map(fn ($p) => trim($p), explode(',', (string) $address)),
            fn ($p) => $p !== ''
        ));

        if (count($tokens) < 2) {
            return null;
        }

        // Locate the LGA — scan from the right, since the state trails it.
        $lgaIndex = null;
        for ($i = count($tokens) - 1; $i >= 1; $i--) {
            if (in_array(strtoupper($tokens[$i]), $lgaNames, true)) {
                $lgaIndex = $i;
                break;
            }
        }

        if ($lgaIndex === null) {
            return null;
        }

        $lga = $tokens[$lgaIndex];
        $town = $lgaIndex >= 1 ? ($tokens[$lgaIndex - 1] ?? '') : '';

        // Everything left of the town is the house number + street.
        $streetPart = $lgaIndex >= 2 ? implode(', ', array_slice($tokens, 0, $lgaIndex - 1)) : '';

        // Legacy rows carry a trailing "NIL" placeholder and one or more "No." prefixes
        // (concatAddress() adds its own "No. " on top of whatever the typist entered).
        $streetPart = preg_replace('/\s*\bNIL\b\s*$/i', '', $streetPart);
        $streetPart = trim(preg_replace('/^(?:No\.?\s*)+/i', '', $streetPart));

        $houseNo = '';
        $street = $streetPart;

        // Only treat a leading token as the house/plot number when it actually looks like one
        // (123, 2A, 15/16, K51). Otherwise the whole thing is free-form street text and
        // splitting it just scatters words across two fields.
        if (preg_match('#^([A-Z]?\d+[A-Z]?(?:/\d+[A-Z]?)?)\s+(.*)$#i', $streetPart, $m)) {
            $houseNo = $m[1];
            $street = trim($m[2]);
        }

        return [
            'property_no' => $houseNo,
            'street_name' => $street,
            'town_city' => $town,
            'lga' => $lga,
        ];
    }
}
