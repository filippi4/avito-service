<?php

namespace App\Services;

use App\Services\Google\GoogleSheets;

class GoogleService
{
    public const PF_AUTOLEADER_ACCRUALS_SPREADSHEET = 'АЛ - Реализации и Возвраты';
    public const PF_AUTOPILOT_ACCRUALS_SPREADSHEET = 'АП - Реализации и Возвраты';
    public const PF_AUTOPILOT_ACCRUALS_TAB = 'АП';
    public const PF_AUTOPILOT_ORDERS_TAB = 'Заказы';
    public const PF_AUTOLEADER_ACCRUALS_TAB = 'АЛ';

    private GoogleSheets $googleSheets;
    private FtpService $ftpService;
    private RetailCRMService $retailCRMService;

    public function __construct(GoogleSheets $googleSheets, FtpService $ftpService, RetailCRMService $retailCRMService)
    {
        $this->googleSheets = $googleSheets;
        $this->ftpService = $ftpService;
        $this->retailCRMService = $retailCRMService;
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

    public function precessUpdateAutopilotOrdersFromRetailCRM()
    {
        $googleSheetsData = $this->read(
            GoogleService::PF_AUTOPILOT_ACCRUALS_SPREADSHEET,
            GoogleService::PF_AUTOPILOT_ORDERS_TAB,
        );

        $googleSheetsDataRows = array_slice($googleSheetsData, 1);
        $lastDate = array_key_exists(2, $googleSheetsDataRows[0])
            ? max(array_column($googleSheetsDataRows, 2)) : '2023-01-01';
        $retailCRMData = $this->retailCRMService->getOrdersNumAndMethodAndDateAndDocumentType($lastDate, ['sklad-ap']);

        $allData = array_merge($googleSheetsDataRows, $retailCRMData);

        // приведение значение поля номера заказа к числовому формату
        foreach ($allData as &$row) {
            $row[0] = get_first_digits_or_origin_string($row[0]);
        }
        // формирования словаря данных с ключем - номер заказа
        // новые данные из retailcrm в конце массива, данные для одинаковых ключей перезаписываются.
        $allData = collect($allData)->keyBy(0)->values()->toArray();

        // выйти, если кол-во существующих строк равно кол-во обновленных строк
        if (count($googleSheetsDataRows) === count($allData)) {
            return;
        }

        // сортировка по дате и номеру заказа. отсутствие даты считается пустой строкой, которая меньше строки с датой
        usort($allData, function ($first, $second) {
            $firstDate = $first[2] ?? '';
            $secondDate = $second[2] ?? '';
            if ($firstDate === $secondDate) {
                return $second[0] <=> $first[0];
            }
            return $secondDate <=> $firstDate;
        });

        $values = [$googleSheetsData[0], ...$allData];
        $this->update(
            GoogleService::PF_AUTOPILOT_ACCRUALS_SPREADSHEET,
            GoogleService::PF_AUTOPILOT_ORDERS_TAB,
            $values
        );

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
