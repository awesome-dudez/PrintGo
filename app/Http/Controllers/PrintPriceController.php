<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CalculatePriceRequest;
use App\Http\Resources\PriceCalculationResource;
use App\Models\UploadedFile;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PrintPriceController extends Controller
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * Calculate authoritative price for document printing.
     *
     * @param CalculatePriceRequest $request
     * @return JsonResponse
     */
    public function calculate(CalculatePriceRequest $request): JsonResponse
    {
        $fileId = $request->input('file_id');
        $file = UploadedFile::query()->find($fileId);

        if (!$file) {
            return $this->errorResponse(
                errorCode: 'FILE_NOT_FOUND',
                message: 'Uploaded file not found.',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        $adjustments = (array) $request->input('adjustments', []);
        $pageRange = $adjustments['page_range'] ?? $request->input('page_range');
        $pageCount = $this->resolveEffectivePageCount($file, $pageRange);

        $calculation = $this->pricingService->calculate(
            pageCount: $pageCount,
            copies: (int) $request->input('copies', 1),
            paperSize: (string) $request->input('paper_size', 'A4'),
            paperType: (string) $request->input('paper_type', 'bond'),
            colorMode: (string) $request->input('color_mode', 'bw'),
            duplex: (bool) $request->input('duplex', false),
            adjustments: $adjustments
        );

        $calculation['file_id'] = $file->id;

        return $this->successResponse(
            data: (new PriceCalculationResource($calculation))->resolve()
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
}
