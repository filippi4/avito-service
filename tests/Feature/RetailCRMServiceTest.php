<?php

namespace Tests\Feature;

use App\Services\ExportService;
use App\Services\RetailCRMService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RetailCRMServiceTest extends TestCase
{
    private array $paths;
    private RetailCRMService $retailCRMService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->retailCRMService = app('App\Services\RetailCRMService');
        $this->paths = ExportService::getOrderCostPaths('test-');
    }

    /**
     * A basic feature test example.
     */
    public function test_export_orders_to_excel(): void
    {
        $this->retailCRMService->exportOrdersCostToExcel($this->paths);

        foreach ($this->paths as $path) {
            $this->assertTrue(Storage::fileExists($path));
        }
    }

    public function test_prepare_wb_excel_data()
    {
        $orders = [
            [
                "orderMethod" => "wildberies",
                "items" => [
                    [
                        "properties" => [
                            "wb_nm_id" => [
                                "code" => "wb_nm_id",
                                "name" => "Артикул WB",
                                "value" => "213986637",
                            ],
                        ],
                    ],
                ],
                "customFields" => [
                    "wb_srid" => "8702389362736228545.0.0",
                    "price_zakup" => 4840,
                    "nomzakazpost" => "253442",
                    "wb_uderzhaniia1" => 3149.4,
                    "artikul_avtodrug" => "HY15-1401-EC02",
                    "nomsborochnogowb" => "1637109377",
                    "stoimostetiketki" => 4,
                    "dostavka_vozvrata" => 0,
                    "ad_dropshiping_price" => 300,
                    "artikul_prodavtsa_wb" => "4478AV909FT",
                    "vneshniy_nomer_zakaza" => "1637109377",
                    "artikul_proizvoditelia" => "4478AV38409FT",
                    "artikul_postavshchika_wb" => "213986637",
                ],
            ],
        ];
        $expectedWbExelData = [["8702389362736228545.0.0", "213986637", 5144]];

        $wbExcelData = $this->retailCRMService->prepareWbExcelData($orders);
        $this->assertEquals($expectedWbExelData, $wbExcelData);
    }

    public function test_prepare_ozon_excel_data()
    {
        $orders = [
            [
                "orderMethod" => "ozon",
                "externalId" => "0117779605-0022-1",
                "items" => [
                    [
                        "offer" => [
                            "id" => 465825,
                            "externalId" => "90497",
                            "xmlId" => "90497",
                            "article" => "90497",
                        ]
                    ],
                    [
                        "offer" => [
                            "id" => 465826,
                            "externalId" => "90498",
                            "xmlId" => "90498",
                            "article" => "90498",
                        ]
                    ],
                ],
                "customFields" => [
                    "price_zakup" => 6700,
                    "profit_summ" => 2153.27,
                    "nomzakazpost" => "845527",
                    "dlia_aktivatora" => true,
                    "stoimostupakovki" => 40,
                    "zabor_so_sklada1" => 200,
                    "artikul_avtopilot" => "vo-po-ps-chch-ar",
                    "dostavka_vozvrata" => 0,
                ]
            ],
        ];
        $expectedWbExelData = [["0117779605-0022-1","90497",6940],["0117779605-0022-1","90498",6940]];

        $ozonExcelData = $this->retailCRMService->prepareOzonExcelData($orders);
        $this->assertEquals($expectedWbExelData, $ozonExcelData);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            Storage::delete($path);
        }

        parent::tearDown();
    }
}
