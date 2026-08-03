<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Models\NotificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsChannel implements NotificationChannel
{
    public function send(NotificationLog $log): void
    {
        $driver = config('services.sms.driver');
        $apiKey = config('services.sms.api_key');

        if (blank($driver) || blank($apiKey)) {
            Log::info('SMS notification left pending — configure SMS_DRIVER and SMS_API_KEY to deliver.', [
                'notification_log_id' => $log->id,
                'recipient' => $log->recipient,
            ]);

            $log->update([
                'status' => 'pending',
                'provider' => 'sms',
                'provider_response' => ['reason' => 'credentials_not_configured'],
            ]);

            return;
        }

        if ($driver === 'log') {
            Log::info('SMS notification (log driver)', [
                'to' => $log->recipient,
                'body' => $log->body,
            ]);
            $log->update([
                'status' => 'sent',
                'provider' => 'sms:log',
                'provider_response' => ['driver' => 'log'],
            ]);

            return;
        }

        $endpoint = config('services.sms.endpoint');

        if (blank($endpoint)) {
            $log->update([
                'status' => 'pending',
                'provider' => 'sms:'.$driver,
                'provider_response' => ['reason' => 'endpoint_not_configured'],
            ]);

            return;
        }

        $response = Http::withToken((string) $apiKey)->post((string) $endpoint, [
            'to' => $log->recipient,
            'from' => config('services.sms.from'),
            'body' => $log->body,
        ]);

        $log->update([
            'status' => $response->successful() ? 'sent' : 'failed',
            'provider' => 'sms:'.$driver,
            'provider_response' => [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ],
        ]);
    }
}
