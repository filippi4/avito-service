<?php

namespace App\Console\Commands;

use App\Models\Posting;
use App\Services\AvitoCSVService;
use App\Services\AvitoDBService;
use Illuminate\Console\Command;

class ExportPostingPositionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:posting-positions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Пересоздает таблицу export_posting_positions с сформированными данными о позициях объявлений.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        AvitoDBService::exportPostingPositions();

        return self::SUCCESS;
    }
}
