<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PrinterStatusResource;
use App\Services\PrinterStatusService;
use Illuminate\Http\JsonResponse;

class PrinterStatusController extends Controller
{
    public function __construct(
        protected PrinterStatusService $printerStatusService
    ) {}

    /**
     * Get real-time printer status, paper, ink levels, and current job.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $status = $this->printerStatusService->getStatus();

        return $this->successResponse(
            data: (new PrinterStatusResource($status))->resolve()
        );
    }
}
