<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Log;

class ParcelUpdateNotificationService
{
    private const LAND_DEPARTMENT_ID = 3;
    private const MLS_ROLE_NAME = 'Generate New FileNo (MLSFileNo)';
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

    private function dispatchToLandUsers(string $title, string $body, array $data): void
    {
        try {
            $roleIds = UserRole::where('name', self::MLS_ROLE_NAME)->pluck('id')->all();

            if (empty($roleIds)) {
                Log::warning('ParcelUpdateNotificationService: MLS role not found', ['role' => self::MLS_ROLE_NAME]);
                return;
            }

            // Build a LIKE pattern that matches any of the role IDs in the comma-separated assign_role field.
            $users = User::on('sqlsrv')
                ->where('department_id', self::LAND_DEPARTMENT_ID)
                ->where(function ($q) use ($roleIds) {
                    foreach ($roleIds as $rid) {
                        $q->orWhere('assign_role', 'LIKE', "%{$rid}%");
                    }
                })
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

            Log::info('ParcelUpdateNotificationService: Land users notified', [
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
            'duplex'            => 'Duplex Parcel Update',
            default             => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
