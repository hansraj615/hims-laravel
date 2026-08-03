<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Channels\EmailChannel;
use App\Domain\Notifications\Channels\SmsChannel;
use App\Domain\Notifications\Channels\WhatsAppChannel;
use App\Domain\Notifications\Models\NotificationLog;
use App\Domain\Notifications\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Model;

class NotificationDispatcher
{
    /**
     * @param  array<string, string|int|float|null>  $context
     */
    public function dispatch(
        ?int $hospitalId,
        ?int $branchId,
        string $templateCode,
        string $channel,
        ?string $recipient = null,
        array $context = [],
        ?int $patientId = null,
        ?int $userId = null,
        ?Model $related = null,
    ): NotificationLog {
        $template = NotificationTemplate::query()
            ->where('code', $templateCode)
            ->where('channel', $channel)
            ->where('status', 'active')
            ->where(function ($query) use ($hospitalId): void {
                $query->where('hospital_id', $hospitalId)->orWhereNull('hospital_id');
            })
            ->orderByRaw('hospital_id is null')
            ->first();

        $log = NotificationLog::create([
            'hospital_id' => $hospitalId,
            'branch_id' => $branchId,
            'patient_id' => $patientId,
            'user_id' => $userId,
            'template_code' => $templateCode,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $this->renderBody($template?->subject, $context),
            'body' => $this->renderBody($template?->body, $context),
            'status' => 'pending',
            'attempts' => 1,
            'last_attempted_at' => now(),
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
        ]);

        match ($channel) {
            'in_app' => $log->update(['status' => 'sent']),
            'email' => app(EmailChannel::class)->send($log),
            'sms' => app(SmsChannel::class)->send($log),
            'whatsapp' => app(WhatsAppChannel::class)->send($log),
            default => null,
        };

        return $log->refresh();
    }

    /**
     * @param  array<string, string|int|float|null>  $context
     */
    private function renderBody(?string $template, array $context): ?string
    {
        if ($template === null) {
            return null;
        }

        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $matches) => (string) ($context[$matches[1]] ?? ''),
            $template,
        );
    }
}
