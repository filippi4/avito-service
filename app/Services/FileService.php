<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class FileService
{
    public const BASE_DIR = 'files';

    public function downloadFile(string $url): void
    {
        $contents = file_get_contents($url);
        $fileName = basename($url);

        Storage::put(self::BASE_DIR . '/' . $fileName, $contents);
    }
}
