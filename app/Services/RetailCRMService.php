<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

            dump("page {$currentPage}/{$totalPageCount}");

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
}
