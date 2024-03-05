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

    public function getPfAccruals(): array
    {
        $diskName = 'ftp-google-sheets-accruals';
        $pattern = '/^Реестр общий Автолидер.*\.xlsx$/';
        $tabIndex = 0;
        $dataDict = [];
        foreach (Storage::disk($diskName)->allFiles() as $filePath) {
            if (Str::of($filePath)->isMatch($pattern)) {
                $excelData = Excel::toArray(new \stdClass(), $filePath, $diskName)[$tabIndex];
                $rows = array_merge($dataDict, array_slice($excelData, 1));
                foreach ($rows as $row) {
                    $dataDict[$row[7]] = $row;
                }
            }
        }
        return $dataDict;
    }

    public function getAutoleaderReturnsDataDict(): array
    {
        $diskName = 'ftp-google-sheets-accruals';
        $pattern = '/^Реестр возвратов по товарам Автолидер.*\.xlsx$/';
        $tabIndex = 0;
        $fileNameIndex = 10;
        $dict = [];
        foreach (Storage::disk($diskName)->allFiles() as $filePath) {
            if (Str::of($filePath)->isMatch($pattern)) {
                $excelData = Excel::toArray(new \stdClass(), $filePath, $diskName)[$tabIndex];

                $rows = array_slice($excelData, 1);
                foreach ($rows as $row) {
                    $dict[$row[$fileNameIndex]] = $row;
                }
            }
        }
        return $dict;
    }

    public static function getGoogleMethodByExcelContractor(string $contractor): string
    {
        return match($contractor) {
            'ИП Коршунов - Oz' => 'Ozon',
            'ИП Коршунов - ВБ' => 'Wildberies',
            'ИП Коршунов - ЯМ' => 'Yandex Market',
            'Коршунов Алексей Валерьевич ИП, опт !' => 'Мессенджеры', // или 'Через корзину', 'По телефону'
        };
    }
}
