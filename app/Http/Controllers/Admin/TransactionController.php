<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionFilterRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TransactionController extends Controller
{
    /**
     * List all transactions with flexible filtering, sorting, and pagination.
     *
     * @param TransactionFilterRequest $request
     * @return JsonResponse
     */
    public function index(TransactionFilterRequest $request): JsonResponse
    {
        $query = Transaction::query()->with(['session', 'printJob.uploadedFile']);

        // Date range filter
        if ($dateFrom = $request->input('date_from')) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo = $request->input('date_to')) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        // File type filter
        if ($fileType = $request->input('file_type')) {
            $query->where('file_type', $fileType);
        }

        // Amount filters
        if ($amountMin = $request->input('amount_min')) {
            $query->where('amount', '>=', (float) $amountMin);
        }
        if ($amountMax = $request->input('amount_max')) {
            $query->where('amount', '<=', (float) $amountMax);
        }

        // Pages filters
        if ($pagesMin = $request->input('pages_min')) {
            $query->where('page_count', '>=', (int) $pagesMin);
        }
        if ($pagesMax = $request->input('pages_max')) {
            $query->where('page_count', '<=', (int) $pagesMax);
        }

        // Sorting
        $sort = $request->input('sort', 'created_at_desc');
        match ($sort) {
            'created_at_asc' => $query->orderBy('created_at', 'asc'),
            'amount_asc' => $query->orderBy('amount', 'asc'),
            'amount_desc' => $query->orderBy('amount', 'desc'),
            'pages_asc' => $query->orderBy('page_count', 'asc'),
            'pages_desc' => $query->orderBy('page_count', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->paginate($perPage);

        $data = [
            'transactions' => TransactionResource::collection($paginated->items())->resolve(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ];

        return $this->successResponse(data: $data);
    }

    /**
     * View a single transaction details.
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show(int|string $id): JsonResponse
    {
        $transaction = Transaction::query()
            ->with(['session', 'printJob.uploadedFile'])
            ->find($id);

        if (!$transaction) {
            return $this->errorResponse(
                errorCode: 'TRANSACTION_NOT_FOUND',
                message: 'Transaction not found.',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        return $this->successResponse(
            data: (new TransactionResource($transaction))->resolve()
        );
    }
}
