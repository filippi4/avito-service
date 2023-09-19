<?php

namespace App\Console\Commands;

use App\Models\Posting;
use App\Models\PostingView;
use App\Services\AvitoDBService;
use App\Services\AvitoParserService;
use Illuminate\Console\Command;

class ParsingAvitoViewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:avito-views';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсит просмотры объявления на avito.ru и записывает результаты в таблицу posting_views.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $portIds = Posting::query()->select('post_id')->distinct()->pluck('post_id')->toArray();
        $postingViews = (new AvitoParserService)->getPostingViews($portIds);

        $preparedForSave = [];
        foreach ($postingViews as $postId => $postingView) {
            $preparedForSave[] = [
                'post_id' => $postId,
                'total_views' => $postingView['total_views'],
                'today_views' => $postingView['today_views'],
            ];
        }

        PostingView::query()->upsert($preparedForSave, ['post_id']);

        return self::SUCCESS;
    }
}
