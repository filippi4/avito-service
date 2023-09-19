<?php

namespace App\Services\DOMParsers;

use DOMDocument;
use DOMXPath;

class AvitoDOMParser
{
    /**
     * Return total post, page total and position of posts. Position start from zero.
     * @param string $html
     * @return array
     */
    public function getPostingsPositions(string $html): array
    {
        $result = [
            'total' => 0,
            'page_total' => 0,
            'positions' => [],
        ];

        //add this line to suppress any warnings
        libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $xpath = new DOMXPath($doc);

        $totalNodeList = $xpath->evaluate('//span[@data-marker="page-title/count"]');
        $total = (int) $totalNodeList->item(0)->textContent;

        if ($total === 0) {
            return $result;
        }
        $result['total'] = $total;

        $postIdsNodeList = $xpath->evaluate('//div[@data-marker="item"]/@data-item-id');

        if ($postIdsNodeList->length === 0) {
            return $result;
        }
        $result['page_total'] = $postIdsNodeList->length;

        $positions = [];
        foreach ($postIdsNodeList as $index => $postIdNode) {
            $positions[$postIdNode->textContent] = $index + 1;
        }
        $result['positions'] = $positions;

        return $result;
    }

    /**
     * Возвращает просмотры объявления за все время и за сегодня. Если не найдет, то вернет пустой массив.
     * @param string $html
     * @return array
     */
    public function getPostingViews(string $html): array
    {
        $result = [];

        //add this line to suppress any warnings
        libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        $xpath = new DOMXPath($doc);

        $totalViewsNodeList = $xpath->evaluate('//span[@data-marker="item-view/total-views"]');
        if ($totalViewsNodeList->length > 0) {
            $totalViewsString = $totalViewsNodeList->item(0)->textContent;
            $totalViews = $this->getMatchedDigit($totalViewsString);
            if ($totalViews !== null) {
                $result['total_views'] = $totalViews;
            }
        }

        $todayViewsNodeList = $xpath->evaluate('//span[@data-marker="item-view/today-views"]');
        if ($todayViewsNodeList->length > 0) {
            $todayViewsString = $todayViewsNodeList->item(0)->textContent;
            $todayViews = $this->getMatchedDigit($todayViewsString);
            if ($todayViews !== null) {
                $result['today_views'] = $todayViews;
            }
        }

        return $result;
    }

    private function getMatchedDigit(string $str): ?int
    {
        $matches = [];
        preg_match('/\d+/', $str, $matches);
        return $matches[0] ?? null;
    }
}
