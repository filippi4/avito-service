<?php

namespace App\Console\Commands;

use App\Jobs\GoogleSheetsAutoleaderAccrualsUpdaterFromExcel;
use App\Jobs\GoogleSheetsAutoleaderReturnsUpdaterFromFtp;
use App\Jobs\GoogleSheetsAutoleaderReturnsUpdaterFromGoogleSheets;
use App\Jobs\PfAutoleaderAccrualsUpdaterFromGoogleSheets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class GoogleSheetsUpdateAutoleaderReturnsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google-sheets:update-autoleader-returns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обновляет возраты в google sheets из ftp, после обновляет данные в RetailCRM и меняет статус';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Bus::chain([
            new GoogleSheetsAutoleaderReturnsUpdaterFromFtp,
            new GoogleSheetsAutoleaderReturnsUpdaterFromGoogleSheets
        ])->dispatch();

        return self::SUCCESS;
    }
}
