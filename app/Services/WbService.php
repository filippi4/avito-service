<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class WbService
{
    const ADVERT_REJECT_STATUS = 8;
    const ADVERT_SEARCH_TYPE = 6;
    const ADVERT_AUTO_TYPE = 8;
    private const HTTP_RETRY_TIMES = 7;
    private const HTTP_RETRY_MILLISECONDS = 1000;

    public function processUpdateExcluded()
    {
        $excel = Excel::toArray(new \stdClass(), 'AutoRKWBMinusSlova.xlsx', 'ftp');
        $advertsRequiredWords = collect($excel[0])->mapWithKeys(
            fn ($row) => [Str::slug(mb_strtolower($row[0])) => explode(';', mb_strtolower($row[1]))]
        )->toArray();
        $requiredWords = array_column($excel[1], 0);
        $excludedWords = array_column($excel[2], 0);

        $adverts = $this->getAdvertsByParams(type: WbService::ADVERT_AUTO_TYPE);

        $advertKeywordsData = [];
        foreach($adverts as $advert) {
            // debug
            // dump("Getting stats for advert id: {$advert['advertId']}");

            $stats = $this->getAutoAdvertStats($advert['advertId']);
            if (!empty($stats['words']['keywords'])) {
                $advertKeywordsData[$advert['advertId']]['keywords'] = array_column($stats['words']['keywords'], 'keyword');
                $advertKeywordsData[$advert['advertId']]['excluded'] = $stats['words']['excluded'];
                $advertKeywordsData[$advert['advertId']]['required_keywords'] = $advertsRequiredWords[Str::slug(mb_strtolower($advert['name']))];
            }
        }

        $keywordsToMinus = [];
        foreach ($advertKeywordsData as $advertId => $keywordsData) {
            // debug
            // dump("Checked to minus for advert id: {$advertId}");

            $advertRequiredWords = $keywordsData['required_keywords'];
            $oldExcludedWordsDict = array_fill_keys($keywordsData['excluded'], null);
            foreach ($keywordsData['keywords'] as $keyword) {
                if (!isset($oldExcludedWordsDict[$keyword]) &&
                    $this->checkKeywordToMinus($keyword, $requiredWords, $excludedWords, $advertRequiredWords)
                ) {
                    $keywordsToMinus[$advertId][] = $keyword;
                }
            }
        }

        foreach ($keywordsToMinus as $advertId => $excluded) {
            $excluded = array_merge($excluded, $advertKeywordsData[$advertId]['excluded']);
            // debug
            // dump($advertId, json_encode($excluded, JSON_UNESCAPED_UNICODE), count($excluded));
            $result = $this->setAutoAdvertsExcluded($advertId, $excluded);
            if ($result) {
                Log::debug(__METHOD__, ["Updated excluded keywords for advert id: {$advertId}"]);
            }
        }
    }

    public function getAdverts(): array
    {
        $response = Http::withHeader('Authorization', config('api.wildberries.promotion'))
            ->retry(self::HTTP_RETRY_TIMES, self::HTTP_RETRY_MILLISECONDS)
            ->get('https://advert-api.wb.ru/adv/v1/promotion/count');
        if ($response->status() !== 200) {
            Log::error(__METHOD__, $response->handlerStats());
            return [];
        }
        return $response->json();
    }

    public function getAdvertsByParams(array $advertIds = null, int $type = null): array
    {
        $request = Http::withHeader('Authorization', config('api.wildberries.promotion'))
            ->retry(self::HTTP_RETRY_TIMES, self::HTTP_RETRY_MILLISECONDS);
        $url = 'https://advert-api.wb.ru/adv/v1/promotion/adverts';

        $params = http_build_query(compact('type'));
        if (!empty($params)) {
            $url .= '?' . $params;
            $response = $request->post($url);
        } elseif (!empty($advertIds)) {
            $response = $request->post($url, $advertIds);
        } else {
            return [];
        }

        if ($response->status() !== 200) {
            Log::error(__METHOD__, $response->handlerStats());
            return [];
        }
        return $response->json();
    }

    public function getAdvertsInfo(array $adverts): array
    {
        $response = Http::withHeader('Authorization', config('api.wildberries.promotion'))
            ->retry(self::HTTP_RETRY_TIMES, self::HTTP_RETRY_MILLISECONDS)
            ->post('https://advert-api.wb.ru/adv/v1/promotion/adverts', $adverts);
        if ($response->status() !== 200) {
            Log::error(__METHOD__, $response->handlerStats());
            return [];
        }
        return $response->json();
    }

    public function getAdvertStats(int $advertId): array
    {
        $response = Http::withHeader('Authorization', config('api.wildberries.promotion'))
            ->retry(self::HTTP_RETRY_TIMES, self::HTTP_RETRY_MILLISECONDS)
            ->get("https://advert-api.wb.ru/adv/v1/stat/words?id={$advertId}");
        if ($response->status() !== 200) {
            Log::error(__METHOD__, $response->handlerStats());
            return [];
        }
        return $response->json();
    }

    public function getAutoAdvertStats(int $advertId): array
    {
        $response = Http::withHeader('Authorization', config('api.wildberries.promotion'))
            ->retry(self::HTTP_RETRY_TIMES, self::HTTP_RETRY_MILLISECONDS)
            ->get("https://advert-api.wb.ru/adv/v1/auto/stat-words?id={$advertId}");
        if ($response->status() !== 200) {
            Log::error(__METHOD__, $response->handlerStats());
            return [];
        }
        return $response->json();
    }

    public function setAutoAdvertsExcluded(int $advertId, array $excluded): bool
    {
        $response = Http::withHeader('Authorization', config('api.wildberries.promotion'))
            ->retry(self::HTTP_RETRY_TIMES, self::HTTP_RETRY_MILLISECONDS)
            ->post(
                "https://advert-api.wb.ru/adv/v1/auto/set-excluded?id={$advertId}",
                compact('excluded')
            );
        if ($response->status() !== 200) {
            Log::error(__METHOD__, $response->handlerStats());
            return false;
        }
        return true;
    }

    private function checkKeywordToMinus(
        string $keyword,
        array $requiredWords,
        array $excludedWords,
        array $advertRequiredWords
    ): bool
    {
        $keyword = mb_strtolower($keyword);
        // если хоть одно "исключающее" слово есть во фразе, тогда в минус-фразу
        foreach ($excludedWords as $word) {
            if (mb_strpos($keyword, $word) !== false) {
                return true;
            }
        }
        // если хоть одно "обязательное" слово есть во фразе
        foreach ($requiredWords as $word) {
            if (mb_strpos($keyword, $word) !== false) {
                // и если хоть одна "обязательная фраза" есть во фразе, тогда НЕ в минус-фразу
                foreach ($advertRequiredWords as $advertRequiredWord) {
                    if (mb_strpos($keyword, $advertRequiredWord) !== false) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
}
