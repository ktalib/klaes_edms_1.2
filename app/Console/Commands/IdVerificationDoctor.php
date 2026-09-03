<?php

namespace App\Console\Commands;

use App\Services\Ocr\OcrException;
use App\Services\Ocr\OcrReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Why is (or is not) Online Legal Search ID name verification working here?
 *
 * Tesseract is an OS-level binary that the code cannot install, and the two ways
 * it usually fails on a Windows server are invisible from the application:
 * the web-server service account cannot see it on PATH, or TESSERACT_PATH never
 * made it into that server's .env (which is gitignored, so a code upload never
 * carries it). Both look identical to an applicant: every ID reads as unreadable.
 *
 * Run this ON the server, and ideally as the account the web server runs as.
 */
class IdVerificationDoctor extends Command
{
    protected $signature = 'ols:id-verification-doctor {--image= : Also OCR this image file and print what was read}';

    protected $description = 'Check why Online Legal Search ID name verification is or is not working on this machine';

    public function handle(): int
    {
        $this->info('ID name verification diagnostics — ' . config('app.env') . ' @ ' . gethostname());
        $this->line('OS: ' . PHP_OS_FAMILY . '   running as: ' . $this->currentUser());
        $this->newLine();

        $ok = true;

        // 1. The PHP package. Present after `composer require`, absent if vendor/
        //    was not redeployed with the code.
        $this->line('1. PHP package (thiagoalessio/tesseract_ocr)');
        if (class_exists(\thiagoalessio\TesseractOCR\TesseractOCR::class)) {
            $this->line('   <fg=green>installed</>');
        } else {
            $this->error('   → NOT INSTALLED. Run: composer require thiagoalessio/tesseract_ocr');
            $this->line('     If you deploy by uploading code, vendor/ must be uploaded too.');
            $ok = false;
        }

        // 2. Where we will look for the binary.
        $this->newLine();
        $this->line('2. Tesseract binary location (config/id_verification.php ← .env)');
        $configured = config('id_verification.ocr.binary');

        if ($configured) {
            $this->line('   TESSERACT_PATH : ' . $configured);
            if (is_file($configured)) {
                $this->line('   <fg=green>file exists</>');
            } else {
                $this->error('   → That path does not exist on this machine.');
                $ok = false;
            }
        } else {
            $this->line('   TESSERACT_PATH : <fg=yellow>not set</> — relying on PATH');
            $found = $this->whichTesseract();
            if ($found) {
                $this->line('   found on PATH  : ' . $found);
            } else {
                $this->error('   → tesseract is NOT on PATH for this account.');
                $this->line('     On Windows this is usually the web-server service account, not your login:');
                $this->line('     a PATH edit needs the IIS/Apache service RESTARTED before it is picked up.');
                $this->line('     The reliable fix is to set the absolute path in this server\'s .env:');
                // SINGLE quotes: dotenv reads a backslash inside double quotes as an
                // escape sequence and then refuses the whole file, which stops the
                // application booting rather than just breaking OCR.
                $this->line("       TESSERACT_PATH='C:\\Program Files\\Tesseract-OCR\\tesseract.exe'");
                $this->line('     Use SINGLE quotes - double quotes make dotenv reject the whole .env file.');
                $this->line('     .env is gitignored, so it is NOT copied by a code upload — edit it on the server.');
                $ok = false;
            }
        }

        // 3. Stale config cache: correct in .env, absent from what the app reads.
        $this->newLine();
        $this->line('3. Config cache');
        if (file_exists($this->laravel->getCachedConfigPath())) {
            $this->line('   <fg=yellow>config is cached</> — if you just edited .env, run: php artisan config:clear');
        } else {
            $this->line('   <fg=green>not cached</> — .env is read live');
        }

        // 3b. proc_open. The OCR package shells out, so a hardened php.ini that
        //     disables proc_open breaks OCR while everything else looks healthy —
        //     and the failure reads as "could not execute tesseract", which sends
        //     you hunting for a missing binary that is sitting right there.
        $this->newLine();
        $this->line('3b. Shell-out support (php.ini)');
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $blocked = array_values(array_intersect(['proc_open', 'proc_close', 'escapeshellarg'], $disabled));

        if (empty($blocked)) {
            $this->line('   <fg=green>proc_open available</> — PHP may run external commands');
        } else {
            $this->error('   → DISABLED in php.ini: ' . implode(', ', $blocked));
            $this->line('     OCR shells out to tesseract, so it cannot work until these are');
            $this->line('     removed from disable_functions in this server\'s php.ini.');
            $ok = false;
        }

        // 4. Can we actually run it? This is the check that catches a binary that
        //    exists but the service account may not execute.
        $this->newLine();
        $this->line('4. Running the binary');
        $reader = app(OcrReader::class);

        if ($reader->isAvailable()) {
            $this->line('   <fg=green>tesseract runs and reports a version</>');
        } else {
            $this->error('   → Could not execute tesseract.');
            $this->line('     Check that the web-server account has Read & Execute on the binary');
            $this->line('     and Read on the tessdata folder beside it.');
            $ok = false;
        }

        // 5. Private disk. ID images are written here; without it nothing is stored.
        $this->newLine();
        $this->line('5. Private storage disk (' . config('id_verification.uploads.disk') . ')');
        try {
            $disk = Storage::disk((string) config('id_verification.uploads.disk'));
            $probe = 'doctor-write-test-' . uniqid() . '.txt';
            $disk->put($probe, 'ok');
            $written = $disk->exists($probe);
            $disk->delete($probe);

            if ($written) {
                $this->line('   <fg=green>writable</> (' . storage_path('app/private') . ')');
            } else {
                $this->error('   → Write appeared to succeed but the file was not there.');
                $ok = false;
            }
        } catch (\Throwable $e) {
            $this->error('   → NOT WRITABLE: ' . $e->getMessage());
            $this->line('     Grant the web-server account Modify on storage/app/private.');
            $ok = false;
        }

        // 6. Thresholds actually in force, so a retuned band is visible here.
        $this->newLine();
        $this->line('6. Matching thresholds in force');
        $this->line('   verified at/above  : ' . config('id_verification.thresholds.verified') . '%');
        $this->line('   review   at/above  : ' . config('id_verification.thresholds.review') . '%');
        $this->line('   minimum name parts : ' . config('id_verification.min_matching_parts'));
        $this->line('   store raw OCR text : ' . (config('id_verification.store_raw_text') ? 'yes' : 'no'));

        // 7. Optional end-to-end read of a real file.
        if ($image = $this->option('image')) {
            $this->newLine();
            $this->line('7. Reading ' . $image);

            if (!is_readable($image)) {
                $this->error('   → Cannot read that file.');
                $ok = false;
            } else {
                try {
                    $text = trim($reader->text($image));
                    if ($text === '') {
                        $this->line('   <fg=yellow>OCR ran but found no text</> — the image is unreadable, not the engine.');
                    } else {
                        // Printed only because an operator explicitly asked for this
                        // file. Never logged, and never shown to an applicant.
                        $this->line('   <fg=green>read ' . strlen($text) . ' characters</>:');
                        $this->line('   ' . str_replace("\n", ' / ', mb_substr($text, 0, 300)));
                    }
                } catch (OcrException $e) {
                    $this->error('   → OCR failed: ' . $e->getMessage());
                    $ok = false;
                }
            }
        }

        $this->newLine();
        if ($ok) {
            $this->info('All checks passed. ID name verification should work on this machine.');

            return self::SUCCESS;
        }

        $this->error('One or more checks failed — every applicant will see "we could not read the uploaded identification" until they are fixed.');

        return self::FAILURE;
    }

    /** Locate tesseract on PATH, using the platform's own lookup tool. */
    private function whichTesseract(): ?string
    {
        $command = PHP_OS_FAMILY === 'Windows' ? 'where tesseract' : 'command -v tesseract';

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return null;
        }

        $out = '';
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                $out .= stream_get_contents($pipe);
                fclose($pipe);
            }
        }

        $code = proc_close($process);
        $first = trim(strtok($out, "\r\n") ?: '');

        return ($code === 0 && $first !== '') ? $first : null;
    }

    private function currentUser(): string
    {
        return get_current_user() ?: (getenv('USERNAME') ?: (getenv('USER') ?: 'unknown'));
    }
}
