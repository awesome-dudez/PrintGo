<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
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
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse(
                errorCode: 'UNAUTHORIZED',
                message: 'Unauthenticated.',
                statusCode: Response::HTTP_UNAUTHORIZED
            );
        }

        if (method_exists($user, 'isAdmin') && !$user->isAdmin()) {
            return $this->errorResponse(
                errorCode: 'FORBIDDEN',
                message: 'You do not have administrative access.',
                statusCode: Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
