<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Models\NotificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel implements NotificationChannel
{
    public function send(NotificationLog $log): void
    {
        $driver = config('services.whatsapp.driver');
        $apiKey = config('services.whatsapp.api_key');

        if (blank($driver) || blank($apiKey)) {
            Log::info('WhatsApp notification left pending — configure WHATSAPP_DRIVER and WHATSAPP_API_KEY to deliver.', [
                'notification_log_id' => $log->id,
                'recipient' => $log->recipient,
            ]);

            $log->update([
                'status' => 'pending',
                'provider' => 'whatsapp',
                'provider_response' => ['reason' => 'credentials_not_configured'],
            ]);

            return;
        }

        if ($driver === 'log') {
            Log::info('WhatsApp notification (log driver)', [
                'to' => $log->recipient,
                'body' => $log->body,
            ]);
            $log->update([
                'status' => 'sent',
                'provider' => 'whatsapp:log',
                'provider_response' => ['driver' => 'log'],
            ]);

            return;
        }

        $endpoint = config('services.whatsapp.endpoint');

        if (blank($endpoint)) {
            $log->update([
                'status' => 'pending',
                'provider' => 'whatsapp:'.$driver,
                'provider_response' => ['reason' => 'endpoint_not_configured'],
            ]);

            return;
        }

        $response = Http::withToken((string) $apiKey)->post((string) $endpoint, [
            'to' => $log->recipient,
            'from' => config('services.whatsapp.from'),
            'body' => $log->body,
        ]);

        $log->update([
            'status' => $response->successful() ? 'sent' : 'failed',
            'provider' => 'whatsapp:'.$driver,
            'provider_response' => [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ],
        ]);
    }
}
