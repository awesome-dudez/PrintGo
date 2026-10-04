<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PaymentResource;
use App\Models\PrintingSession;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Get payment status for a specific printing session.
     *
     * @param string $sessionId
     * @return JsonResponse
     */
    public function show(string $sessionId): JsonResponse
    {
        $session = PrintingSession::query()->where('id', $sessionId)->first();

        if (!$session) {
            return $this->errorResponse(
                errorCode: 'SESSION_NOT_FOUND',
                message: 'Printing session not found.',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        $payment = $this->paymentService->getPaymentForSession($session->id);

        if (!$payment) {
            $payment = $this->paymentService->createOrUpdatePayment($session->id, 0.00);
        }

        return $this->successResponse(
            data: (new PaymentResource($payment))->resolve()
        );
    }
}

