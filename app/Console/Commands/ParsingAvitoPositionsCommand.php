<?php

namespace App\Console\Commands;

use App\Jobs\ParseAvitoPositionsJob;
use App\Models\Posting;
use App\Services\AvitoCSVService;
use Illuminate\Console\Command;

class ParsingAvitoPositionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:avito-positions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Берет объявления из таблицы postigns, парсит их позиции на avito.ru ' .
        'и записывает в таблицу posting_positions';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $postings = Posting::query()->select('query', 'query_url', 'post_id')->get()
            ->groupBy('query')
            ->toArray();
        foreach ($postings as $query => $posting) {
            $queryUrl = $posting[0]['query_url'];
            $postIds = array_column($posting, 'post_id');
            ParseAvitoPositionsJob::dispatch($query, $queryUrl, $postIds);
        }
    }
}
