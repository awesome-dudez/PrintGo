<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\MaintenanceConfiguration;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response) $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (MaintenanceConfiguration::isEnabled()) {
            $message = MaintenanceConfiguration::getMessage();

            return $this->errorResponse(
                errorCode: 'MAINTENANCE_MODE',
                message: $message ?: 'Printing is currently unavailable.',
                statusCode: Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return $next($request);
    }
}
