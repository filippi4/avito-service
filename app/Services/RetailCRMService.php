<?php

namespace App\Services;

use App\Exports\OzonOrdersCostExport;
use App\Exports\WbOrdersCostExport;
use App\Exports\YandexMarketOrdersCostExport;
use Google\Service\SQLAdmin\ExportContextSqlExportOptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class RetailCRMService
{
    public const ORDERS_START_DATE = '2023-01-01';
    public const AUTOPILOT_ORDERS_SHIPMENT_STORES = 'sklad-ap';
    public const AUTOLEADER_ORDERS_SHIPMENT_STORES = 'sklad-al';
    public const ORDERS_RETURNS_STATUSES = [
        'vozvrat',
        'vozvrat-zachten',
        'vozvrat-clientom',
        'sdannaskladpost',
        'podg-k-vozvratu',
    ];

    public function editOrder(array $order): bool
    {
        // externalId нужен для url запроса
        if (!isset($order['externalId'])) {
            Log::debug(__METHOD__, ['message' => 'Нет externalId у заказа', 'order' => $order]);
            return false;
        }
        $jsonBody = [
            'site' => $order['site'],
            'order' => json_encode($order),
        ];
        $response = Http::timeout(30)
            ->acceptJson()
            ->retry(5, 3000)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-API-KEY' => config('api.retailcrm.key'),
            ])->post("https://totalart.retailcrm.ru/api/v5/orders/{$order['externalId']}/edit", $jsonBody)->json();

        if (!$response['success']) {
            Log::debug(__METHOD__, compact('response', 'order'));
        }
        return $response['success'];
    }

    public function getAllOrders(
        string $createdAtFrom,
        array $extendedStatus = [],
        array $shipmentStores = [],
    ): array
    {
        $currentPage = 1;
        $totalPageCount = 0;

        $data = [];

        while (true) {
            $response = $this->getOrders(
                $createdAtFrom,
                $extendedStatus,
                $shipmentStores,
                $currentPage
            );
            $currentPage = $response['pagination']['currentPage'] ?? $currentPage;
            $totalPageCount = $response['pagination']['totalPageCount'] ?? $totalPageCount;

            $orders = $response['orders'] ?? [];

            if ($currentPage > $totalPageCount || empty($orders)) {
                break;
            }

            $data = array_merge($data, $orders);

            $currentPage++;
        }

        return $data;
    }

    public function getOrdersNumAndMethodAndDateAndDocumentType(string $createdAtFrom, array $shipmentStores): array
    {
        $currentPage = 1;
        $totalPageCount = 0;

        $data = [];

        while (true) {
            $response = $this->getOrders($createdAtFrom, shipmentStores: $shipmentStores, page: $currentPage);
            $currentPage = $response['pagination']['currentPage'] ?? $currentPage;
            $totalPageCount = $response['pagination']['totalPageCount'] ?? $totalPageCount;

            $orders = $response['orders'] ?? [];

            if ($currentPage > $totalPageCount || empty($orders)) {
                break;
            }

            // debug
//            dump("page {$currentPage}/{$totalPageCount}");

            // process
            foreach ($orders as $order) {
                $date = explode(' ', $order['createdAt'] ?? '')[0];
                $method = match ($order['orderMethod'] ?? '') {
                    'shopping-cart' => 'Через корзину',
                    'wildberies' => 'Wildberies',
                    'phone' => 'По телефону',
                    'yandexmarket' => 'Yandex Market',
                    'ozon' => 'Ozon',
                    'aliexpress' => 'Aliexpress',
                    'messenger' => 'Мессенджеры',
                    'ozon-chekhly-ru' => 'Ozon',
                    'ozon-avtoleto' => 'Ozon',
                    'live-chat' => 'Мессенджеры',
                    'ozon-avtodrug' => 'Ozon',
                    'ozon-avtopilot' => 'Ozon',
                    default => '',
                };
                if (isset($order['customFields']['nomzakazpost'])) {
                    $orderNumber = $order['customFields']['nomzakazpost'];
                    $data[] = [
                        $orderNumber,
                        $method,
                        $date,
                        'Реализация',
                    ];
                }
                if (isset($order['customFields']['vozvratnaya_realizacia_nomer'])) {
                    $returnNumber = $order['customFields']['vozvratnaya_realizacia_nomer'];
                    $data[] = [
                        $returnNumber,
                        $method,
                        $date,
                        'Возврат',
                    ];
                }
            }

            $currentPage++;
        }

        return $data;
    }

    private function getOrders(
        string $createdAtFrom = '',
        array $extendedStatus = [],
        array $shipmentStores = [],
        int $page = 1,
        int $limit = 100
    ): array
    {
        $query = http_build_query(array_merge(
            [
                'filter' => compact(
                    'createdAtFrom',
                    'extendedStatus',
                    'shipmentStores',
                )
            ],
            compact('page', 'limit')
        ));
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-API-KEY' => config('api.retailcrm.key'),
        ])
            ->retry(5, 3000)
            ->timeout(30)
            ->get("https://totalart.retailcrm.ru/api/v5/orders", $query)->json();

        if (!$response['success']) {
            Log::debug(__METHOD__, $response);
        }
        return $response;
    }

    public function exportOrdersCostToExcel(array $ordersCostPaths): void
    {
        $from = now()->subDays(30)->toDateString();
        $orders = $this->getAllOrders($from);

        $wbExcelData = $this->prepareWbExcelData($orders);
        if (!empty($wbExcelData)) {
            $this->createWbOrdersExcel($wbExcelData, $ordersCostPaths['wb']);
        }

        $ozonExcelData = $this->prepareOzonExcelData($orders);
        if (!empty($wbExcelData)) {
            $this->createOzonOrdersExcel($ozonExcelData, $ordersCostPaths['ozon']);
        }

        $yandexMarketExcelData = $this->prepareYandexMarketExcelData($orders);
        if (!empty($wbExcelData)) {
            $this->createYandexMarketOrdersExcel($yandexMarketExcelData, $ordersCostPaths['ym']);
        }
    }

    public function prepareWbExcelData(array $orders): array
    {
        $preparedData = [];
        foreach ($orders as $order) {
            if ($order['orderMethod'] !== 'wildberies' ||
                empty($order['customFields']['wb_srid']) ||
                empty($order['customFields']['artikul_postavshchika_wb']) ||
                empty($order['customFields']['price_zakup'])
            ) {
                continue;
            }

            $cost = $order['customFields']['price_zakup']
                + ($order['customFields']['zabor_so_sklada1'] ?? 0)
                + ($order['customFields']['stoimostupakovki'] ?? 0)
                + ($order['customFields']['stoimostetiketki'] ?? 0)
                + ($order['customFields']['ad_dropshiping_price'] ?? 0);

            $preparedData[] = [
                $order['customFields']['wb_srid'],
                $order['customFields']['artikul_postavshchika_wb'],
                $cost,
            ];
        }

        return $preparedData;
    }

    public function prepareOzonExcelData(array $orders): array
    {
        $preparedData = [];
        foreach ($orders as $order) {
            if ($order['orderMethod'] !== 'ozon' ||
                empty($order['externalId']) ||
                empty($order['customFields']['price_zakup']) ||
                empty($order['items'])
            ) {
                continue;
            }

            $cost = $order['customFields']['price_zakup']
                + ($order['customFields']['zabor_so_sklada1'] ?? 0)
                + ($order['customFields']['stoimostupakovki'] ?? 0)
                + ($order['customFields']['stoimostetiketki'] ?? 0)
                + ($order['customFields']['ad_dropshiping_price'] ?? 0);

            foreach ($order['items'] as $item) {
                if (empty($item['offer']['article'])) {
                    continue;
                }

                $preparedData[] = [
                    $order['externalId'],
                    $item['offer']['article'],
                    $cost,
                ];
            }
        }

        return $preparedData;
    }

    public function prepareYandexMarketExcelData(array $orders): array
    {
        $preparedData = [];
        foreach ($orders as $order) {
            if ($order['orderMethod'] !== 'yandexmarket' ||
                (empty($order['externalId']) && empty($order['customer']['phones'][0]['number'])) ||
                empty($order['customFields']['price_zakup']) ||
                empty($order['items'])
            ) {
                continue;
            }

            $cost = $order['customFields']['price_zakup']
                + ($order['customFields']['zabor_so_sklada1'] ?? 0)
                + ($order['customFields']['stoimostupakovki'] ?? 0)
                + ($order['customFields']['stoimostetiketki'] ?? 0)
                + ($order['customFields']['ad_dropshiping_price'] ?? 0);

            $orderNumber = !empty($order['externalId'])
                ? $order['externalId'] : $order['customer']['phones'][0]['number'];

            foreach ($order['items'] as $item) {
                if (empty($item['offer']['article'])) {
                    continue;
                }

                $preparedData[] = [
                    $orderNumber,
                    $item['offer']['article'],
                    $cost,
                ];
            }
        }

        return $preparedData;
    }

    private function createWbOrdersExcel(array $wbExcelData, string $path)
    {
        Excel::store(new WbOrdersCostExport($wbExcelData), $path);
    }

    private function createOzonOrdersExcel(array $ozonExcelData, string $path)
    {
        Excel::store(new OzonOrdersCostExport($ozonExcelData), $path);
    }

    private function createYandexMarketOrdersExcel(array $yandexMarketExcelData, string $path)
    {
        Excel::store(new YandexMarketOrdersCostExport($yandexMarketExcelData), $path);
    }
}
