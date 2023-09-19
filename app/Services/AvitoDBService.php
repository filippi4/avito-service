<?php

namespace App\Services;

use App\Models\Posting;
use App\Models\PostingView;
use League\Csv\Writer;
use Illuminate\Support\Facades\DB;

class AvitoDBService
{
    private const EXPORT_TABLE = 'export_posting_positions';
    private const MAX_MONTH = 3;
    private static array $columnTypeDict = [
        'Запрос' => 'VARCHAR(255) NOT NULL',
        'УРЛ Запроса' => 'TEXT NOT NULL',
        'Регион' => 'VARCHAR(255) NOT NULL',
        'Количество объявлений' => 'INT UNSIGNED NOT NULL',
        'Частотность показов в месяц' => 'INT UNSIGNED NOT NULL',
        'Точная частота' => 'INT UNSIGNED NOT NULL',
        'Объявление ID' => 'BIGINT UNSIGNED NOT NULL',
        'Аккаунт' => 'VARCHAR(255) NOT NULL',
        'Объявление URL' => 'VARCHAR(255) NOT NULL',
        'Просмотры в день' => 'INT UNSIGNED',
        'Просмотры всего' => 'INT UNSIGNED',
    ];

    public static function exportPostingPositions()
    {
        $header = self::$columnTypeDict;
        $idRowDict = [];

        $postingPositionsJoin = DB::table('posting_positions as pp')->selectRaw(
            'p.id, pp.fk_posting_id, p.query, p.query_url, p.region, p.post_count, p.freq_per_month,
                p.exact_freq, p.post_id, p.account, p.post_url, pp.position, pp.total, pp.check_date, pp.updated_at'
        )->join('postings as p', 'pp.fk_posting_id', '=', 'p.id')
            ->whereRaw('pp.check_date >= DATE_SUB(NOW(), INTERVAL ' . self::MAX_MONTH . ' MONTH)')
            ->get()->toArray();

        $postings = Posting::query()->from((new Posting)->getTable() . ' as p')
            ->selectRaw('p.id, p.query, p.query_url, p.region, p.post_count, p.freq_per_month, p.exact_freq,
                p.post_id, p.account, p.post_url, pv.today_views, pv.total_views')
            ->leftJoin((new PostingView)->getTable() . ' as pv',
                'pv.post_id', '=', 'p.post_id'
            )->get();

        $dates = array_unique(array_column($postingPositionsJoin, 'check_date'));
        $ids = $postings->pluck('id');

        foreach ($postings as $posting) {
            $idRowDict[$posting->id] = array_values($posting->replicate()->toArray());
        }

        // [date][posting_foreign_id] = ['position' => position, 'total' => total]
        $dateIdPositionsDict = [];
        foreach ($postingPositionsJoin as $item) {
            $dateIdPositionsDict[$item->check_date][$item->fk_posting_id] = [
                'position' => $item->position,
                'total' => $item->total,
            ];
        }

        foreach ($dates as $date) {
            $header["Позиция_{$date}"] = 'INT UNSIGNED';
            $header["Всего_{$date}"] = 'INT UNSIGNED';

            foreach ($ids as $id) {
                $idRowDict[$id][] = $dateIdPositionsDict[$date][$id]['position'] ?? null;
                $idRowDict[$id][] = $dateIdPositionsDict[$date][$id]['total'] ?? null;
            }

        }

        $rows = array_values($idRowDict);
        $rows = self::prepareDataForInsert($header, $rows);

        DB::statement('DROP TABLE IF EXISTS ' . self::EXPORT_TABLE . ';');
        self::createExportTable($header);

        DB::table(self::EXPORT_TABLE)->insert($rows);
    }

    private static function createExportTable(array $columnTypeDict): void
    {
        $query = 'CREATE TABLE IF NOT EXISTS ' . self::EXPORT_TABLE;

        $columnDefinition = '(';
        foreach ($columnTypeDict as $column => $type) {
            $columnDefinition .= "`{$column}` {$type},";
        }
        $columnDefinition = trim($columnDefinition, ",");
        $columnDefinition .= ')';

        $query .= $columnDefinition . ';';

        DB::statement($query);
    }

    private static function prepareDataForInsert(array $header, array $rows): array
    {
        $prepared = [];
        foreach ($rows as $row) {
            $prepared[] = array_combine(array_keys($header), $row);
        }
        return $prepared;
    }
}
