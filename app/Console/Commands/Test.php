<?php

namespace App\Console\Commands;

use App\Imports\PfOperationsDenizImport;
use App\Imports\PfOperationsRaifImport;
use App\Imports\PfOperationsSberIncomeImport;
use App\Imports\PfOperationsSberOutcomeImport;
use App\Jobs\FtpExporter;
use App\Jobs\PfOperationsChecker;
use App\Jobs\PfOperationsSender;
use App\Jobs\WbExcludedUpdater;
use App\Models\PfOperation;
use App\Models\Posting;
use App\Models\PostingView;
use App\Services\AvitoDBService;
use App\Services\AvitoParserService;
use App\Services\FtpService;
use App\Services\Google\GoogleSheets;
use App\Services\GoogleService;
use App\Services\PfService;
use App\Services\RetailCRMService;
use App\Services\WbService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
//        dd(app()->make(GoogleService::class)->precessUpdateAutopilotOrdersFromRetailCRM());
//        dd(app()->make(RetailCRMService::class)->getOrdersNumAndMethodAndDateAndDocumentType('2023-01-01', ['sklad-ap']));
//        dd(app()->make(PfService::class)->processUpdateAutopilotAccrualsFromGoogleSheets());
//        dd(app()->make(PfService::class)->processUpdateAutoleaderAccrualsFromGoogleSheets());
//        dd(app()->make(GoogleService::class)->processUpdatePfAccrualsFromExcel());
//        dd(app()->make(FtpService::class)->getAccruals());
//        dd((new GoogleSheets)->read('АЛ - Реализации и Возвраты', 'АП')['result']);

        // Возврат:    Списание -> Зачисление => Баланс Автопилот -> Отгружено в OZON
        // Реализация: Списание -> Зачисление => Отгружено в OZON -> Баланс Автопилот
        // Способ | Контрагент (contrAgentId) | Проект (projectId) | Статья (operationCategoryId)
        // Сайт | Коршунов Алексей Валерьевич ИП, опт ! (7167336) | Чехлы Сайты (867457) | Отгружено Сайты (5947560)
        // Ozon | ИП Коршунов - Oz (7167337) | Чехлы Озон (867456) | Отгружено в OZON (5947558)
        // ВБ | ИП Коршунов - ВБ (7167335) | Чехлы WB (867455) | Отгружено в WB (5947559)
        // Яндекс Маркет | ИП Коршунов - ЯМ (7167338) | Чехлы Яндекс Маркет (867465) | Отгружено в Яндекс Маркет (5947563)
        // Статья (operationCategoryId)
        // Баланс Автопилот (6919203)
        //-- Баланс Автолидер (6817880)
        // Юрлицо (companyId)
        // ИП Коршунов А.В. (130544)
        // Назначение платежа: {Вид документа} {Дата начисления} {Номер} {Контрагент}

        return self::SUCCESS;
    }
}
