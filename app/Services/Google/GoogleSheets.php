<?php

namespace App\Services\Google;

use Google_Service_Sheets;
use App\Services\Google\Exceptions\GoogleServiceException;
use App\Services\Google\Exceptions\GoogleWorksheetException;

/**
 *
 */
class GoogleSheets extends GoogleClient
{

    /**
     * @var null
     */
    protected $spreadsheet = null;

    /**
     * @var Google_Service_Sheets
     */
    protected Google_Service_Sheets $service;

    /**
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->service = new Google_Service_Sheets($this->client);
    }

    /**
     * @param $spreadsheetName
     * @return GoogleSheets
     * @throws GoogleServiceException
     */
    public function validateSpreadsheet($spreadsheetName): GoogleSheets
    {
        $spreadsheet = $this->validate($spreadsheetName);
        $this->spreadsheet = $this->service->spreadsheets->get($spreadsheet->id);
        return $this;
    }

    /**
     * @param string $spreadsheetName
     * @param string $tabName
     * @return mixed
     * @throws GoogleServiceException
     * @throws GoogleWorksheetException
     */
    public function validateSheet(string $spreadsheetName, string $tabName)
    {
        $this->validateSpreadsheet($spreadsheetName);

        $worksheet = collect($this->getSheets())->where(function($sheet) use ($tabName) {
            return $sheet->getProperties()->getTitle() === $tabName;
        })->first();
        if (empty($worksheet)) {
            throw new GoogleWorksheetException('Неверные параметры Google таблицы');
        }

        return $worksheet;
    }

    /**
     * @return mixed|null
     */
    public function getSpreadsheet(): mixed
    {
        return $this->spreadsheet;
    }

    /**
     * @return array|null
     */
    public function getSheets(): ?array
    {
        return $this->spreadsheet ? $this->spreadsheet->getSheets() : null;
    }

    /**
     * @param string $spreadsheetName
     * @param string $tabName
     * @return array
     * @throws GoogleServiceException
     * @throws GoogleWorksheetException
     */
    public function read(string $spreadsheetName, string $tabName): array
    {
        $worksheet = $this->validateSheet($spreadsheetName, $tabName);
        $rows = $this->service->spreadsheets_values->get($this->spreadsheet->getSpreadsheetId(), "{$tabName}");
        $values = $rows->getValues();

        return $this->readResponse($worksheet, $values);
    }

    /**
     * @param $worksheet
     * @param array $values
     * @return array
     */
    private function readResponse($worksheet, array $values): array
    {
        return [
            'properties' => $worksheet->getProperties(),
            'result' => $values
        ];
    }

    /**
     * @param string $spreadsheetName
     * @param string $tabName
     * @param array $values
     * @return array
     * @throws GoogleServiceException
     * @throws GoogleWorksheetException
     */
    public function update(string $spreadsheetName, string $tabName, array $values, string $cellsRange = 'A1:Z', bool $hasFormulas = false): array
    {
        $worksheet = $this->validateSheet($spreadsheetName, $tabName);
        $range = "{$tabName}!{$cellsRange}";
        $body = new \Google_Service_Sheets_ValueRange([
            'values' => $values
        ]);
        $params = [
            'valueInputOption' => $hasFormulas ? 'USER_ENTERED' : 'RAW'
        ];
        $result = $this->service->spreadsheets_values->update($this->spreadsheet->getSpreadsheetId(),
            $range,
            $body,
            $params
        );

        return $this->updateResponse($worksheet, $result);
    }

    private function updateResponse($worksheet, $result): array
    {
        return [
            'properties' => $worksheet->getProperties(),
            'result' => [
                'updated_cells' => $result->getUpdatedCells(),
                'updated_columns' => $result->getUpdatedColumns(),
                'updated_range' => $result->getUpdatedRange(),
                'updated_rows' => $result->getUpdatedRows()
            ]
        ];
    }

    public function clear(string $spreadsheetName, string $tabName, string $cellsRange = 'A1:Z'): array
    {
        $worksheet = $this->validateSheet($spreadsheetName, $tabName);
        $range = "{$tabName}!{$cellsRange}";
        $body = new \Google_Service_Sheets_ClearValuesRequest();
        $result = $this->service->spreadsheets_values->clear($this->spreadsheet->getSpreadsheetId(),
            $range,
            $body
        );

        return $this->clearResponse($worksheet, $result);
    }

    private function clearResponse($worksheet, $result): array
    {
        return [
            'properties' => $worksheet->getProperties(),
            'result' => [
                'cleared_range' => $result->getClearedRange(),
            ]
        ];
    }
}
