<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Models\NotificationLog;

interface NotificationChannel
{
    public function send(NotificationLog $log): void;
}
