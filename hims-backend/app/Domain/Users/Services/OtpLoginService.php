<?php

namespace App\Domain\Users\Services;

use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Domain\Users\Models\LoginOtpChallenge;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use LogicException;

class OtpLoginService
{
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    public function createLoginChallenge(string $mobile): void
    {
        $this->assertDemoOtpIsSafe();
        $mobile = PhoneNumber::normalizeIndianMobile($mobile);

        $user = User::query()
            ->where('mobile', $mobile)
            ->where('status', 'active')
            ->first();

        if (! $user) {
            return;
        }

        $demoEnabled = (bool) config('hims.auth.demo_otp_enabled');

        if ($demoEnabled) {
            $codes = config('hims.auth.demo_otp_codes', []);
            $code = $codes[0] ?? null;

            if (! $code) {
                throw new LogicException('Demo OTP is enabled but no demo OTP code is configured.');
            }
        } else {
            if (blank($user->email)) {
                return;
            }

            $length = max(4, min(8, (int) config('hims.auth.otp_length', 6)));
            $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        }

        LoginOtpChallenge::create([
            'user_id' => $user->id,
            'mobile' => $mobile,
            'otp_hash' => Hash::make($code),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes((int) config('hims.auth.otp_ttl_minutes', 5)),
        ]);

        if (! $demoEnabled) {
            $hospitalId = $user->hospitalAssignments()
                ->where('status', 'active')
                ->value('hospital_id');

            $this->notifications->dispatch(
                hospitalId: $hospitalId !== null ? (int) $hospitalId : null,
                branchId: null,
                templateCode: 'auth.login_otp',
                channel: 'email',
                recipient: $user->email,
                context: [
                    'otp' => $code,
                    'user_name' => $user->name,
                    'ttl_minutes' => (int) config('hims.auth.otp_ttl_minutes', 5),
                ],
                userId: $user->id,
            );
        }
    }

    public function consumeLoginChallenge(string $mobile, string $otp): User
    {
        $this->assertDemoOtpIsSafe();
        $mobile = PhoneNumber::normalizeIndianMobile($mobile);

        $challenge = LoginOtpChallenge::query()
            ->with('user')
            ->where('mobile', $mobile)
            ->where('purpose', 'login')
            ->usable()
            ->latest('id')
            ->first();

        $demoCodes = config('hims.auth.demo_otp_enabled')
            ? config('hims.auth.demo_otp_codes', [])
            : [];

        $matchesStored = $challenge && Hash::check($otp, $challenge->otp_hash);
        $matchesDemo = in_array($otp, $demoCodes, true);

        if (! $challenge || (! $matchesStored && ! $matchesDemo) || ! $challenge->user) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired OTP.'],
            ]);
        }

        if ($challenge->user->status !== 'active') {
            throw ValidationException::withMessages([
                'mobile' => ['This account is not active.'],
            ]);
        }

        $challenge->forceFill(['consumed_at' => now()])->save();

        return $challenge->user;
    }

    private function assertDemoOtpIsSafe(): void
    {
        if (app()->environment('production') && config('hims.auth.demo_otp_enabled')) {
            throw new LogicException('Demo OTP must be disabled in production.');
        }
    }
}
