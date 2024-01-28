<?php

namespace App\Services;

use App\Services\Google\GoogleSheets;

class GoogleService
{
    public const PF_AUTOLEADER_ACCRUALS_SPREADSHEET = 'АЛ - Реализации и Возвраты';
    public const PF_AUTOPILOT_ACCRUALS_SPREADSHEET = 'АП - Реализации и Возвраты';
    public const PF_AUTOPILOT_ACCRUALS_TAB = 'АП';
    public const PF_AUTOLEADER_ACCRUALS_TAB = 'АЛ';

    private GoogleSheets $googleSheets;
    private FtpService $ftpService;

    public function __construct(GoogleSheets $googleSheets, FtpService $ftpService)
    {
        $this->googleSheets = $googleSheets;
        $this->ftpService = $ftpService;
    }

    public function read(string $spreadsheet, string $tab)
    {
        return $this->googleSheets->read($spreadsheet, $tab)['result'];
    }

    public function update(string $spreadsheet, string $tab, array $values, string $cellsRange = 'A1:Z'): bool
    {
        $this->googleSheets->clear($spreadsheet, $tab, $cellsRange);
        $result = $this->googleSheets->update($spreadsheet, $tab, $values, $cellsRange)['result'];
        return $result['updated_rows'] === count($values);
    }

    public function processUpdatePfAccrualsFromExcel()
    {
        $excelDataDict = $this->ftpService->getPfAccruals();
        if (empty($excelDataDict)) {
            return;
        }

        $googleSheetsData = $this->read(
            GoogleService::PF_AUTOLEADER_ACCRUALS_SPREADSHEET,
            GoogleService::PF_AUTOLEADER_ACCRUALS_TAB
        );

        // если кол-во строк в файле google sheet равно кол-ву строк в файлах ftp,
        // значит новых данных нет, и обновлять файл google sheet не нужно
        if (count($excelDataDict) === count($googleSheetsData) - 1) {
            return;
        }

        // update data
        foreach ($excelDataDict as &$row) {
            $row[8] = 'Нет';
        }
        $googleSheetsDataDict = collect($googleSheetsData)->slice(1)->keyBy(7)->toArray();
        $allData = array_merge($excelDataDict, $googleSheetsDataDict);

        $values = [$googleSheetsData[0], ...array_values($allData)];
        $this->update(
            GoogleService::PF_AUTOLEADER_ACCRUALS_SPREADSHEET,
            GoogleService::PF_AUTOLEADER_ACCRUALS_TAB,
            $values
        );
    }
}
