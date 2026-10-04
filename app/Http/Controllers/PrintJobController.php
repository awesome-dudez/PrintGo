<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreatePrintJobRequest;
use App\Http\Resources\PrintJobResource;
use App\Models\PrintJob;
use App\Models\PrintingSession;
use App\Models\UploadedFile;
use App\Services\PricingService;
use App\Services\PrinterStatusService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PrintJobController extends Controller
{
    public function __construct(
        protected PricingService $pricingService,
        protected PrinterStatusService $printerStatusService
    ) {}

    /**
     * Submit a new print job.
     *
     * @param CreatePrintJobRequest $request
     * @return JsonResponse
     */
    public function store(CreatePrintJobRequest $request): JsonResponse
    {
        $idempotencyKey = $request->input('idempotency_key');

        // Check idempotency
        if ($idempotencyKey) {
            $existingJob = PrintJob::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingJob) {
                return $this->successResponse(
                    data: (new PrintJobResource($existingJob))->resolve(),
                    code: Response::HTTP_OK
                );
            }
        }

        // Validate printer availability
        if (!$this->printerStatusService->isAvailable()) {
            return $this->errorResponse(
                errorCode: 'PRINTER_UNAVAILABLE',
                message: 'The printer is currently unavailable.',
                statusCode: Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        // Find file
        $fileId = $request->input('file_id');
        $file = UploadedFile::query()->find($fileId);

        if (!$file) {
            return $this->errorResponse(
                errorCode: 'FILE_NOT_FOUND',
                message: 'Uploaded file not found.',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        if ($file->status === 'failed') {
            return $this->errorResponse(
                errorCode: 'FILE_PROCESSING_FAILED',
                message: 'The document failed processing and cannot be printed.',
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Resolve or create session
        $session = null;
        $sessionToken = $request->input('session_id');
        if ($sessionToken) {
            $session = PrintingSession::query()
                ->where('id', $sessionToken)
                ->first();
        }

        if (!$session) {
            $session = $file->session ?? PrintingSession::query()->create([
                'status' => 'active',
                'last_activity_at' => now(),
                'expires_at' => now()->addMinutes(config('printing.session_ttl_minutes', 30)),
            ]);
        }

        $adjustments = (array) $request->input('adjustments', []);
        $pageRange = $adjustments['page_range'] ?? $request->input('page_range');
        $pageCount = $this->resolveEffectivePageCount($file, $pageRange);
        if ($pageRange && is_string($pageRange)) {
            $adjustments['page_range'] = $pageRange;
        }

        $copies = (int) $request->input('copies', 1);
        $paperSize = (string) $request->input('paper_size', 'A4');
        $paperType = (string) $request->input('paper_type', 'bond');
        $colorMode = (string) $request->input('color_mode', 'bw');
        $duplex = (bool) $request->input('duplex', false);

        // Calculate authoritative price
        $pricing = $this->pricingService->calculate(
            pageCount: $pageCount,
            copies: $copies,
            paperSize: $paperSize,
            paperType: $paperType,
            colorMode: $colorMode,
            duplex: $duplex,
            adjustments: $adjustments
        );

        $totalAmount = (float) $pricing['total_amount'];

        /** @var PrintJob $job */
        $job = PrintJob::query()->create([
            'session_id' => $session->id,
            'file_id' => $file->id,
            'idempotency_key' => $idempotencyKey,
            'status' => 'queued',
            'copies' => $copies,
            'paper_size' => $paperSize,
            'paper_type' => $paperType,
            'orientation' => (string) $request->input('orientation', 'portrait'),
            'color_mode' => $colorMode,
            'duplex' => $duplex,
            'page_count' => $pageCount,
            'pages_completed' => 0,
            'total_price' => $totalAmount,
            'payment_status' => 'pending',
            'adjustments' => $adjustments,
        ]);

        // Create or update payment required amount for the session
        app(\App\Services\Payment\PaymentService::class)->createOrUpdatePayment(
            sessionId: $session->id,
            requiredAmount: $totalAmount
        );

        return $this->successResponse(
            data: (new PrintJobResource($job))->resolve(),
            code: Response::HTTP_CREATED
        );
    }

    /**
     * Resolve effective pages to print from page range expression.
     */
    private function resolveEffectivePageCount(UploadedFile $file, mixed $pageRange): int
    {
        $totalPages = $file->page_count > 0 ? $file->page_count : 1;

        if (!$pageRange || !is_string($pageRange) || strtolower(trim($pageRange)) === 'all') {
            return $totalPages;
        }

        $pagesSelected = [];
        $parts = explode(',', $pageRange);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (str_contains($part, '-')) {
                [$start, $end] = explode('-', $part, 2);
                $start = (int) trim($start);
                $end = (int) trim($end);
                if ($start > 0 && $end >= $start) {
                    for ($p = $start; $p <= min($end, $totalPages); $p++) {
                        $pagesSelected[$p] = true;
                    }
                }
            } else {
                $p = (int) $part;
                if ($p > 0 && $p <= $totalPages) {
                    $pagesSelected[$p] = true;
                }
            }
        }

        $count = count($pagesSelected);
        return $count > 0 ? $count : $totalPages;
    }

    /**
     * Get print job progress and details.
     *
     * @param int|string $jobId
     * @return JsonResponse
     */
    public function show(int|string $jobId): JsonResponse
    {
        $job = PrintJob::query()->with(['uploadedFile', 'session'])->find($jobId);

        if (!$job) {
            return $this->errorResponse(
                errorCode: 'NOT_FOUND',
                message: 'Print job not found.',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        return $this->successResponse(
            data: (new PrintJobResource($job))->resolve()
        );
    }
}
