<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Exception;

/**
 * The registration numbers a register skipped, and the guarded way to fill one.
 *
 * The Deeds register is a paper book that KLAES took over part-way through: a
 * vault does not start at 1/1/1, it starts wherever the manual workflow had
 * reached when digitization began (Deed of Assignment picks up at 121/121/73,
 * Deed of Mortgage at 89/89/22, Certificate of Occupancy at 266/266/173). What
 * counts as "missing" therefore cannot be measured from 1 - it is measured from
 * the first number that register ever issued in KLAES up to the number its
 * vault stands on now, and every value in between that no registration holds.
 *
 * The gaps themselves come from registrations that were deleted outright: the
 * paper book has a serial the database no longer accounts for. Capturing the
 * instrument again through the normal path would burn the NEXT number and leave
 * the hole permanently, so the capture screen offers the hole instead.
 *
 * Nothing here advances a vault. A backfill consumes a number the vault already
 * issued and passed over, so the vault's own position must not move.
 */
class MissingRegistrationParticularsService
{
    /**
     * Serials per volume under the Deeds rules, mirroring the base-300 rollover
     * in InstrumentRegistrationService::nextFromVault(). A volume that is not
     * the one the vault currently sits in is a closed book of 300 entries.
     */
    private const SERIALS_PER_VOLUME = 300;

    /**
     * Ceiling on the numbers listed for one volume. A volume that was barely
     * captured (two registrations in a book of 300) is honestly "298 missing",
     * but nobody picks from a list that long - the count still reports the truth.
     */
    private const MAX_LISTED_PER_VOLUME = 400;

    public function __construct(
        protected InstrumentRegistrationService $vaults
    ) {
    }

    /**
     * Every skipped number for the register the given instrument belongs to.
     *
     * $opType is what separates the two Occupancy Permit registers, exactly as
     * it does for allocation - see instrumentTypesFor().
     *
     * @return array{
     *   vault:string, configured:bool, supported:bool, reason?:string,
     *   origin?:array, current?:array, total?:int, volumes?:array
     * }
     */
    public function report(string $instrumentType, ?string $opType = null): array
    {
        $vaultName = $this->vaults->resolveVaultName($instrumentType, $opType);

        // The Land Registry numbers on its own rules - a serial that runs to
        // 9999 independently of the volume, which is not the per-volume book
        // this survey walks. Backfilling one would need its own arithmetic, so
        // it is declined rather than answered wrongly.
        if ($vaultName === config('land_registration.instrument_type')) {
            return [
                'vault' => $vaultName,
                'configured' => true,
                'supported' => false,
                'reason' => 'The Land Registration register numbers on separate rules and cannot be backfilled from here.',
            ];
        }

        $vault = DB::connection('sqlsrv')->table('instrument_number_vaults')
            ->where('instrument_type', $vaultName)
            ->first();

        if (!$vault) {
            return ['vault' => $vaultName, 'configured' => false, 'supported' => false];
        }

        $survey = $this->survey($vaultName, $opType, $vault);

        if ($survey === null) {
            return [
                'vault' => $vaultName,
                'configured' => true,
                'supported' => true,
                'total' => 0,
                'volumes' => [],
                'reason' => 'No registration has been recorded in volume ' . max(1, (int) $vault->current_volume)
                    . ' yet, so there is nothing to backfill.',
            ];
        }

        return [
            'vault' => $vaultName,
            'configured' => true,
            'supported' => true,
            'origin' => $survey['origin'],
            'current' => $survey['current'],
            'total' => $survey['total'],
            'volumes' => $survey['volumes'],
        ];
    }

