<?php

namespace App\Http\Controllers;

use App\Services\FileService;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function download(string $file)
    {
        abort_unless(Storage::fileExists(FileService::BASE_DIR . '/' . $file), 403);

        return Storage::download(FileService::BASE_DIR . '/' . $file);
    }
}
