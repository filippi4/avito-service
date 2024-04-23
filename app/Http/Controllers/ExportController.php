<?php

namespace App\Http\Controllers;

use App\Services\ExportService;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function show(string $file)
    {
        abort_unless(Storage::fileExists(ExportService::BASE_DIR . '/' . $file), 403);

        return Storage::download(ExportService::BASE_DIR . '/' . $file);
    }
}
