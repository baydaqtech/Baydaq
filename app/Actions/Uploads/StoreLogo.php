<?php

namespace App\Actions\Uploads;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreLogo
{
    /**
     * Save an uploaded logo (random file name) on the logo disk and drop the one it replaces.
     */
    public function handle(UploadedFile $file, ?string $replacing = null): string
    {
        $disk = config('baydaq.logo_disk');
        $path = $file->store('logos', $disk);

        if ($replacing) {
            Storage::disk($disk)->delete($replacing);
        }

        return $path;
    }

    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk(config('baydaq.logo_disk'))->url($path) : null;
    }
}
