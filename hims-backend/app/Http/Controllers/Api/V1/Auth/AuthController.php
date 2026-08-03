<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Users\Services\SessionHistoryRecorder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailLoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(EmailLoginRequest $request, SessionHistoryRecorder $sessionHistory): JsonResponse
    {
        $credentials = $request->validated();
        $email = $credentials['email'];
        $failuresKey = "login_failures:{$email}";

        $existingUser = User::where('email', $email)->first();

        if ($existingUser?->locked_at !== null
            && $existingUser->locked_at->gt(now()->subMinutes((int) config('hims.auth.lockout_minutes', 15)))
        ) {
            throw ValidationException::withMessages([
                'email' => ['This account is temporarily locked due to multiple failed login attempts. Please try again later.'],
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $credentials['password'], 'status' => 'active'])) {
            $this->registerFailedAttempt($existingUser, $failuresKey);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        Cache::forget($failuresKey);

        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now(), 'locked_at' => null])->save();
        $sessionHistory->recordLogin($request, $user, 'email_password');

        return ApiResponse::success(
            request: $request,
            data: new AuthUserResource($user),
            message: 'Logged in successfully',
        );
    }

    private function registerFailedAttempt(?User $user, string $failuresKey): void
    {
        if (! $user) {
            return;
        }

        $maxAttempts = (int) config('hims.auth.max_login_attempts', 5);
        $lockoutMinutes = (int) config('hims.auth.lockout_minutes', 15);
        $attempts = (int) Cache::get($failuresKey, 0) + 1;

        if ($attempts >= $maxAttempts) {
            $user->forceFill(['locked_at' => now()])->save();
            Cache::forget($failuresKey);

            return;
        }

        Cache::put($failuresKey, $attempts, now()->addMinutes($lockoutMinutes));
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            request: $request,
            data: new AuthUserResource($request->user()),
            message: 'Authenticated user loaded',
        );
    }

    public function logout(Request $request, SessionHistoryRecorder $sessionHistory): JsonResponse
    {
        $user = $request->user();

        $sessionHistory->recordCurrentLogout($request, $user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(
            request: $request,
            message: 'Logged out successfully',
        );
    }

    public function logoutAll(Request $request, SessionHistoryRecorder $sessionHistory): JsonResponse
    {
        $user = $request->user();

        DB::table('sessions')->where('user_id', $user->id)->delete();
        $sessionHistory->recordAllLogout($user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(
            request: $request,
            message: 'Logged out from all devices successfully',
        );
    }
}
