<?php

namespace App\Services;

use App\Services\Google\GoogleSheets;
use Carbon\Carbon;

class GoogleService
{
    public const PF_AUTOLEADER_ACCRUALS_SPREADSHEET = 'АЛ - Реализации и Возвраты';
    public const PF_AUTOPILOT_ACCRUALS_SPREADSHEET = 'АП - Реализации и Возвраты';
    public const PF_AUTOPILOT_ACCRUALS_TAB = 'АП';
    public const PF_AUTOPILOT_ORDERS_TAB = 'Заказы';
    public const PF_AUTOLEADER_ACCRUALS_TAB = 'АЛ';
    public const PF_AUTOLEADER_RETURNS_TAB = 'Возвраты';

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

    public function precessUpdateAutoleaderReturnsFromGoogleSheets()
    {
        $dateGoogleIndex = 1;
        $addedInRetailCRMGoogleIndex = 7;

        $googleSheetsData = $this->read(
            GoogleService::PF_AUTOLEADER_ACCRUALS_SPREADSHEET,
            GoogleService::PF_AUTOLEADER_RETURNS_TAB,
        );
        $googleSheetsDataRows = array_slice($googleSheetsData, 1);

        $notAddedInRetailCRMMinOrderDate = Carbon::parse(collect($googleSheetsDataRows)
                ->filter(fn ($row) => $row[$addedInRetailCRMGoogleIndex] === 'Нет')
                ->pluck($dateGoogleIndex)
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->min()
            )->subDays(90)->toDateString();
        $retailCRMOrders = $this->retailCRMService->getAllOrders(
            $notAddedInRetailCRMMinOrderDate,
            $this->retailCRMService::ORDERS_RETURNS_STATUSES,
            [$this->retailCRMService::AUTOLEADER_ORDERS_SHIPMENT_STORES],
        );

        // данные из RetailCRM приходят отсортированные по дате по убыванию
        // меняем порядок по возрастанию, чтобы у ключей перезаписывались заказы с ближайшей датой
        $retailCRMOrders = array_reverse($retailCRMOrders);
        // формирование словаря заказов RetailCRM с ключем "Артикул автодруг" для заказов, у которых ключ есть
        $retailCRMOrdersDict = [];
        foreach ($retailCRMOrders as $order) {
            if (isset($order['customFields']['artikul_avtodrug'])) {
                $retailCRMOrdersDict[$order['customFields']['artikul_avtodrug']] = $order;
            }
        }

        $numberGoogleIndex = 0;
        $articleGoogleIndex = 2;
        $retailCRMOrderIdGoogleIndex = 8;
        $retailCRMOrderDateGoogleIndex = 9;
        $retailCRMOrderShipmentDateGoogleIndex = 10;
        $retailCRMOrderLinkGoogleIndex = 11;
        // заполнение
        $notUpdatedRowsIncrementChecker = 0;
        $googleSheetsDataRowsCount = count($googleSheetsDataRows);
        foreach ($googleSheetsDataRows as $index => &$row) {
            if ($row[$addedInRetailCRMGoogleIndex] === 'Нет') {
                if (isset($retailCRMOrdersDict[$row[$articleGoogleIndex]])) {
                    $notUpdatedRowsIncrementChecker++;

                    $retailCRMOrder = $retailCRMOrdersDict[$row[$articleGoogleIndex]];
                    $row[$retailCRMOrderIdGoogleIndex] = $retailCRMOrder['id'];
                    $row[$retailCRMOrderDateGoogleIndex] = Carbon::parse($retailCRMOrder['createdAt'])
                        ->format('d.m.Y');
                    $row[$retailCRMOrderShipmentDateGoogleIndex] = Carbon::parse($retailCRMOrder['shipmentDate'])
                        ->format('d.m.Y');
                    $row[$retailCRMOrderLinkGoogleIndex] = "https://totalart.retailcrm.ru/orders/{$retailCRMOrder['id']}/edit";

                    $retailCRMOrder['customFields']['vozvratnaya_realizacia_nomer'] = $row[$numberGoogleIndex];
                    $retailCRMOrder['customFields']['data_prinatia_vozvrata_v_zachet'] = Carbon::parse($row[$dateGoogleIndex])
                        ->toDateString();

                    // обновление данных в RetailCRM
                    $isUpdated = $this->retailCRMService->editOrder($retailCRMOrder);
                    // debug
//                    dump(($index + 1) . '/' . $googleSheetsDataRowsCount
//                        . ' order_id (' . $retailCRMOrder['id'] . ')'
//                        . ' is updated: ' . ($isUpdated ? 'yes' : 'no')
//                    );
                    if ($isUpdated) {
                        $row[$addedInRetailCRMGoogleIndex] = 'Да';
                    } else {
                        $notUpdatedRowsIncrementChecker--;
                    }
                }
            }
        }

        if ($notUpdatedRowsIncrementChecker === 0) {
            return;
        }

        $values = [$googleSheetsData[0], ...$googleSheetsDataRows];
        $this->update(
            GoogleService::PF_AUTOLEADER_ACCRUALS_SPREADSHEET,
            GoogleService::PF_AUTOLEADER_RETURNS_TAB,
            $values
        );
    }

