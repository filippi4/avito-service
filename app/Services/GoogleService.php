<?php

namespace App\Services;

use App\Services\Google\GoogleSheets;
use Throwable;

class GoogleService
{
    private GoogleSheets $googleSheets;

    private const PF_ACCRUALS_SPREADSHEET = 'АП - Реализации и Возвраты';
    private const PF_ACCRUALS_TAB = 'АП';

    public function __construct(GoogleSheets $googleSheets)
    {
        $this->googleSheets = $googleSheets;
    }

    public function getPfAccruals()
    {
        return $this->googleSheets->read(self::PF_ACCRUALS_SPREADSHEET, self::PF_ACCRUALS_TAB)['result'];
    }

    public function updatePfAccruals(array $values): bool
    {
        $oldValues = $this->googleSheets->read(self::PF_ACCRUALS_SPREADSHEET, self::PF_ACCRUALS_TAB)['result'];
        try {
            $this->googleSheets->clear(self::PF_ACCRUALS_SPREADSHEET, self::PF_ACCRUALS_TAB);
            $result = $this->googleSheets->update(self::PF_ACCRUALS_SPREADSHEET, self::PF_ACCRUALS_TAB, $values)['result'];
            dump($result);
            return $result['updated_rows'] === count($values);
        } catch (Throwable $th) {
            $result = $this->googleSheets->update(self::PF_ACCRUALS_SPREADSHEET, self::PF_ACCRUALS_TAB, $oldValues)['result'];
            if ($result['updated_rows'] === count($values)) {
                return false;
            } else {
                throw $th;
            }
        }
    }
}
