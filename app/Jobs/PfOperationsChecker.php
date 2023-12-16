<?php

namespace App\Jobs;

use App\Services\PfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PfOperationsChecker implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
//        $this->onQueue('pf_operations_checker');
    }

    /**
     * Execute the job.
     */
    public function handle(PfService $pfService): void
    {
        $pfService->processCheckingOperations();
    }
}
