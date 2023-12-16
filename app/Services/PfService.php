<?php

namespace App\Services;

use App\Enums\Status;
use App\Jobs\PfOperationsSender;
use App\Models\PfOperation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PfService
{
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
}
