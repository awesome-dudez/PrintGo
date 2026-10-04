<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Authenticate an admin user and generate a Sanctum personal access token.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $login = (string) ($request->input('login') ?? $request->input('email') ?? $request->input('username'));
        $password = (string) $request->input('password');

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return $this->errorResponse(
                errorCode: 'INVALID_CREDENTIALS',
                message: 'Invalid credentials.',
                statusCode: Response::HTTP_UNAUTHORIZED
            );
        }

        if (method_exists($user, 'isAdmin') && !$user->isAdmin()) {
            return $this->errorResponse(
                errorCode: 'FORBIDDEN',
                message: 'Access restricted to administrators.',
                statusCode: Response::HTTP_FORBIDDEN
            );
        }

        $token = $user->createToken('admin-api-token')->plainTextToken;

        AuditLog::log('admin_login', ['identifier' => $login], $user->id);

        return $this->successResponse(
            data: [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $user->role ?? 'admin',
                ],
            ],
            message: 'Authenticated successfully.'
        );
    }

    /**
     * Revoke the authenticated user's current access token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()?->delete();
            AuditLog::log('admin_logout', null, $user->id);
        }

        return $this->successResponse(
            data: [],
            message: 'Logged out successfully.'
        );
    }
}
