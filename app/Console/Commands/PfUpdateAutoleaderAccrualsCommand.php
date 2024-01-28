<?php

namespace App\Console\Commands;

use App\Jobs\GoogleSheetsAutoleaderAccrualsUpdaterFromExcel;
use App\Jobs\PfAutoleaderAccrualsUpdaterFromGoogleSheets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class PfUpdateAutoleaderAccrualsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pf:update-autoleader-accruals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обновляет данные в google sheets из ftp, после отправляет неотправленные данные в пф.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Bus::chain([
            new GoogleSheetsAutoleaderAccrualsUpdaterFromExcel,
            new PfAutoleaderAccrualsUpdaterFromGoogleSheets
        ])->dispatch();

        return self::SUCCESS;
    }
}
