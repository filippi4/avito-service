<?php

namespace App\Imports;

use App\Models\PfOperation;
use App\Services\SberService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMappedCells;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class PfOperationsSberOutcomeImport implements WithHeadingRow, ToModel
{
    public function model(array $row)
    {
        $month = explode(' ', $row['data'])[1] ?? '';
        $monthNumber = $this->getRuMonthNumber($month);
        if ($monthNumber !== 0) {
            $date = Carbon::createFromFormat('d n Y, H:i', str_replace($month,  $monthNumber, $row['data']));
        } else {
            $date = Carbon::createFromTimestamp(0);
        }
        $accountId = SberService::getAccountId($row['nomer_scetakarty_spisaniia']);
        return new PfOperation([
            'operation_type' => 'outcome',
            'operation_date' => $date->toDateString(),
            'account_id' => $accountId,
            'account_title' => SberService::getAccountTitle($accountId),
            'value' => $row['summa_v_rubliax'],
            'currency_code' => 'RUB',
            'comment' => preg_replace('| +|', ' ', $row['opisanie'] . ' ' . $row['kategoriia'] . ' ' . $row['nomer']),
            'operation_dt' => $date->toDateTimeString(),
        ]);
    }

    private function getRuMonthNumber(string $string): int
    {
        $monthsDict = array_flip([
            '',
            'янв',
            'фев',
            'мар',
            'апр',
            'май',
            'июн',
            'июл',
            'авг',
            'сен',
            'окт',
            'ноя',
            'дек'
        ]);
        return $monthsDict[mb_substr($string, 0, 3)] ?? 0;
    }

    public function headingRow(): int
    {
        return 1;
    }
}
