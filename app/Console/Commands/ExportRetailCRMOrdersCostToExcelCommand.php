<?php

namespace App\Console\Commands;

use App\Jobs\ExportOrdersCostToExcelJob;
use Illuminate\Console\Command;

class ExportRetailCRMOrdersCostToExcelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retailcrm:export-orders-cost-to-excel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создает файлы себестоимости заказов WB/Ozon/YandexMarket из RetailCRM';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ExportOrdersCostToExcelJob::dispatch();
    }
}
