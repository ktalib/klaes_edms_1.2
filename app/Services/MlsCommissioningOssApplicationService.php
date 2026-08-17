<?php

namespace App\Services;

use App\Models\MlsFileNo;
use App\Support\OssOpCommissionFilter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors MLS File Number Generator commissions into the OSS no-change list.
 *
 * The change-of-name list is deliberately isolated by SYSTEM_SOURCE. Never update
 * or reclassify one of those rows, even if it happens to carry the same file number.
 */
class MlsCommissioningOssApplicationService
{
    public const SYSTEM_SOURCE = 'MLS_FILE_NUMBER_GENERATOR';
    public const CHANGE_OF_NAME_SOURCE = 'OSSOPCHANGEOFNAME';

    private ?array $ossColumns = null;

    /**
     * @param MlsFileNo|array<string,mixed>|object $source
     * @return array{action:string,id:?int,file_number:string}
     */
    public function sync($source): array
    {
        $row = $source instanceof MlsFileNo ? $source->getAttributes() : (array) $source;
        $fileNumber = trim((string) ($row['full_file_number'] ?? $row['file_number'] ?? ''));

        if ($fileNumber === '') {
            throw new \InvalidArgumentException('An MLS full file number is required for the OSS mirror.');
        }

        if (strtoupper(trim((string) ($row['system_sub_type'] ?? ''))) === OssOpCommissionFilter::OSS) {
            return $this->result('skipped_oss', null, $fileNumber);
        }

        $db = DB::connection('sqlsrv');
        $existingRows = $db->table('oss_applications')
            ->where('file_no', $fileNumber)
            ->orderByDesc('id')
            ->get();

        // A Change-of-Name application is authoritative for this file. In particular,
        // do not rewrite its source and accidentally move it onto the no-change page.
        if ($existingRows->contains(function ($existing) {
            return $this->isActive($existing)
                && strtoupper(trim((string) ($existing->system_source ?? ''))) === self::CHANGE_OF_NAME_SOURCE;
        })) {
            return $this->result('skipped_change_of_name', null, $fileNumber);
        }

        $existing = $existingRows->first(fn ($candidate) => $this->isActive($candidate));
        $payload = $this->payload($row, $fileNumber);

        if ($existing) {
            // Respect data entered through OSS. Only fill fields that are currently blank.
            $changes = [];
            foreach ($payload as $column => $value) {
                if (in_array($column, ['created_at', 'system_source', 'status', 'remarks'], true)) {
                    continue;
                }

                $current = $existing->{$column} ?? null;
                if (($current === null || trim((string) $current) === '') && $value !== null && $value !== '') {
                    $changes[$column] = $value;
                }
            }

            if (!$changes) {
                return $this->result('unchanged', (int) $existing->id, $fileNumber);
            }

            $changes['updated_at'] = now();
            $db->table('oss_applications')->where('id', $existing->id)->update($changes);
            $this->audit('UPDATED', (int) $existing->id, (array) $existing, $changes, $fileNumber);

            return $this->result('updated', (int) $existing->id, $fileNumber);
        }

        $id = (int) $db->table('oss_applications')->insertGetId($payload);
        $this->audit('CREATED', $id, null, $payload, $fileNumber);

        return $this->result('created', $id, $fileNumber);
    }

    /** @param array<string,mixed> $row */
    private function payload(array $row, string $fileNumber): array
    {
        $commissionedAt = $row['commissioning_date'] ?? $row['created_at'] ?? now();
        try {
            $commissionedAt = Carbon::parse($commissionedAt);
        } catch (\Throwable $e) {
            $commissionedAt = now();
        }

        $payload = [
            'application_type' => $this->resolveApplicationType($row['land_use'] ?? null, $fileNumber),
            'applicant_name' => $this->nullable($row['file_name'] ?? null),
            'file_no' => $fileNumber,
            'plot_no' => $this->nullable($row['plot_no'] ?? null),
            'plan_no' => $this->nullable($row['tp_no'] ?? null),
            'location' => $this->nullable($row['location'] ?? null),
            'district' => $this->nullable($row['district'] ?? null),
            'lga' => $this->nullable($row['lga'] ?? null),
            'land_use' => $this->nullable($row['land_use'] ?? null),
            'status' => 'approved',
            'remarks' => 'Auto-created from MLPP File Number Generator commissioning.',
            'system_source' => self::SYSTEM_SOURCE,
            'is_deleted' => 0,
            'created_at' => $commissionedAt,
            'updated_at' => $commissionedAt,
        ];

        $columns = $this->ossColumns();

        return array_filter($payload, static function ($value, $column) use ($columns) {
            return isset($columns[strtolower((string) $column)]);
        }, ARRAY_FILTER_USE_BOTH);
    }

    public function resolveApplicationType($landUse, string $fileNumber = ''): string
    {
        $value = strtoupper(trim((string) $landUse) . ' ' . trim($fileNumber));

        if (preg_match('/\b(AGR|AGRICULTURAL|AGRICULTURE)\b/', $value)) {
            return 'agricultural';
        }
        if (preg_match('/\b(IND|INDUSTRIAL|INDUSTRY)\b/', $value)) {
            return 'industrial';
        }
        if (preg_match('/\b(COM|CON|COMMERCIAL|COMMERCIAL CONCESSION)\b/', $value)) {
            return 'commercial';
        }

        return 'residential';
    }

    /** @return array<string,string> */
    private function ossColumns(): array
    {
        if ($this->ossColumns !== null) {
            return $this->ossColumns;
        }

        $this->ossColumns = [];
        foreach (Schema::connection('sqlsrv')->getColumnListing('oss_applications') as $column) {
            $this->ossColumns[strtolower($column)] = $column;
        }

        return $this->ossColumns;
    }

    private function nullable($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function isActive(object $row): bool
    {
        return !isset($row->is_deleted) || $row->is_deleted === null || (int) $row->is_deleted === 0;
    }

    private function audit(string $action, int $id, ?array $old, array $new, string $fileNumber): void
    {
        try {
            app(AuditService::class)->logAction(
                $action,
                'LandsOneStopShopApplication',
                $id,
                $old,
                $new,
                "MLS commissioning mirror for {$fileNumber}"
            );
        } catch (\Throwable $e) {
            // The mirror is part of the commissioning transaction; audit storage is
            // best-effort so an unavailable audit table does not strand a valid file.
            Log::warning('Could not audit MLS-to-OSS application mirror', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function result(string $action, ?int $id, string $fileNumber): array
    {
        return ['action' => $action, 'id' => $id, 'file_number' => $fileNumber];
    }
}
