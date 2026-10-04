<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\PrinterStatusService;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPrinterAvailable
{
    use ApiResponse;

    public function __construct(
        protected PrinterStatusService $printerStatusService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response) $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->printerStatusService->isAvailable()) {
            return $this->errorResponse(
                errorCode: 'PRINTER_UNAVAILABLE',
                message: 'The printer is currently unavailable.',
                statusCode: Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return $next($request);
    }
}
