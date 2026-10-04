<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    /**
     * Return a standardized success JSON response.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $code
     * @return JsonResponse
     */
    public function successResponse(mixed $data = [], ?string $message = null, int $code = Response::HTTP_OK): JsonResponse
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $code);
    }

    /**
     * Return a standardized error JSON response.
     *
     * @param string $errorCode
     * @param string $message
     * @param int $statusCode
     * @param mixed $details
     * @return JsonResponse
     */
    public function errorResponse(
        string $errorCode,
        string $message,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        mixed $details = null
    ): JsonResponse {
        $error = [
            'code' => $errorCode,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json([
            'success' => false,
            'error' => $error,
        ], $statusCode);
    }
}
