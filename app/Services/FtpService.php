<?php

namespace App\Services;

use App\Imports\PfOperationsDenizImport;
use App\Imports\PfOperationsRaifImport;
use App\Imports\PfOperationsSberIncomeImport;
use App\Imports\PfOperationsSberOutcomeImport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class FtpService
{
    public function processExportFiles()
    {
        foreach (Storage::disk('ftp')->allFiles() as $filePath) {
            if (Str::of($filePath)->isMatch('/.*\.(xlsx|csv)$/')) {
                try {
                    $dir = explode('/', $filePath)[0];
                    switch ($dir) {
                        case 'al':
                            break;
                        case 'deniz':
                            Excel::import(new PfOperationsDenizImport(), $filePath, 'ftp');
                            break;
                        case 'raif':
                            Excel::import(new PfOperationsRaifImport(), $filePath, 'ftp');
                            break;
                        case 'sber':
                            $file = basename($filePath);
                            if (Str::is('income*', $file)) {
                                Excel::import(new PfOperationsSberIncomeImport, $filePath, 'ftp');
                            }
                            if (Str::is('outcome*', $file)) {
                                Excel::import(new PfOperationsSberOutcomeImport, $filePath, 'ftp');
                            }
                            break;
                    }
                } catch (Throwable $e) {
                    Log::debug(__METHOD__, [$e->getMessage()]);
                }
            }
        }
    }
}
