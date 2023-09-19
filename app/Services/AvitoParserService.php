<?php

namespace App\Services;

use App\Services\DOMParsers\AvitoDOMParser;
use Illuminate\Support\Facades\Http;

class AvitoParserService
{
    private const DEFAULT_POSITION = 1000;
    private const DEFAULT_VIEWS = null;
    private AvitoDOMParser $parser;
    public function __construct()
    {
        $this->parser = new AvitoDOMParser();
    }

    /**
     * Вычисляет позиции объявлений $postIds по адресу $queryUrl, проходит все страницы пагинации.
     * @param string $queryUrl
     * @param array $postIds
     * @return array
     */
    public function calculatePositions(string $queryUrl, array $postIds): array
    {
        $result['positions'] = array_combine($postIds, array_fill(0, count($postIds), self::DEFAULT_POSITION));

        $page = 0;
        $passedPostsCount = 0;
        $queryPostIds = [];

        do {
            $page += 1;
            $queryUrlWithPage = $queryUrl . "&p={$page}";

            $response = Http::retry(3, 100)->get($queryUrlWithPage);
            $html = $response->body();

            $positionsResult = $this->parser->getPostingsPositions($html);

            $postsTotal = $positionsResult['total'];
            $pageTotal = $positionsResult['page_total'];
            $pagePositions = $positionsResult['positions'];

            if (empty($pageTotal)) {
                break;
            }

            $queryPostIds = array_merge($queryPostIds, array_keys($pagePositions));

            $passedPostsCount += $pageTotal;

            // DEBUG
            dump("{$page}/{$passedPostsCount}/{$postsTotal}");
        } while ($passedPostsCount < $postsTotal);

        $result['total'] = $postsTotal;

        $foundPostIds = array_intersect($queryPostIds, $postIds);
        foreach ($foundPostIds as $position => $postId) {
            $result['positions'][$postId] = $position + 1;
        }

        return $result;
    }

    public function getPostingViews(array $postIds): array
    {
        $result = [];

        $postIdsCount = count($postIds);
        foreach ($postIds as $index => $postId) {
            $response = Http::retry(3, 100)->get("avito.ru/{$postId}");
            $html = $response->body();

            $viewsResult = $this->parser->getPostingViews($html);

            $result[$postId]['total_views'] = $viewsResult['total_views'] ?? self::DEFAULT_VIEWS;
            $result[$postId]['today_views'] = $viewsResult['today_views'] ?? self::DEFAULT_VIEWS;

            // DEBUG
            dump(($index + 1) . "/{$postIdsCount}");
        }

        return $result;
    }
}
