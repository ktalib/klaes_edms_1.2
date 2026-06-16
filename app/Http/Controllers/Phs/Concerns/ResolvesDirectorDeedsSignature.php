<?php

namespace App\Http\Controllers\Phs\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resolves the Authorized Signatory (Director Deeds) signature for the certified
 * PHS search slip. Shared by the member-facing slip and the staff "Open Search"
 * re-print so both render an identical signature.
 *
 * Pulls the signature image of the Director Deeds in the Lands department
 * (department_id = 8) and returns it as an inline base64 data URI so it embeds
 * reliably when the slip is printed/saved as PDF.
 */
trait ResolvesDirectorDeedsSignature
{
    protected function directorDeedsSignature(): ?string
    {
        $path = User::where('department_id', 8)
            ->where('rank', 'Director Deeds')
            ->value('signature');

        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['data:', 'http://', 'https://'])) {
            return $path;
        }

        // Normalise to a Storage path (DB stores e.g. "public/signing_officer_signatures/xxx.png").
        $candidates = [$path];
        if (Str::startsWith($path, '/storage/')) {
            $candidates[] = 'public/' . ltrim(substr($path, 9), '/');
        } elseif (Str::startsWith($path, 'storage/')) {
            $candidates[] = 'public/' . ltrim(substr($path, 8), '/');
        } elseif (!Str::startsWith($path, 'public/')) {
            $candidates[] = 'public/' . ltrim($path, '/');
        }

        foreach (array_unique($candidates) as $candidate) {
            if (!Storage::exists($candidate)) {
                continue;
            }

            try {
                $mimeType = Storage::mimeType($candidate) ?: 'image/png';
                return 'data:' . $mimeType . ';base64,' . base64_encode(Storage::get($candidate));
            } catch (\Throwable $e) {
                // Fall through to URL-based rendering.
            }
        }

        if (Str::startsWith($path, 'public/')) {
            return Storage::url($path);
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
