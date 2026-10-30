<?php

namespace LaravelCap\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use LaravelCap\Events\CapMisconfigured;
use LaravelCap\Events\CapServerUnreachable;
use LaravelCap\Notifications\CapAlertNotification;

class SendCapAlertMail implements ShouldQueue
{
    public function handleServerUnreachable(CapServerUnreachable $event): void
    {
        $mail = config('cap.alerts.mail');
        if (! $mail) {
            return;
        }
        $minutes = (int) config('cap.alerts.throttle_minutes', 15);
        if (! Cache::add('cap_alert:server_unreachable', 1, now()->addMinutes($minutes))) {
            return;
        }
        Notification::route('mail', $mail)->notify(new CapAlertNotification($event));
    }

    public function handleMisconfigured(CapMisconfigured $event): void
    {
        $mail = config('cap.alerts.mail');
        if (! $mail) {
            return;
        }
        $minutes = (int) config('cap.alerts.throttle_minutes', 15);
        if (! Cache::add('cap_alert:misconfigured', 1, now()->addMinutes($minutes))) {
            return;
        }
        Notification::route('mail', $mail)->notify(new CapAlertNotification($event));
    }
}