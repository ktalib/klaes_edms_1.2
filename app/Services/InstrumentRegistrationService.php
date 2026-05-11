<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Exception;

class InstrumentRegistrationService
{
    /**
     * Generate the next available registration number ensuring atomic uniqueness.
     * Uses Base-300 rollover logic (Serial -> Page -> Volume).
     *
     * @param string $instrumentType
     * @param bool $isAutoReg If true, returns 0/0/0 (e.g., for some ST fragmentations)
     * @return array Contains 'volume', 'page', 'serial', 'formatted'
     * @throws Exception
     */
    public function getRegistrationNumber(string $instrumentType, bool $isAutoReg = false, array $instrumentData = []): array
    {
        if ($isAutoReg) {
            return [
                'volume' => 0,
                'page' => 0,
                'serial' => 0,
                'formatted' => '0/0/0' // Special case as per requirement
            ];
        }

        // Resolve Vault Name (Shared Vault Logic)
        $vaultName = $this->resolveVaultName($instrumentType, $instrumentData['op_type'] ?? null);

        return DB::transaction(function () use ($vaultName, $instrumentType) {
            // Atomic Locking: Lock the vault row for this instrument type (or shared vault)
            $vault = DB::connection('sqlsrv')->table('instrument_number_vaults')
                ->where('instrument_type', $vaultName)
                ->lockForUpdate()
                ->first();

            if (!$vault) {
                throw new \Exception("The instrument type [{$vaultName}] is not found in the 'Manage Instrument Types for Vault Registration'. Please configure the vault first.");
            }

            // User requirement: if values are 0/0/1 or NULL, starting point is 1/1/1
            $currentVolume = (int) ($vault->current_volume ?? 1);
            $currentPage = (int) ($vault->current_page ?? 0);
            $currentSerial = (int) ($vault->current_serial ?? 0);

            // If it's a fresh or corrupted vault, ensure we start at 1/1/1
            if ($currentVolume < 1)
                $currentVolume = 1;

            $volume = $currentVolume;
            $serial = $currentSerial + 1;
            $page = $serial;

            // Rollover Logic: Base-300
            if ($serial > 300) {
                $serial = 1;
                $page = 1;
                $volume++;
            }

            // Update the vault
            DB::connection('sqlsrv')->table('instrument_number_vaults')
                ->where('instrument_type', $vaultName)
                ->update([
                    'current_volume' => $volume,
                    'current_page' => $page,
                    'current_serial' => $serial,
                    'updated_at' => now()
                ]);

            $formatted = "{$serial}/{$page}/{$volume}";

            Log::info("Vault [{$vaultName}] (requested as {$instrumentType}) advanced to {$formatted} by User " . Auth::id());

            return [
                'volume' => $volume,
                'page' => $page,
                'serial' => $serial,
                'formatted' => $formatted
            ];
        });
    }

