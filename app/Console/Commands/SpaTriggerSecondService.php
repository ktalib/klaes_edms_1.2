<?php

namespace App\Console\Commands;

use App\Models\SpaNotice;
use App\Services\EBulkSmsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpaTriggerSecondService extends Command
{
    protected $signature   = 'spa:trigger-second-service';
    protected $description = 'Auto-trigger second serve notices for SPAS first-serve records that are 14+ days old';

    public function handle(): int
    {
        $cutoff = Carbon::today()->subDays(14);

        // Find first-serve notices where: served_date <= 14 days ago AND no second serve exists
        $eligible = SpaNotice::where('notice_type', 'first')
            ->where('served_date', '<=', $cutoff)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('spa_notices as sn2')
                      ->whereColumn('sn2.spa_application_id', 'spa_notices.spa_application_id')
                      ->where('sn2.notice_type', 'second');
            })
            ->with('application')
            ->get();

        if ($eligible->isEmpty()) {
            $this->info('No eligible first-serve records found.');
            return 0;
        }

        $sms    = app(EBulkSmsService::class);
        $count  = 0;
        $failed = 0;

        foreach ($eligible as $first) {
            // Double-check no second serve exists for this application
            $alreadyExists = SpaNotice::where('spa_application_id', $first->spa_application_id)
                ->where('notice_type', 'second')
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            try {
                $second = SpaNotice::create([
                    'spa_application_id' => $first->spa_application_id,
                    'file_number'        => $first->file_number,
                    'notice_type'        => 'second',
                    'recipient_name'     => $first->recipient_name,
                    'phone'              => $first->phone,
                    'served_by'          => $first->served_by,
                    'served_date'        => now()->toDateString(),
                    'status'             => 'served',
                    'created_by'         => 'system:spa-auto',
                ]);

                // Send SMS
                $smsSent = false;
                try {
                    $fileRef = $first->file_number ? " (File No: {$first->file_number})" : '';
                    $message = "Dear {$first->recipient_name}, this is a Second Serve Notice from Kano State Ministry of Lands regarding your property{$fileRef}. You are required to comply immediately. KANGIS.";
                    $smsSent = $sms->send($first->phone, $message);
                } catch (\Throwable $e) {
                    Log::warning('SPAS auto-second-serve SMS failed', ['phone' => $first->phone, 'error' => $e->getMessage()]);
                }

                $second->update(['sms_sent' => $smsSent, 'sms_sent_at' => $smsSent ? now() : null]);

                $count++;
                $this->line("  ✓ Second serve created for App #{$first->spa_application_id} ({$first->file_number}) — SMS " . ($smsSent ? 'sent' : 'failed'));

            } catch (\Throwable $e) {
                $failed++;
                Log::error('SPAS auto-second-serve failed', ['first_id' => $first->id, 'error' => $e->getMessage()]);
                $this->error("  ✗ Failed for App #{$first->spa_application_id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Created: {$count}, Failed: {$failed}.");
        Log::info("spa:trigger-second-service — created: {$count}, failed: {$failed}");

        return 0;
    }
}
