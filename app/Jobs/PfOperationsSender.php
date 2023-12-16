<?php

namespace App\Jobs;

use App\Models\PfOperation;
use App\Services\PfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PfOperationsSender implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private PfOperation $operation;

    /**
     * Create a new job instance.
     */
    public function __construct(PfOperation $operation)
    {
//        $this->onQueue('pf_operations_checker');

        $this->operation = $operation;
    }

    /**
     * Execute the job.
     */
    public function handle(PfService $pfService): void
    {
        $pfService->processSendingOperations($this->operation);
    }
}
