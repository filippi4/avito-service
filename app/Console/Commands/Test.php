<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        app('App\Services\PfService')->processUpdateAutopilotAccrualsFromGoogleSheets();
        app('App\Services\GoogleService')->precessUpdateAutopilotOrdersFromRetailCRM();

        return self::SUCCESS;
    }
}
