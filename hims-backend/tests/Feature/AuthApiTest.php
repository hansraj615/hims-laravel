<?php

use App\Domain\Users\Models\UserSessionHistory;
use App\Domain\Users\Services\OtpLoginService;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;


uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['sanctum.stateful' => ['127.0.0.1:5173']]);
});

function frontend(): TestCase
{
    return test()->withHeaders([
        'Origin' => 'http://127.0.0.1:5173',
        'Accept' => 'application/json',
    ]);
}

it('logs in with email and password and records session history', function () {
    $this->seed(HimsDemoSeeder::class);

    $response = frontend()->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Logged in successfully')
        ->assertJsonPath('data.email', 'admin@example.com');

    expect(UserSessionHistory::query()->where('login_method', 'email_password')->count())->toBe(1);
});

it('rejects invalid email credentials', function () {
    $this->seed(HimsDemoSeeder::class);

    $response = frontend()->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

it('returns the authenticated user', function () {
    $this->seed(HimsDemoSeeder::class);

    $user = User::where('email', 'doctor@example.com')->firstOrFail();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/auth/me');

    $response
        ->assertOk()
        ->assertJsonPath('data.email', 'doctor@example.com');

    expect($response->json('data.roles'))->toContain('doctor');
});

it('logs out the current device and marks session history', function () {
    $this->seed(HimsDemoSeeder::class);

    frontend()->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->assertOk();

    $response = frontend()->postJson('/api/v1/auth/logout');

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully');

    expect(UserSessionHistory::query()->whereNotNull('logged_out_at')->count())->toBe(1);
});

it('logs in using configured demo OTP in non-production', function () {
    config([
        'hims.auth.demo_otp_enabled' => true,
        'hims.auth.demo_otp_codes' => ['1234', '123'],
    ]);

    $this->seed(HimsDemoSeeder::class);

    frontend()->postJson('/api/v1/auth/otp/request', [
        'mobile' => '9900000003',
    ])->assertOk();

    $response = frontend()->postJson('/api/v1/auth/otp/verify', [
        'mobile' => '9900000003',
        'otp' => '123',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.email', 'reception@example.com');

    expect(UserSessionHistory::query()->where('login_method', 'mobile_otp')->count())->toBe(1);
});

it('locks the account after repeated failed login attempts', function () {
    $this->seed(HimsDemoSeeder::class);

    config(['hims.auth.max_login_attempts' => 3, 'hims.auth.lockout_minutes' => 15]);

    for ($i = 0; $i < 3; $i++) {
        frontend()->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    $user = User::where('email', 'admin@example.com')->firstOrFail();
    expect($user->refresh()->locked_at)->not->toBeNull();

    $response = frontend()->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonFragment(['email' => ['This account is temporarily locked due to multiple failed login attempts. Please try again later.']]);
});

it('clears failed attempts and lock state after a successful login', function () {
    $this->seed(HimsDemoSeeder::class);

    frontend()->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ])->assertUnprocessable();

    $response = frontend()->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response->assertOk();

    expect(User::where('email', 'admin@example.com')->firstOrFail()->locked_at)->toBeNull();
});

it('does not allow demo OTP in production', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['hims.auth.demo_otp_enabled' => true]);

    app(OtpLoginService::class)->createLoginChallenge('+919900000003');
})->throws(LogicException::class, 'Demo OTP must be disabled in production.');

it('sends a password reset link for a registered email without revealing unknown emails', function () {
    $this->seed(HimsDemoSeeder::class);
    Notification::fake();

    frontend()->postJson('/api/v1/auth/password/forgot', [
        'email' => 'admin@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'If that email is registered, password reset instructions have been sent.');

    Notification::assertSentTo(
        User::where('email', 'admin@example.com')->firstOrFail(),
        ResetPasswordNotification::class,
    );

    frontend()->postJson('/api/v1/auth/password/forgot', [
        'email' => 'missing@example.com',
    ])->assertOk();
});

it('resets a password with a valid token', function () {
    $this->seed(HimsDemoSeeder::class);

    $user = User::where('email', 'admin@example.com')->firstOrFail();
    $token = Password::broker()->createToken($user);

    frontend()->postJson('/api/v1/auth/password/reset', [
        'email' => 'admin@example.com',
        'token' => $token,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Password has been reset successfully.');

    frontend()->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'NewPassword123!',
    ])->assertOk();
});
