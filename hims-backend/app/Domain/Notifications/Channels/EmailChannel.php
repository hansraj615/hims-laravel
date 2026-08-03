<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Models\NotificationLog;
use App\Mail\TemplatedNotificationMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailChannel implements NotificationChannel
{
    public function send(NotificationLog $log): void
    {
        if (blank($log->recipient)) {
            $log->update([
                'status' => 'failed',
                'provider' => 'smtp',
                'provider_response' => ['error' => 'Missing recipient email'],
            ]);

            return;
        }

        try {
            Mail::to($log->recipient)->send(new TemplatedNotificationMail(
                emailSubject: (string) ($log->subject ?: 'HIMS Notification'),
                emailBody: (string) ($log->body ?? ''),
            ));

            $log->update([
                'status' => 'sent',
                'provider' => 'smtp',
                'provider_response' => ['delivered_via' => config('mail.default')],
            ]);
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'provider' => 'smtp',
                'provider_response' => ['error' => $exception->getMessage()],
            ]);
        }
    }
}