    /**
     * Persist a new instrument registration to the deed_registrations table.
     *
     * @param array $data
     * @param array|null $preGeneratedNumber Optional pre-generated number data
     * @return int The ID of the new record
     */
    public function registerInstrument(array $data, ?array $preGeneratedNumber = null)
    {
        // Extract numbering if passed, or generate it if 'generate_number' is true
        // Note: It's safer to generate number INSIDE the same transaction that inserts the record
        // to minimize gaps if insert fails.

        return DB::transaction(function () use ($data, $preGeneratedNumber) {
            $instrumentType = $data['instrument_type'];
            $isAutoReg = $data['is_auto_reg'] ?? false;

            // Generate Number OR Use Pre-generated
            if ($preGeneratedNumber) {
                $numberData = $preGeneratedNumber;
                 Log::info("Using pre-generated number for {$data['fileno']}: {$numberData['formatted']}");
            } else {
                 $numberData = $this->getRegistrationNumber($instrumentType, $isAutoReg, $data);
            }

            $insertData = [
                'instrument_capture_id' => $data['instrument_capture_id'] ?? null,
                'fileno' => $data['fileno'],
                'parent_fileno' => $data['parent_fileno'] ?? null,
                'instrument_type' => $instrumentType,
                'registration_number' => $numberData['formatted'],
                'volume_no' => $numberData['volume'],
                'page_no' => $numberData['page'],
                'serial_no' => $numberData['serial'],
                'instrument_date' => $data['instrument_date'] ?? date('Y-m-d'),
                'deeds_date' => $data['deeds_date'] ?? ($data['instrument_date'] ?? date('Y-m-d')),
                'deeds_time' => $data['deeds_time'] ?? date('g:i A'), // Standard 12-hour AM/PM format
                'grantor' => $data['grantor'] ?? null,
                'grantee' => $data['grantee'] ?? null,
                'lga' => $data['lga'] ?? null,
                'district' => $data['district'] ?? null,
                'plot_number' => $data['plot_number'] ?? null,
                'size' => $data['size'] ?? null,
                'property_description' => $data['property_description'] ?? null,
                'status' => 'registered',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $propId = $data['prop_id'] ?? null;
            if (($propId === null || $propId === '') && !empty($data['instrument_capture_id'])) {
                $propId = DB::connection('sqlsrv')
                    ->table('instrument_capture')
                    ->where('id', $data['instrument_capture_id'])
                    ->value('prop_id');
            }

            if (Schema::connection('sqlsrv')->hasColumn('deed_registrations', 'prop_id')) {
                $insertData['prop_id'] = $propId !== '' ? $propId : null;
            }

            if (Schema::connection('sqlsrv')->hasColumn('deed_registrations', 'batch_session_id')) {
                $insertData['batch_session_id'] = $data['batch_session_id'] ?? null;
            }

            // If there's extra data, json_encode it
            if (isset($data['extra_data'])) {
                $insertData['data'] = json_encode($data['extra_data']);
            }

            $id = DB::connection('sqlsrv')->table('deed_registrations')->insertGetId($insertData);

            Log::info("Instrument Registered: {$data['fileno']} as {$numberData['formatted']} (ID: $id)");

            return $id;
        });
    }

    /**
     * Initialize/Seed a vault if it doesn't exist (Helper)
     */
    public function initializeVault(string $instrumentType, int $vol = 1, int $page = 1, int $serial = 0, ?string $opType = null)
    {
        $vaultName = $this->resolveVaultName($instrumentType, $opType);

        $exists = DB::connection('sqlsrv')->table('instrument_number_vaults')->where('instrument_type', $vaultName)->exists();
        if (!$exists) {
            DB::connection('sqlsrv')->table('instrument_number_vaults')->insert([
                'instrument_type' => $vaultName,
                'current_volume' => $vol,
                'current_page' => $page,
                'current_serial' => $serial,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Preview the next number without incrementing (for UI display only)
     * @return array
     */
    public function previewNextNumber(string $instrumentType, ?string $opType = null): array
    {
        Log::info("DEBUG: resolving vault for type [{$instrumentType}] with op_type [{$opType}]");
        $vaultName = $this->resolveVaultName($instrumentType, $opType);
        Log::info("DEBUG: resolved vault name: [{$vaultName}]");

        $vault = DB::connection('sqlsrv')->table('instrument_number_vaults')
            ->where('instrument_type', $vaultName)
            ->first();

        if (!$vault) {
            throw new \Exception("The instrument type [{$vaultName}] is not found in the 'Manage Instrument Types for Vault Registration'. Please configure the vault first.");
        }

        $currentVolume = (int) ($vault->current_volume ?? 1);
        $currentSerial = (int) ($vault->current_serial ?? 0);

        if ($currentVolume < 1)
            $currentVolume = 1;

        $volume = $currentVolume;
        $serial = $currentSerial + 1;
        $page = $serial; // Enforce Serial == Page

        // Rollover Logic Preview
        if ($serial > 300) {
            $serial = 1;
            $page = 1;
            $volume++;
        }

        return [
            'volume' => $volume,
            'page' => $page,
            'serial' => $serial,
            'formatted' => "{$serial}/{$page}/{$volume}",
            'deeds_date' => date('d F Y'), // e.g., 12 February 2026
            'deeds_time' => date('g:i A'), // e.g., 8:22 PM
        ];
    }
    /**
     * Automatically register ST Fragmentation for approved mother applications.
     * Migrated from Controller to Service.
     */
    public function autoRegisterSTFragmentation()
    {
        try {
            // 1. Fetch existing registrations from SQL Server (primary connection)
            $existingFilenos = DB::connection('sqlsrv')->table('deed_registrations')
                ->where('instrument_type', 'ST Fragmentation')
                ->where('status', 'registered')
                ->pluck('fileno')
                ->toArray();

            // 2. Query Mother Applications from SQL Server, excluding existing ones
            $query = DB::connection('sqlsrv')->table('mother_applications as m')
                ->where('m.planning_recommendation_status', 'Approved')
                ->where('m.application_status', 'Approved');

            if (!empty($existingFilenos)) {
                $query->whereNotIn('m.fileno', $existingFilenos);
            }

            $approvedMotherApplications = $query->select(
                'm.id',
                'm.fileno',
                'm.np_fileno',
                'm.applicant_title',
                'm.first_name',
                'm.middle_name',
                'm.surname',
                'm.corporate_name',
                'm.rc_number',
                'm.multiple_owners_names',
                'm.owner_fullname as mother_applicant',
                'm.property_lga as lga',
                'm.property_district as district',
                'm.plot_size as size',
                'm.property_plot_no as plotNumber',
                'm.property_house_no',
                'm.property_street_name'
            )
                ->get();

            $registeredCount = 0;

            foreach ($approvedMotherApplications as $motherApp) {
                $stmReference = $this->generateSTMReference();

                // Build mother applicant name
                $motherApplicantParts = [];
                if (!empty($motherApp->applicant_title))
                    $motherApplicantParts[] = $motherApp->applicant_title;
                if (!empty($motherApp->first_name))
                    $motherApplicantParts[] = $motherApp->first_name;
                if (!empty($motherApp->middle_name))
                    $motherApplicantParts[] = $motherApp->middle_name;
                if (!empty($motherApp->surname))
                    $motherApplicantParts[] = $motherApp->surname;
                if (!empty($motherApp->corporate_name))
                    $motherApplicantParts[] = $motherApp->corporate_name;
                if (!empty($motherApp->rc_number))
                    $motherApplicantParts[] = $motherApp->rc_number;
                if (!empty($motherApp->multiple_owners_names))
                    $motherApplicantParts[] = $motherApp->multiple_owners_names;
                $motherApplicantName = implode(' ', $motherApplicantParts) ?: ($motherApp->mother_applicant ?? 'N/A');

                // Build property description
                $propertyParts = [];
                if (!empty($motherApp->property_house_no))
                    $propertyParts[] = 'House No: ' . $motherApp->property_house_no;
                if (!empty($motherApp->plotNumber))
                    $propertyParts[] = 'Plot No: ' . $motherApp->plotNumber;
                if (!empty($motherApp->property_street_name))
                    $propertyParts[] = $motherApp->property_street_name;
                if (!empty($motherApp->district))
                    $propertyParts[] = $motherApp->district;
                if (!empty($motherApp->lga))
                    $propertyParts[] = $motherApp->lga;
                $propertyDescription = implode(', ', $propertyParts) ?: 'Property details not available';

                // Use the registerInstrument method to ensure consistent logging and table usage
                // We pass 'is_auto_reg' => true to get the 0/0/0 numbering
                $this->registerInstrument([
                    'fileno' => $motherApp->fileno, // Using fileno as primary identifier
                    'parent_fileno' => $motherApp->fileno, // Self-referential or null? Logic used motherApp->fileno as parent
                    'instrument_type' => 'ST Fragmentation',
                    'is_auto_reg' => true,
                    'grantor' => 'Kano State Government',
                    'grantee' => $motherApplicantName,
                    'lga' => $motherApp->lga,
                    'district' => $motherApp->district,
                    'plot_number' => $motherApp->plotNumber,
                    'size' => $motherApp->size,
                    'property_description' => $propertyDescription,
                    'extra_data' => [
                        'stm_ref' => $stmReference,
                        'np_fileno' => $motherApp->np_fileno
                    ]
                ]);

                $registeredCount++;
            }

            if ($registeredCount > 0) {
                Log::info('Auto-registered ST Fragmentation records', ['count' => $registeredCount]);
            }

            // Trigger SubApps checks as well
            $this->autoRegisterSTSubApps();

        } catch (\Exception $e) {
            Log::error('Error auto-registering ST Fragmentation', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Automatically register ST Fragmentation for approved sub-applications.
     */
    public function autoRegisterSTSubApps()
    {
        try {
            // 1. Fetch existing registrations from SQL Server (primary connection)
            // We can reuse the same list if we want, but let's be safe and fetch fresh or pass it
            $existingFilenos = DB::connection('sqlsrv')->table('deed_registrations')
                ->where('instrument_type', 'ST Fragmentation')
                ->where('status', 'registered')
                ->pluck('fileno') // SubApps also use fileno column
                ->toArray();

            // 2. Query SubApplications from SQL Server
            // 2. Query SubApplications from SQL Server
            $query = DB::connection('sqlsrv')->table('subapplications as s')
                ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                ->where('s.application_status', 'Approved')
                ->where('s.is_primary_the_owner', 1) // Only auto-register if Primary Owner Retains
                ->where(function ($q) {
                    $q->where('s.planning_recommendation_status', 'Approved')
                        ->orWhere(function ($subQ) {
                            $subQ->where('m.planning_recommendation_status', 'Approved')
                                ->where('s.is_sua_unit', 0);
                        });
                });

            if (!empty($existingFilenos)) {
                $query->whereNotIn('s.fileno', $existingFilenos);
            }

            $approvedSubApps = $query->select(
                's.id',
                's.fileno',
                's.created_by',
                'm.property_lga as lga',
                'm.property_district as district',
                'm.property_plot_no as plotNumber',
                'm.plot_size as size',
                'm.property_house_no',
                'm.property_street_name',
                'm.fileno as mother_fileno',
                // Fetch Mother Applicant Details for Grantee Name
                'm.applicant_title',
                'm.first_name',
                'm.middle_name',
                'm.surname',
                'm.corporate_name',
                'm.rc_number',
                'm.multiple_owners_names'
            )
                ->get();

            $registeredCount = 0;

            foreach ($approvedSubApps as $subApp) {
                $stmReference = $this->generateSTMReference();

                // Build applicant name using MOTHER details (since is_primary_the_owner = 1)
                $parts = [];
                if (!empty($subApp->applicant_title))
                    $parts[] = $subApp->applicant_title;
                if (!empty($subApp->first_name))
                    $parts[] = $subApp->first_name;
                if (!empty($subApp->middle_name))
                    $parts[] = $subApp->middle_name;
                if (!empty($subApp->surname))
                    $parts[] = $subApp->surname;
                if (!empty($subApp->corporate_name))
                    $parts[] = $subApp->corporate_name;
                if (!empty($subApp->rc_number))
                    $parts[] = $subApp->rc_number;

                $granteeName = implode(' ', $parts) ?: ($subApp->multiple_owners_names ?? 'N/A');

                // Build property description
                $propertyParts = [];
                if (!empty($subApp->property_house_no))
                    $propertyParts[] = 'House No: ' . $subApp->property_house_no;
                if (!empty($subApp->plotNumber))
                    $propertyParts[] = 'Plot No: ' . $subApp->plotNumber;
                if (!empty($subApp->property_street_name))
                    $propertyParts[] = $subApp->property_street_name;
                if (!empty($subApp->district))
                    $propertyParts[] = $subApp->district;
                if (!empty($subApp->lga))
                    $propertyParts[] = $subApp->lga;
                $propertyDescription = implode(', ', $propertyParts) ?: 'Property details not available';

                $this->registerInstrument([
                    'fileno' => $subApp->fileno,
                    'parent_fileno' => $subApp->mother_fileno,
                    'instrument_type' => 'ST Fragmentation',
                    'is_auto_reg' => true,
                    'grantor' => 'Kano State Government',
                    'grantee' => $granteeName,
                    'lga' => $subApp->lga,
                    'district' => $subApp->district,
                    'plot_number' => $subApp->plotNumber,
                    'size' => $subApp->size,
                    'property_description' => $propertyDescription,
                    'created_by' => $subApp->created_by,
                    'extra_data' => [
                        'stm_ref' => $stmReference,
                        'subapp_id' => $subApp->id
                    ]
                ]);

                $registeredCount++;
            }

            if ($registeredCount > 0) {
                Log::info('Auto-registered ST Fragmentation records for SubApps (Retention)', ['count' => $registeredCount]);
            }
        } catch (\Exception $e) {
            Log::error('Error auto-registering ST SubApps', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Generate a unique STM reference.
     */
    private function generateSTMReference()
    {
        $prefix = 'STM';
        $year = date('Y');
        $random = mt_rand(1000, 9999);
        return "{$prefix}/{$year}/{$random}";
    }

    public function resolveVaultName(string $instrumentType, ?string $opType = null): string
    {
        // Normalize for comparison
        $normalized = trim($instrumentType);

        // Occupancy Permit (OP) special handling: use sub-type vault if provided
        if (
            stripos($normalized, 'Occupancy Permit') !== false ||
            $normalized === 'OP'
        ) {
            return $opType ?? 'Occupancy Permit (OP)';
        }

        // Shared Vault Mapping
        if (
            stripos($normalized, 'Deed of Surrender') !== false ||
            stripos($normalized, 'Power of Attorney') !== false
        ) {
            // Both share the 'Power of Attorney' vault
            return 'Power of Attorney';
        }

        if (
            stripos($normalized, 'Regular CofO') !== false ||
            stripos($normalized, 'Certificate of Occupancy') !== false
        ) {
            return 'Certificate of Occupancy';
        }

        if (
            stripos($normalized, 'Deed of Gift') !== false ||
            stripos($normalized, 'Deed of Assignment') !== false
        ) {
            return 'Deed of Assignment';
        }


        if (
            stripos($normalized, 'Deed of Mortgage') !== false ||
            stripos($normalized, 'Tripartite Mortgage') !== false
        ) {
            return 'Deed of Mortgage';
        }

        if (
            stripos($normalized, 'Sub Lease') !== false
        ) {
            return 'Sub Lease';
        }

        if (
            stripos($normalized, 'Occupancy Permit') !== false ||
            $normalized === 'OP'
        ) {
            return 'Occupancy Permit (OP)';
        }

        if (
            stripos($normalized, 'Devolution Order') !== false
        ) {
            return 'Devolution Order';
        }

        if (
            stripos($normalized, 'ST Assignment') !== false
        ) {
            return 'ST Assignment (Transfer of Title)';
        }

        if (
            stripos($normalized, 'ST Fragmentation') !== false
        ) {
            return 'ST Fragmentation';
        }

        if (
            stripos($normalized, 'Sectional Titling CofO') !== false
        ) {
            return 'Sectional Titling CofO';
        }

        // Default: 1-to-1 mapping
        return $instrumentType;
    }
}
