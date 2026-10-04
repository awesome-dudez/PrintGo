<?php

declare(strict_types=1);

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\CheckPrinterAvailable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.only' => AdminOnly::class,
            'check.maintenance' => CheckMaintenanceMode::class,
            'check.printer' => CheckPrinterAvailable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Validation Exception
        $exceptions->render(function (ValidationException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => $e->validator->errors()->first() ?: 'The given data was invalid.',
                        'details' => $e->errors(),
                    ],
                    'errors' => $e->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            return null;
        });

        // Authentication Exception
        $exceptions->render(function (AuthenticationException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'Unauthenticated.',
                    ],
                ], Response::HTTP_UNAUTHORIZED);
            }
            return null;
        });

        // Authorization Exception
        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => $e->getMessage() ?: 'Forbidden.',
                    ],
                ], Response::HTTP_FORBIDDEN);
            }
            return null;
        });

        // Model / Route Not Found
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'The requested resource was not found.',
                    ],
                ], Response::HTTP_NOT_FOUND);
            }
            return null;
        });

        // Method Not Allowed
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'METHOD_NOT_ALLOWED',
                        'message' => 'The requested HTTP method is not supported for this route.',
                    ],
                ], Response::HTTP_METHOD_NOT_ALLOWED);
            }
            return null;
        });

        // Rate Limiting / Throttle
        $exceptions->render(function (ThrottleRequestsException|TooManyRequestsHttpException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TOO_MANY_REQUESTS',
                        'message' => 'Too many requests. Please try again later.',
                    ],
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }
            return null;
        });

        // Generic HTTP Exception
        $exceptions->render(function (HttpExceptionInterface $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'HTTP_ERROR',
                        'message' => $e->getMessage() ?: 'An HTTP error occurred.',
                    ],
                ], $e->getStatusCode());
            }
            return null;
        });

        // General Throwable Exception (Server Error)
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                $isLocal = app()->environment('local', 'testing');
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SERVER_ERROR',
                        'message' => $isLocal ? $e->getMessage() : 'An unexpected server error occurred.',
                    ],
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            return null;
        });
    })->create();
