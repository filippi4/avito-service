<?php

namespace App\Services;

use App\Models\Posting;
use League\Csv\Writer;
use Illuminate\Support\Facades\DB;

/**
 * Создает форматированный csv файл storage/export.csv
 */
class AvitoCSVService
{
    private static array $defaultHeader = [
        'Запрос',
        'УРЛ Запроса',
        'Регион',
        'Количество объявлений',
        'Частотность показов в месяц',
        'Точная частота',
        'Объявление ID',
        'Аккаунт',
        'Объявление URL',
    ];

    public static function exportPositions()
    {
        $header = self::$defaultHeader;
        $idRowDict = [];

        $postingPositionsJoin = DB::table('posting_positions as pp')->selectRaw(
                'p.id, pp.fk_posting_id, p.query, p.query_url, p.region, p.post_count, p.freq_per_month,
                p.exact_freq, p.post_id, p.account, p.post_url, pp.position, pp.total, pp.check_date, pp.updated_at'
            )->join('postings as p', 'pp.fk_posting_id', '=', 'p.id')->get()->toArray();

        $postings = Posting::all();

        $dates = array_unique(array_column($postingPositionsJoin, 'check_date'));
        $ids = $postings->pluck('id');

        foreach ($postings as $posting) {
            $idRowDict[$posting->id] = array_values($posting->replicate()->toArray());
        }

        $dateIdResultsDict = [];
        foreach ($postingPositionsJoin as $item) {
            $dateIdResultsDict[$item->check_date][$item->fk_posting_id] = [
                'position' => $item->position,
                'total' => $item->total,
            ];
        }

        foreach ($dates as $date) {
            $header[] = $date;
            $header[] = $date;

            foreach ($ids as $id) {
                $idRowDict[$id][] = $dateIdResultsDict[$date][$id]['position'] ?? '-';
                $idRowDict[$id][] = $dateIdResultsDict[$date][$id]['total'] ?? '-';
            }
        }

        $rows = array_values($idRowDict);

        $content = array_merge([$header], $rows);

        $path = storage_path('export.csv');
        $csv = Writer::createFromPath($path, 'w');
        $csv->insertAll($content);
    }
}
