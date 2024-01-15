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
    private const METHODS = ['Ozon', 'Wildberies', 'Yandex Market', 'Мессенджеры', 'Через корзину', 'По телефону'];
    private GoogleService $googleService;

    public function __construct(GoogleService $googleService)
    {
        $this->googleService = $googleService;
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

    public function updateAccrualsFromGoogleSheets()
    {
        $data = $this->googleService->getPfAccruals();
        $rows = array_slice($data, 1);

        foreach ($rows as $index => &$row) {
            $sum = $row[4];

            if ($sum <= 0) {
                continue;
            }

            if ($row[8] === 'Нет') {
                $method = $row[6];

                // пропускает неизвеные 'способы'
                if (!in_array($method, self::METHODS)) {
                    continue;
                }

                $documentType = $row[1];
                [$income, $outcome] = $this->getIncomeAndOutcomeOperationCategoryId($documentType, $method);
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
                if ($isUpdated) {
                    $row[8] = 'Да';
                }
            }
        }

        $values = [$data[0], ...$rows];
        $this->googleService->updatePfAccruals($values);
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

    public function getIncomeAndOutcomeOperationCategoryId(string $documentType, string $method)
    {
        if ($documentType === 'Возврат') {
            return [self::BALANCE_AUTOPILOT_OPERATION_CATEGORY_ID, $this->getOperationCategoryIdByMethod($method)];
        } else {
            // Реализация
            return [$this->getOperationCategoryIdByMethod($method), self::BALANCE_AUTOPILOT_OPERATION_CATEGORY_ID];
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
        $response = Http::withHeaders([
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
