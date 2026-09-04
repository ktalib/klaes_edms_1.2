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
     * Clear the user's photo: delete the file and blank both columns.
     * The caller is responsible for saving the model.
     *
     * Returns true when the user actually had a photo to remove.
     *
     * Note this re-arms the mandatory-photo gate — User::$needs_profile_photo becomes
     * true again, so RequireProfilePhoto will hold the user on the upload card at their
     * next request until they supply a new one. That is the intended effect of removing
     * a wrong or unacceptable photo.
     */
    public function remove(User $user): bool
    {
        $had = $user->profile_url !== null;

        // Both columns are cleared: profile is what the accessor reads first, but a
        // legacy row can name a different file in passport_photo_path, which would
        // otherwise resurface as the fallback.
        $this->deleteFile($user, (string) $user->profile);
        $this->deleteFile($user, (string) $user->passport_photo_path);

        $user->profile = null;
        $user->passport_photo_path = null;

        return $had;
    }

    /**
     * Remove the user's current photo file, leaving the shared placeholder alone.
     */
    private function deletePrevious(User $user): void
    {
        $this->deleteFile($user, (string) $user->profile);
    }

    /**
     * Delete one stored photo path off the public disk.
     *
     * Skips the shared "avatar.png" placeholder, and skips any path another user row
     * still points at — legacy rows stored bare filenames, so two accounts can name the
     * same file and deleting it for one would blank the other.
     */
    private function deleteFile(User $user, string $stored): void
    {
        $stored = trim($stored);

        if ($stored === '' || strtolower($stored) === self::PLACEHOLDER) {
            return;
        }

        $sharedWith = User::where('id', '<>', $user->id)
            ->where(function ($query) use ($stored) {
                $query->where('profile', $stored)->orWhere('passport_photo_path', $stored);
            })
            ->exists();

        if ($sharedWith) {
            return;
        }

        // Older rows stored a bare filename that lived under upload/profile.
        $candidates = [$stored, self::DIRECTORY . '/' . $stored];

        foreach ($candidates as $candidate) {
            if (Storage::disk('public')->exists($candidate)) {
                Storage::disk('public')->delete($candidate);
                return;
            }
        }
    }
}
