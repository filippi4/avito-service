<?php

namespace App\Services;

class ExportService
{
    public const BASE_DIR = 'export';
    public const ORDERS_COST_FILES = [
        'wb' => 'wb_orders_cost.xlsx',
        'ozon' => 'ozon_orders_cost.xlsx',
        'ym' => 'ym_orders_cost.xlsx',
    ];

    public static function getOrderCostPaths(string $prefix = ''): array
    {
        $paths = [];
        foreach (self::ORDERS_COST_FILES as $marketplace => $file) {
            $paths[$marketplace] = self::BASE_DIR . '/'. $prefix . $file;
        }
        return $paths;
    }
}
