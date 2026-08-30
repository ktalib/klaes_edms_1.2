<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Single place that writes a user's passport photo.
 *
 * The `profile` column historically held three different shapes (a public-disk path,
 * a bare filename under upload/profile, and the "avatar.png" placeholder); every new
 * upload goes through here so it is always stored as a public-disk relative path,
 * which User::getProfileUrlAttribute() resolves for display.
 */
class ProfilePhotoService
{
    public const DIRECTORY = 'upload/profile';
    public const PLACEHOLDER = 'avatar.png';
    public const MAX_KILOBYTES = 2048;

    /**
     * Validation rules for an uploaded passport photo.
     */
    public static function rules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'image',
            'mimes:jpeg,jpg,png,gif',
            'max:' . self::MAX_KILOBYTES,
        ];
    }

    /**
     * Store the file against the user and return the stored path.
     * The caller is responsible for saving the model.
     */
    public function store(UploadedFile $file, User $user): string
    {
        $filename = uniqid('profile_') . '_' . time() . '.' . $file->getClientOriginalExtension();
        Storage::disk('public')->putFileAs(self::DIRECTORY, $file, $filename);

        $this->deletePrevious($user);

        $path = self::DIRECTORY . '/' . $filename;
        $user->profile = $path;
        $user->passport_photo_path = $path;

        return $path;
    }

    /**
     * Remove the user's current photo file, leaving the shared placeholder alone.
     */
    private function deletePrevious(User $user): void
    {
        $previous = trim((string) $user->profile);

        if ($previous === '' || strtolower($previous) === self::PLACEHOLDER) {
            return;
        }

        // Older rows stored a bare filename that lived under upload/profile.
        $candidates = [$previous, self::DIRECTORY . '/' . $previous];

        foreach ($candidates as $candidate) {
            if (Storage::disk('public')->exists($candidate)) {
                Storage::disk('public')->delete($candidate);
                return;
            }
        }
    }
}
