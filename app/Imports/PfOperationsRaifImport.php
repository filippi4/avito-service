<?php

namespace App\Imports;

use App\Models\PfOperation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMappedCells;

class PfOperationsRaifImport implements ToModel, WithHeadingRow, WithCustomCsvSettings
{
    private const RAIF_ACCOUNT_ID = 494595;

    public function model(array $row)
    {
        $value = floatval(str_replace(' ', '', $row['summa_v_valiute_sceta']));
        $operationType = $value > 0 ? 'accrual' : 'outcome';
        $date = Carbon::parse($row['data_tranzakcii']);
        return new PfOperation([
            'operation_type' => $operationType,
            'operation_date' => $date->toDateString(),
            'account_id' => self::RAIF_ACCOUNT_ID,
            'account_title' => 'Райф карта MasterCard',
            'value' => abs($value),
            'currency_code' => 'RUB',
            'comment' => $row['opisanie'],
            'operation_dt' => $date->toDateTimeString(),
        ]);
    }

    public function getCsvSettings(): array
    {
        return [
            'input_encoding' => 'windows-1251'
        ];
    }

    public function headingRow(): int
    {
        return 1;
    }
}
