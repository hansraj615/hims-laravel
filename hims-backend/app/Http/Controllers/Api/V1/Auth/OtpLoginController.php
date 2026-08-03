<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Users\Services\OtpLoginService;
use App\Domain\Users\Services\SessionHistoryRecorder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Http\Resources\AuthUserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OtpLoginController extends Controller
{
    public function requestOtp(OtpRequest $request, OtpLoginService $otpLogin): JsonResponse
    {
        $otpLogin->createLoginChallenge($request->validated('mobile'));

        return ApiResponse::success(
            request: $request,
            message: 'If this mobile number is registered, an OTP will be sent.',
        );
    }

    public function verify(OtpVerifyRequest $request, OtpLoginService $otpLogin, SessionHistoryRecorder $sessionHistory): JsonResponse
    {
        $validated = $request->validated();
        $user = $otpLogin->consumeLoginChallenge($validated['mobile'], $validated['otp']);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        $sessionHistory->recordLogin($request, $user, 'mobile_otp');

        return ApiResponse::success(
            request: $request,
            data: new AuthUserResource($user),
            message: 'Logged in successfully',
        );
    }
}
