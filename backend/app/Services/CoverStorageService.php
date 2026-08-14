<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CoverStorageService
{
    private const DIRECTORY = 'covers';

    private const DISK = 'public';

    /**
     * Store an uploaded cover image and return its publicly reachable URL.
     */
    public function store(UploadedFile $file): string
    {
        // extension() guesses from the file's actual MIME content and can return
        // false for content it doesn't recognize — validation already restricts
        // uploads to jpeg/png/webp, so this fallback is never actually hit.
        $extension = $file->extension() ?: 'jpg';

        $path = $file->storeAs(
            self::DIRECTORY,
            Str::uuid()->toString().'.'.$extension,
            self::DISK,
        );

        if ($path === false) {
            throw new \RuntimeException('Failed to store cover image.');
        }

        return $this->urlFor($path);
    }

    /**
     * Delete the file behind a cover URL, but only if it's one we stored
     * ourselves (BGG-imported/external URLs are left untouched).
     */
    public function delete(?string $coverUrl): void
    {
        $path = $this->ownStoragePath($coverUrl);

        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    private function urlFor(string $path): string
    {
        return rtrim(config('app.url'), '/').'/api/storage/'.$path;
    }

    private function ownStoragePath(?string $coverUrl): ?string
    {
        if ($coverUrl === null) {
            return null;
        }

        $prefix = rtrim(config('app.url'), '/').'/api/storage/';

        if (! str_starts_with($coverUrl, $prefix)) {
            return null;
        }

        return substr($coverUrl, strlen($prefix));
    }
}
