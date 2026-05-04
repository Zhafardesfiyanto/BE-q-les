<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Sanctum\NewAccessToken;

class AuthService
{
    /**
     * Register a new user with email and password.
     *
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    public function register(array $data): array
    {
        // Note: Validation is already handled by RegisterRequest Form Request
        // This method assumes data has been validated
        
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => UserRole::Murid, // Default role is Murid
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Login with email and password credentials.
     *
     * @param string $email
     * @param string $password
     * @return array
     * @throws ValidationException
     */
    public function loginWithCredentials(string $email, string $password): array
    {
        // Note: Basic validation is already handled by LoginRequest Form Request
        // This method assumes email and password are non-empty strings
        
        // Check if user exists
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ])->status(401);
        }

        // Check if user has a password (not a Google-only user)
        if (!$user->password) {
            throw ValidationException::withMessages([
                'email' => ['This account uses Google login. Please login with Google instead.'],
            ])->status(401);
        }

        if (!Auth::attempt(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ])->status(401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Login or register with Google OAuth token.
     *
     * @param string $googleToken
     * @return array
     * @throws ValidationException
     */
    public function loginWithGoogle(string $googleToken): array
    {
        // Note: Basic validation is already handled by GoogleAuthRequest Form Request
        // This method assumes googleToken is a non-empty string
        
        try {
            // Verify Google token using Socialite
            $googleUser = Socialite::driver('google')->userFromToken($googleToken);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or expired Google token.'],
            ])->status(401);
        }

        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();

        // First, try to find user by google_id
        $user = User::where('google_id', $googleId)->first();

        if (!$user) {
            // If not found by google_id, try by email
            $user = User::where('email', $email)->first();

            if ($user) {
                // User exists with email but different google_id
                // Check if google_id is already taken by another user
                $existingUserWithGoogleId = User::where('google_id', $googleId)->first();
                if ($existingUserWithGoogleId) {
                    // This should not happen in normal cases, but handle it gracefully
                    throw ValidationException::withMessages([
                        'token' => ['This Google account is already associated with another user.'],
                    ])->status(409);
                }
                
                // Update existing user with google_id
                $user->google_id = $googleId;
                $user->save();
            } else {
                // Create new user if not found
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $email,
                    'google_id' => $googleId,
                    'role' => UserRole::Murid, // Default role is Murid
                    'avatar_url' => $googleUser->getAvatar(),
                ]);
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Logout the user by revoking the current access token.
     *
     * @param User $user
     * @return void
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}