    /**
     * Take one skipped number for a capture that is about to be written.
     *
     * Must be called inside the capture's own sqlsrv transaction: it holds the
     * vault row for the duration, which is the same lock ordinary allocation
     * takes, so two officers cannot leave with the same gap and a backfill
     * cannot interleave with a fresh allocation.
     *
     * @return array{volume:int, page:int, serial:int, formatted:string, backfilled:true}
     * @throws Exception when the number is not (or is no longer) a gap.
     */
    public function claim(string $instrumentType, ?string $opType, int $volume, int $serial): array
    {
        $vaultName = $this->vaults->resolveVaultName($instrumentType, $opType);

        // Same boundary report() draws, restated here so the write path never
        // depends on the reader having drawn it: the Land Registry's serial runs
        // independently of its volume, which this survey does not model.
        if ($vaultName === config('land_registration.instrument_type')) {
            throw new Exception('The Land Registration register numbers on separate rules and cannot be backfilled from here.');
        }

        $vault = DB::connection('sqlsrv')->table('instrument_number_vaults')
            ->where('instrument_type', $vaultName)
            ->lockForUpdate()
            ->first();

        if (!$vault) {
            throw new Exception("The instrument type [{$vaultName}] has no registration vault configured, so no missing number can be filled.");
        }

        // Re-surveyed under the lock rather than trusted from the form: the gap
        // the officer picked may have been taken while the form was open.
        $survey = $this->survey($vaultName, $opType, $vault);
        $stillMissing = $survey !== null
            && in_array($serial, $survey['missing'][$volume] ?? [], true);

        if (!$stillMissing) {
            throw new Exception(
                "Registration particulars {$serial}/{$serial}/{$volume} are not a missing number in the {$vaultName} register"
                . " (they may have been filled since this form was opened). Please re-open the missing-number list and pick again."
            );
        }

        // survey() reads deed_registrations, the register of record. A capture
        // row that never produced a registration would be invisible to it, so
        // the write path checks that table too before committing to a number.
        $heldByCapture = DB::connection('sqlsrv')->table('instrument_capture')
            ->whereIn('instrument_type', $this->instrumentTypesFor($vaultName, $opType))
            ->whereRaw('TRY_CONVERT(int, volume_no) = ?', [$volume])
            ->whereRaw('TRY_CONVERT(int, serial_no) = ?', [$serial])
            ->exists();

        if ($heldByCapture) {
            throw new Exception("Registration particulars {$serial}/{$serial}/{$volume} are already held by a captured instrument.");
        }

        return [
            'volume' => $volume,
            // Page has never differed from serial in this register - the paper
            // book writes the same value in both columns, and allocation sets
            // them from one another.
            'page' => $serial,
            'serial' => $serial,
            'formatted' => "{$serial}/{$serial}/{$volume}",
            'backfilled' => true,
        ];
    }