    public function precessUpdateAutoleaderReturnsFromFtp()
    {
        $excelDataDict = $this->ftpService->getAutoleaderReturnsDataDict();
        if (empty($excelDataDict)) {
            return;
        }

        $googleSheetsData = $this->read(
            GoogleService::PF_AUTOLEADER_ACCRUALS_SPREADSHEET,
            GoogleService::PF_AUTOLEADER_RETURNS_TAB,
        );
        if (empty($googleSheetsData)) {
            return;
        }

        $numberExcelIndex = 2;
        $dateExcelIndex = 3;
        $articleExcelIndex = 4;
        $nomenclatureExcelIndex = 5;
        $characteristicExcelIndex = 6;
        $sumExcelIndex = 7;
        $contractorExcelIndex = 9;
        $fileNameGoogleIndex = 13;

        // подготовка данных из ftp файлов к формату google документа
        $excelDictDataForGoogle = [];
        foreach ($excelDataDict as $fileNameKey => $row) {
            $excelDictDataForGoogle[$fileNameKey] = [
                str_pad($row[$numberExcelIndex], 11, '0', STR_PAD_LEFT), // Номер, дополненный '0' слева, как в ftp файле
                $row[$dateExcelIndex], // Дата
                $row[$articleExcelIndex], // Артикул
                $row[$nomenclatureExcelIndex], // Номенклатура
                $row[$characteristicExcelIndex], // Характеристика
                $row[$sumExcelIndex], // Сумма
                $row[$contractorExcelIndex], // Контрагент
                'Нет', // Добавлен в RetailCRM
                '', // ID Заказа
                '', // Дата заказа
                '', // Дата отгрузки
                '', // Ссылка на заказ RetailCRM
                FtpService::getGoogleMethodByExcelContractor($row[$contractorExcelIndex]), // Способ
                $fileNameKey, // Имя файла
            ];
        }

        $googleSheetsDataDict = collect($googleSheetsData)->slice(1)->keyBy($fileNameGoogleIndex)->toArray();

        $mergedDictionaries = array_merge($excelDictDataForGoogle, $googleSheetsDataDict);

        // выйти, если кол-во существующих строк равно кол-во обновленных строк
        if (count($googleSheetsDataDict) === count($mergedDictionaries)) {
            return;
        }

        $values = [$googleSheetsData[0], ...array_values($mergedDictionaries)];
        $this->update(
            GoogleService::PF_AUTOLEADER_ACCRUALS_SPREADSHEET,
            GoogleService::PF_AUTOLEADER_RETURNS_TAB,
            $values
        );
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
