<?php

namespace App\Imports;

use App\Models\PfOperation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMappedCells;

class PfOperationsDenizImport implements ToModel, WithHeadingRow
{
    private const DENIZ_ACCOUNT_ID = 494598;

    public function model(array $row)
    {
        $value = $row['tutar_tl'];
        $operationType = $value > 0 ? 'accrual' : 'outcome';
        $date = Carbon::parse($row['tarih']);
        return new PfOperation([
            'operation_type' => $operationType,
            'operation_date' => $date->toDateString(),
            'account_id' => self::DENIZ_ACCOUNT_ID,
            'account_title' => 'DenizBank',
            'value' => abs($value),
            'currency_code' => 'TRY',
            'comment' => $row['aciklama'] . ' ' . $row['islem'] . ' ' . $row['kanal'] . ' ' . $row['dekont_numarasi'],
            'operation_dt' => $date->toDateTimeString(),
        ]);
    }

    public function headingRow(): int
    {
        return 13;
    }
}
