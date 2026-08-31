<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ParcelUpdateNotificationService
{
    private const LAND_DEPARTMENT_ID = 3;

    /**
     * Land staff are MLPP in department 3; the other categories (MDC, MDCM) are not.
     *
     * This REPLACES a filter on the "Generate New FileNo (MLSFileNo)" role, which
     * matched the role id inside the comma-separated assign_role column. That rule
     * selected NOBODY — the role exists (id 10055) but no user in department 3 carries
     * it — so every parcel-update notification this service has ever raised was sent
     * to an empty list and silently logged as "0 users notified".
     */
    private const STAFF_CATEGORY = 'MLPP';

    private const MODULE = 'parcel_update';

    public function __construct(
        protected UserNotificationService $notifier

    ) {}


    
    /**
     * Notify Land (MLS) users that a new Parcel Update application was created.
     */
    public function notifyCreated(string $type, int|null $appId, string $fileNo, string $fileTitle, string $applicantName = ''): void
    {
        $label = $this->typeLabel($type);
        $title = "New {$label} Application Submitted";
        $body  = "A new {$label} application for file {$fileNo}" . ($fileTitle ? " ({$fileTitle})" : '') . " has been submitted and is pending review.";

        $this->dispatchToLandUsers($title, $body, [
            'type'           => $type,
            'app_id'         => $appId,
            'file_no'        => $fileNo,
            'file_title'     => $fileTitle,
            'applicant_name' => $applicantName,
            'event'          => 'created',
        ]);
    }

    /**
     * Notify Land (MLS) users that a Parcel Update application has been approved and is ready for commissioning.
     */
    public function notifyApproved(string $type, int|null $appId, string $fileNo, string $fileTitle, string $approvedBy = ''): void
    {
        $label = $this->typeLabel($type);
        $title = "{$label} Application Approved – Ready for Commissioning";
        $body  = "The {$label} application for file {$fileNo}" . ($fileTitle ? " ({$fileTitle})" : '') . " has been approved. Please proceed to commission the new file number.";

        $this->dispatchToLandUsers($title, $body, [
            'type'        => $type,
            'app_id'      => $appId,
            'file_no'     => $fileNo,
            'file_title'  => $fileTitle,
            'approved_by' => $approvedBy,
            'event'       => 'approved',
        ]);
    }

    /**
     * Notify Deeds users that a file number has been commissioned for a Parcel Update.
     */
    public function notifyCommissioned(string $type, int|null $appId, string $sourceFileNo, string $newFileNo, string $commissionedBy = ''): void
    {
        $label = $this->typeLabel($type);
        $title = "File Number Commissioned for {$label}";
        $body  = "File number {$newFileNo} has been commissioned for the {$label} of {$sourceFileNo}.";

        $this->dispatchToDeedsUsers($title, $body, [
            'type'             => $type,
            'app_id'           => $appId,
            'source_file_no'   => $sourceFileNo,
            'new_file_no'      => $newFileNo,
            'commissioned_by'  => $commissionedBy,
            'event'            => 'commissioned',
        ]);
    }

    // -------------------------------------------------------------------------

    /**
     * Everyone who should hear about a parcel update: Land, plus the administrators.
     *
     * Land is MLPP staff in department 3. Administrators are included whatever their
     * department — they carry the system, and a duplex sitting uncaptured is as much
     * their problem as Land's.
     *
     * One query, deduplicated by the OR: an administrator who is also MLPP in Land
     * appears once, and is notified once.
     */
    private function dispatchToLandUsers(string $title, string $body, array $data): void
    {
        try {
            $users = User::on('sqlsrv')
                ->where(function ($q) {
                    $q->where(function ($land) {
                        $land->where('staff_type_category', self::STAFF_CATEGORY)
                            ->where('department_id', self::LAND_DEPARTMENT_ID);
                    })->orWhere('is_admin', 1);
                })
                ->get(['id']);

            if ($users->isEmpty()) {
                // Worth a warning rather than a silent success: the previous rule
                // selected nobody for months and read as "notified" in the log.
                Log::warning('ParcelUpdateNotificationService: no Land or admin users matched', [
                    'department_id'       => self::LAND_DEPARTMENT_ID,
                    'staff_type_category' => self::STAFF_CATEGORY,
                    'event'               => $data['event'] ?? null,
                ]);
                return;
            }

            foreach ($users as $user) {
                $this->notifier->create(
                    $user->id,
                    'info',
                    $title,
                    $body,
                    $data,
                    ['module' => self::MODULE]
                );
            }

            Log::info('ParcelUpdateNotificationService: Land + admin users notified', [
                'count' => $users->count(),
                'event' => $data['event'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('ParcelUpdateNotificationService: Failed to notify Land users', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);
        }
    }

    private function dispatchToDeedsUsers(string $title, string $body, array $data): void
    {
        try {
            $deedsDept = Department::where('name', 'LIKE', '%Deed%')->first();

            if (!$deedsDept) {
                Log::warning('ParcelUpdateNotificationService: Deeds department not found');
                return;
            }

            $users = User::on('sqlsrv')
                ->where('department_id', $deedsDept->id)
                ->get(['id']);

            foreach ($users as $user) {
                $this->notifier->create(
                    $user->id,
                    'info',
                    $title,
                    $body,
                    $data,
                    ['module' => self::MODULE]
                );
            }

            Log::info('ParcelUpdateNotificationService: Deeds users notified', [
                'count' => $users->count(),
                'event' => $data['event'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('ParcelUpdateNotificationService: Failed to notify Deeds users', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);
        }
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'subdivision'       => 'Plot Subdivision',
            'separation'        => 'Plot Separation',
            'merger'            => 'Plot Merger',
            'extension'         => 'Plot Extension',
            'change_of_purpose' => 'Change of Purpose',
            'duplex'            => 'APU - Advance Parcel Update (Duplex)',
            default             => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
