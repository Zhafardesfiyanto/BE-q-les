<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\GoogleAuthRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * The auth service instance.
     *
     * @var AuthService
     */
    protected $authService;

    /**
     * Create a new controller instance.
     *
     * @param AuthService $authService
     * @return void
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user.
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->validated());

            return response()->json([
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'role' => $result['user']->role->value,
                    'avatar_url' => $result['user']->avatar_url,
                ],
                'token' => $result['token'],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Login with email and password.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->loginWithCredentials(
                $request->input('email'),
                $request->input('password')
            );

            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'role' => $result['user']->role->value,
                    'avatar_url' => $result['user']->avatar_url,
                ],
                'token' => $result['token'],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Authentication failed',
                'errors' => $e->errors(),
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Login or register with Google OAuth token.
     *
     * @param GoogleAuthRequest $request
     * @return JsonResponse
     */
    public function google(GoogleAuthRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->loginWithGoogle($request->input('token'));

            return response()->json([
                'message' => 'Google authentication successful',
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'role' => $result['user']->role->value,
                    'avatar_url' => $result['user']->avatar_url,
                ],
                'token' => $result['token'],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Google authentication failed',
                'errors' => $e->errors(),
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Google authentication failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Logout the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request->user());

            return response()->json([
                'message' => 'Logout successful',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Logout failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}