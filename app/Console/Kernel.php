<?php

namespace App\Console;

use App\Jobs\ExportOrdersCostToExcelJob;
use App\Jobs\GoogleSheetsAutoleaderAccrualsUpdaterFromExcel;
use App\Jobs\GoogleSheetsAutopilotOrdersUpdaterFromRetailCRM;
use App\Jobs\PfAutopilotAccrualsUpdaterFromGoogleSheets;
use App\Jobs\PfAutoleaderAccrualsUpdaterFromGoogleSheets;
use App\Jobs\WbExcludedUpdater;
use DateTimeZone;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Bus;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new WbExcludedUpdater)->everyThirtyMinutes();

        $schedule->command('retailcrm:export-orders-cost-to-excel')->hourly();

        $schedule->call(function () {
            app('App\Services\FileService')->downloadFile('https://chekhly.ru/upload/IMTID.csv');
        })->dailyAt('00:05');

        $schedule->job(new GoogleSheetsAutopilotOrdersUpdaterFromRetailCRM)->dailyAt('06:10');
        $schedule->job(new PfAutopilotAccrualsUpdaterFromGoogleSheets)->dailyAt('06:20');
        $schedule->command('pf:update-autoleader-accruals')->dailyAt('06:30');
        $schedule->command('google-sheets:update-autoleader-returns')->dailyAt('06:40');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    protected function scheduleTimezone(): DateTimeZone|string|null
    {
        return 'Europe/Moscow';
    }
}