    /**
     * Walk one register: where it started, where it stands, and what it skipped.
     *
     * Returns null when the vault's current volume holds no registration at all
     * - there is then no book to walk, only a vault pointing at an empty page.
     *
     * @return null|array{origin:array, current:array, total:int, volumes:array, missing:array<int,int[]>}
     */
    private function survey(string $vaultName, ?string $opType, object $vault): ?array
    {
        $currentVolume = max(1, (int) $vault->current_volume);
        $currentSerial = (int) $vault->current_serial;

        $used = $this->usedNumbers($vaultName, $opType, $currentVolume);

        if (empty($used[$currentVolume])) {
            return null;
        }

        // The volumes this register owns: the unbroken run of books ending at
        // the one the vault sits in. Anything below a break belongs to a
        // different run - which is what tells the two Occupancy Permit
        // registers apart, since deed_registrations records both as
        // "Occupancy Permit (OP)" and only their volume ranges separate them.
        $block = [];
        for ($volume = $currentVolume; $volume >= 1; $volume--) {
            if (empty($used[$volume])) {
                break;
            }
            $block[] = $volume;
        }
        sort($block);

        $lowestVolume = $block[0];
        $originSerial = min(array_keys($used[$lowestVolume]));

        $missing = [];
        $volumes = [];
        $total = 0;

        foreach (array_reverse($block) as $volume) {
            // Where this book begins. The lowest volume begins at the first
            // number KLAES ever issued in it - everything before that belongs
            // to the manual workflow and was never this system's to fill.
            $from = $volume === $lowestVolume ? $originSerial : 1;
            $to = $volume === $currentVolume ? $currentSerial : self::SERIALS_PER_VOLUME;

            $gaps = [];
            for ($serial = $from; $serial <= $to; $serial++) {
                if (!isset($used[$volume][$serial])) {
                    $gaps[] = $serial;
                }
            }

            if (!$gaps) {
                continue;
            }

            $missing[$volume] = $gaps;
            $total += count($gaps);

            $listed = array_slice($gaps, 0, self::MAX_LISTED_PER_VOLUME);

            $volumes[] = [
                'volume' => $volume,
                'missing' => count($gaps),
                // How much of the book IS accounted for. A volume reading
                // "1 missing, 299 registered" is a deletion; one reading
                // "226 missing, 2 registered" was never captured in the first
                // place, and the officer can see which is which at a glance.
                'registered' => count($used[$volume]),
                'from' => $from,
                'to' => $to,
                'truncated' => count($gaps) > count($listed),
                'entries' => array_map(fn ($serial) => [
                    'serial' => $serial,
                    'page' => $serial,
                    'volume' => $volume,
                    'formatted' => "{$serial}/{$serial}/{$volume}",
                ], $listed),
            ];
        }

        return [
            'origin' => [
                'volume' => $lowestVolume,
                'serial' => $originSerial,
                'formatted' => "{$originSerial}/{$originSerial}/{$lowestVolume}",
            ],
            'current' => [
                'volume' => $currentVolume,
                'serial' => $currentSerial,
                'formatted' => "{$currentSerial}/{$currentSerial}/{$currentVolume}",
            ],
            'total' => $total,
            'volumes' => $volumes,
            'missing' => $missing,
        ];
    }

    /**
     * Every serial already spoken for, as [volume][serial] => true.
     *
     * Soft-deleted and non-'registered' rows count as used on purpose: the row
     * still carries the number, and a number this register has issued is never
     * handed out twice. Only a registration deleted outright leaves a hole, and
     * that hole is precisely what this class exists to find.
     */
    private function usedNumbers(string $vaultName, ?string $opType, int $currentVolume): array
    {
        $types = $this->instrumentTypesFor($vaultName, $opType);

        if (!$types) {
            return [];
        }

        $rows = DB::connection('sqlsrv')->table('deed_registrations')
            ->whereIn('instrument_type', $types)
            ->whereRaw('TRY_CONVERT(int, volume_no) BETWEEN 1 AND ?', [$currentVolume])
            ->selectRaw('TRY_CONVERT(int, volume_no) AS v, TRY_CONVERT(int, serial_no) AS s')
            ->get();

        $used = [];
        foreach ($rows as $row) {
            if ($row->s === null) {
                continue;
            }
            $used[(int) $row->v][(int) $row->s] = true;
        }

        return $used;
    }

    /**
     * Which stored instrument types feed a given vault.
     *
     * Derived by running every type present in deed_registrations back through
     * the allocator's own resolveVaultName(), never by a second copy of the
     * mapping rules - a shared vault (Deed of Assignment + Deed of Gift; Deed of
     * Surrender and Release + Power of Attorney; Deed of Mortgage + Tripartite
     * Mortgage) has to survey exactly the rows it allocates for, and a private
     * copy of that mapping is how the two drift apart.
     *
     * @return string[]
     */
    private function instrumentTypesFor(string $vaultName, ?string $opType): array
    {
        static $distinctTypes = null;

        if ($distinctTypes === null) {
            $distinctTypes = DB::connection('sqlsrv')->table('deed_registrations')
                ->whereNotNull('instrument_type')
                ->distinct()
                ->pluck('instrument_type')
                ->all();
        }

        $matched = [];
        foreach ($distinctTypes as $type) {
            if ($this->vaults->resolveVaultName($type, $opType) === $vaultName) {
                $matched[] = $type;
            }
        }

        return $matched;
    }
}
