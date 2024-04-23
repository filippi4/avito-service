<?php

namespace App\Jobs;

use App\Services\ExportService;
use App\Services\RetailCRMService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportOrdersCostToExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(RetailCRMService $retailCRMService): void
    {
        $retailCRMService->exportOrdersCostToExcel(ExportService::getOrderCostPaths());
    }
}
