<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WbOrdersCostExport implements FromArray, WithHeadings
{
    public function __construct(private readonly array $data)
    {}

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Номер заказа (srid)',
            'Артикул',
            'Себестоимость',
        ];
    }
}
