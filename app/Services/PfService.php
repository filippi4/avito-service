<?php

namespace App\Services;

use App\Enums\Status;
use App\Jobs\PfOperationsSender;
use App\Models\PfOperation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PfService
{
    private const IP_KORSHUNOV_A_V_COMPANY_ID = 130544;
    private const BALANCE_AUTOPILOT_OPERATION_CATEGORY_ID = 6919203;
    private const BALANCE_AUTOLEADER_OPERATION_CATEGORY_ID = 6817880;
    private const METHODS = ['Ozon', 'Wildberies', 'Yandex Market', 'Мессенджеры', 'Через корзину', 'По телефону'];

    private GoogleService $googleService;
    private FtpService $ftpService;

    public function __construct(GoogleService $googleService, FtpService $ftpService)
    {
        $this->googleService = $googleService;
        $this->ftpService = $ftpService;
    }

    public function processCheckingOperations()
    {
        $pendingOperation = PfOperation::query()->where('status', Status::Pending)->get();

        foreach ($pendingOperation as $operation) {
            PfOperationsSender::dispatch($operation);
        }
    }

    public function processSendingOperations(PfOperation $operation)
    {
        $result = $this->sendOperation($operation);
        $operation->update(['status' => ($result) ? Status::Completed : Status::Failed]);
    }

    private function sendOperation(PfOperation $operation): bool
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-ApiKey' => config('api.planfact.key'),
        ])->post("https://api.planfact.io/api/v1/operations/{$operation->operation_type}", [
            'operationDate' => $operation->operation_date,
            'isCalculationCommitted' => $operation->is_calculation_committed,
            'isCommitted' => $operation->is_committed,
            'accountId' => $operation->account_id,
            'value' => $operation->value,
            'comment' => $operation->comment
        ])->json();

        if (!$response['isSuccess']) {
            Log::debug(__METHOD__, $response);
        }
        return $response['isSuccess'];
    }

    public function processUpdateAutopilotAccrualsFromGoogleSheets()
    {
        $data = $this->googleService->read(
            GoogleService::PF_AUTOPILOT_ACCRUALS_SPREADSHEET,
            GoogleService::PF_AUTOPILOT_ACCRUALS_TAB,
        );

        $notUpdatedRowsIncrementChecker = 0;

        $rows = array_slice($data, 1);
        foreach ($rows as $index => &$row) {
            $sum = $row[4] ?? 0;

            if ($sum <= 0) {
                continue;
            }

            // test
//            if ($index > 10) {
//                continue;
//            }

            if ($row[9] ?? '' === 'Нет') {
                $notUpdatedRowsIncrementChecker++;

                $method = $row[6];

                // пропускает неизвестные 'способы'
                if (!in_array($method, self::METHODS)) {
                    $notUpdatedRowsIncrementChecker--;
                    continue;
                }

                $documentType = $row[1];
                [$income, $outcome] = $this->getIncomeAndOutcomeOperationCategoryId(
                    $documentType,
                    $method,
                    self::BALANCE_AUTOPILOT_OPERATION_CATEGORY_ID
                );
                $contrangentName = $this->getContragentNameByMethod($method);
                $accrual = [
                    'calculationDate' => Carbon::parse($row[3])->format('Y-m-d'),
                    'isCalculationCommitted' => true,
                    'companyId' => self::IP_KORSHUNOV_A_V_COMPANY_ID,
                    'outcomeOperationCategoryId' => $income,
                    'incomeOperationCategoryId' => $outcome,
                    'incomeProjectId' => $this->getProjectIdByMethod($method),
                    'comment' => "{$documentType} {$row[3]} {$row[2]} {$contrangentName}",
                    'value' => $sum,
                    'contrAgentId' => $this->getContragentIdByName($contrangentName),
                    'currencyCode' => 'RUB',
                ];
                $isUpdated = $this->sendAccrualOperation($accrual);
                // debug
//                dump('"' . $accrual['comment'] . '" is updated: ' . ($isUpdated ? 'yes' : 'no'));
                if ($isUpdated) {
                    $row[9] = 'Да';
                } else {
                    $notUpdatedRowsIncrementChecker--;
                }
            }
        }

        if ($notUpdatedRowsIncrementChecker === 0) {
            return;
        }

        $values = array_map(fn ($item) => [$item], array_column($rows, 9));
        $this->googleService->update(
            GoogleService::PF_AUTOPILOT_ACCRUALS_SPREADSHEET,
            GoogleService::PF_AUTOPILOT_ACCRUALS_TAB,
            $values,
            'J2:J'
        );
    }

    public function processUpdateAutoleaderAccrualsFromGoogleSheets()
    {
        $googleSheetsData = $this->googleService->read(
            GoogleService::PF_AUTOLEADER_ACCRUALS_SPREADSHEET,
            GoogleService::PF_AUTOLEADER_ACCRUALS_TAB
        );

        // Увеличивает счетчик не загруженных строк до отправки в пф,
        // если не отправится счетчик уменьшится.
        // Если в итоге равен 0, значит кол-во не загрежнных данных не поенялось,
        // следовательно обновлять google файл не нужно.
        $notUpdatedRowsIncrementChecker = 0;

        $rows = array_slice($googleSheetsData, 1);
        foreach ($rows as $index => &$row) {
            // test
//            if ($index > 70) {
//                continue;
//            }

            if ($row[8] === 'Нет') {
                $notUpdatedRowsIncrementChecker++;

                $sum = $row[4];
                $method = $this->getMethodByExcelMethod($row[6]);
                $documentType = $row[1];
                [$income, $outcome] = $this->getIncomeAndOutcomeOperationCategoryId(
                    $documentType,
                    $method,
                    self::BALANCE_AUTOLEADER_OPERATION_CATEGORY_ID
                );
                $contrangentName = $this->getContragentNameByMethod($method);
                $accrual = [
                    'calculationDate' => Carbon::parse($row[3])->format('Y-m-d'),
                    'isCalculationCommitted' => true,
                    'companyId' => self::IP_KORSHUNOV_A_V_COMPANY_ID,
                    'outcomeOperationCategoryId' => $income,
                    'incomeOperationCategoryId' => $outcome,
                    'incomeProjectId' => $this->getProjectIdByMethod($method),
                    'comment' => "{$documentType} {$row[3]} {$row[2]} {$contrangentName}",
                    'value' => $sum,
                    'contrAgentId' => $this->getContragentIdByName($contrangentName),
                    'currencyCode' => 'RUB',
                ];
//                dump($accrual);
                $isUpdated = $this->sendAccrualOperation($accrual);
//                dump($isUpdated);
                if ($isUpdated) {
                    $row[8] = 'Да';
                } else {
                    $notUpdatedRowsIncrementChecker--;
                }
            }
        }

        if ($notUpdatedRowsIncrementChecker === 0) {
            return;
        }

        $values = [$googleSheetsData[0], ...$rows];
        $this->googleService->update(
            GoogleService::PF_AUTOLEADER_ACCRUALS_SPREADSHEET,
            GoogleService::PF_AUTOLEADER_ACCRUALS_TAB,
            $values
        );
    }

    private function getMethodByExcelMethod(string $excelMethod)
    {
        return match($excelMethod) {
            'ИП Коршунов - Oz' => 'Ozon',
            'ИП Коршунов - ВБ' => 'Wildberies',
            'ИП Коршунов - ЯМ' => 'Yandex Market',
            'Коршунов Алексей Валерьевич ИП, опт !' => 'Мессенджеры', // или 'Через корзину', 'По телефону'
        };
    }

    public function getOperationCategoryIdByMethod(string $method)
    {
        return match($method) {
            'Ozon' => 5947558, // Отгружено в OZON
            'Wildberies' => 5947559, // Отгружено в WB
            'Yandex Market' => 5947563, // Отгружено в Яндекс Маркет
            'Мессенджеры', 'Через корзину', 'По телефону' => 5947560, // Отгружено Сайты
        };
    }

    public function getIncomeAndOutcomeOperationCategoryId(
        string $documentType,
        string $method,
        string $balanceOperationCategoryId,
    )
    {
        if ($documentType === 'Возврат') {
            return [$balanceOperationCategoryId, $this->getOperationCategoryIdByMethod($method)];
        } else {
            // Реализация
            return [$this->getOperationCategoryIdByMethod($method), $balanceOperationCategoryId];
        }
    }

    public function getContragentNameByMethod(string $method)
    {
        return match($method) {
            'Ozon' => 'ИП Коршунов - Oz',
            'Wildberies' => 'ИП Коршунов - ВБ',
            'Yandex Market' => 'ИП Коршунов - ЯМ',
            'Мессенджеры', 'Через корзину', 'По телефону' => 'Коршунов Алексей Валерьевич ИП, опт !',
        };
    }

    public function getContragentIdByName(string $name)
    {
        return match($name) {
            'Коршунов Алексей Валерьевич ИП, опт !' => 7167336,
            'ИП Коршунов - Oz' => 7167337,
            'ИП Коршунов - ВБ' => 7167335,
            'ИП Коршунов - ЯМ' => 7167338,
        };
    }

    public function getProjectIdByMethod(string $method)
    {
        return match($method) {
            'Ozon' => 867456, // Чехлы Озон
            'Wildberies' => 867455, // Чехлы WB
            'Yandex Market' => 867465, // Чехлы Яндекс Маркет
            'Мессенджеры', 'Через корзину', 'По телефону' => 867457, // Чехлы Сайты
        };
    }

    private function sendAccrualOperation(array $accrual): bool
    {
        $response = Http::timeout(30)
            ->retry(5, 3000)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-ApiKey' => config('api.planfact.key'),
            ])->post("https://api.planfact.io/api/v1/operations/accrual", [
                'calculationDate' => $accrual['calculationDate'],
                'isCalculationCommitted' => $accrual['isCalculationCommitted'],
                'companyId' => $accrual['companyId'],
                'outcomeOperationCategoryId' => $accrual['outcomeOperationCategoryId'],
                'incomeOperationCategoryId' => $accrual['incomeOperationCategoryId'],
                'incomeProjectId' => $accrual['incomeProjectId'],
                'comment' => $accrual['comment'],
                'value' => $accrual['value'],
                'contrAgentId' => $accrual['contrAgentId'],
                'currencyCode' => $accrual['currencyCode'],
            ])->json();

        if (!$response['isSuccess']) {
            Log::debug(__METHOD__, $response);
        }
        return $response['isSuccess'];
    }
}
