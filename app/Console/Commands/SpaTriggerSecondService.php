<?php

namespace App\Console\Commands;

use App\Models\SpaNotice;
use App\Services\BetaSmsService;
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
        $cutoff = Carbon::today()->subDays(SpaNotice::SECOND_SERVE_AFTER_DAYS);

        // Find first-serve notices where: served_date <= 14 days ago AND no second
        // serve exists. Free-style notices carry no application id, so they are
        // matched on file number instead — matching on a NULL id never holds in SQL
        // and would re-issue their second serve on every run.
        $eligible = SpaNotice::where('notice_type', 'first')
            ->whereNotNull('served_date')
            ->where('served_date', '<=', $cutoff)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('spa_notices as sn2')
                      ->where('sn2.notice_type', 'second')
                      ->where(function ($q) {
                          $q->where(function ($q2) {
                              $q2->whereNotNull('spa_notices.spa_application_id')
                                 ->whereColumn('sn2.spa_application_id', 'spa_notices.spa_application_id');
                          })->orWhere(function ($q2) {
                              $q2->whereNull('spa_notices.spa_application_id')
                                 ->whereNull('sn2.spa_application_id')
                                 ->whereColumn('sn2.file_number', 'spa_notices.file_number');
                          });
                      });
            })
            ->with('application')
            ->get();

        if ($eligible->isEmpty()) {
            $this->info('No eligible first-serve records found.');
            return 0;
        }

        $sms    = app(BetaSmsService::class);
        $count  = 0;
        $failed = 0;

        foreach ($eligible as $first) {
            // Double-check no second serve exists for this application / file
            $alreadyExists = SpaNotice::where('notice_type', 'second')
                ->when($first->spa_application_id,
                    fn($q) => $q->where('spa_application_id', $first->spa_application_id),
                    fn($q) => $q->whereNull('spa_application_id')->where('file_number', $first->file_number))
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

                // Send SMS — wording is the Ministry's, shared with the manual path
                $smsSent = false;
                try {
                    $smsSent = $sms->send($first->phone, SpaNotice::smsBody('second'));
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
