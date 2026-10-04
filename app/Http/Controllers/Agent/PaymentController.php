<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmPaymentRequest;
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
     * Handle coin/bill acceptor payment webhook from hardware agent.
     *
     * @param ConfirmPaymentRequest $request
     * @return JsonResponse
     */
    public function confirm(ConfirmPaymentRequest $request): JsonResponse
    {
        $sessionId = (string) $request->input('session_id');
        $amount = (float) $request->input('amount');
        $source = (string) $request->input('source', 'coin');

        /** @var PrintingSession|null $session */
        $session = PrintingSession::query()
            ->where('id', $sessionId)
            ->first();

        if (!$session) {
            return $this->errorResponse(
                errorCode: 'SESSION_NOT_FOUND',
                message: 'Printing session not found.',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        $payment = $this->paymentService->recordPayment(
            sessionId: $session->id,
            amountInserted: $amount,
            source: $source
        );

        return $this->successResponse(
            data: (new PaymentResource($payment))->resolve(),
            message: "Payment of PHP {$amount} confirmed."
        );
    }
}

