<?php

namespace LaravelCap\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LaravelCap\Events\CapMisconfigured;
use LaravelCap\Events\CapServerUnreachable;

class CapAlertNotification extends Notification
{
    public function __construct(
        private readonly CapServerUnreachable|CapMisconfigured $event,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $site = parse_url($this->event->endpoint, PHP_URL_HOST) ?? $this->event->endpoint;

        if ($this->event instanceof CapServerUnreachable) {
            return (new MailMessage)
                ->subject(trans('cap::messages.alert_unreachable_subject', ['site' => $site]))
                ->greeting(trans('cap::messages.alert_unreachable_greeting'))
                ->line(trans('cap::messages.alert_unreachable_endpoint', ['endpoint' => $this->event->endpoint]))
                ->line(trans('cap::messages.alert_unreachable_error', ['message' => $this->event->exceptionMessage]))
                ->line($this->event->failOpen
                    ? trans('cap::messages.alert_unreachable_failopen')
                    : trans('cap::messages.alert_unreachable_failclosed'));
        }

        // CapMisconfigured
        return (new MailMessage)
            ->subject(trans('cap::messages.alert_misconfigured_subject', ['site' => $site]))
            ->greeting(trans('cap::messages.alert_misconfigured_greeting'))
            ->line(trans('cap::messages.alert_misconfigured_endpoint', ['endpoint' => $this->event->endpoint]))
            ->line(trans('cap::messages.alert_misconfigured_status', ['code' => $this->event->statusCode]));
    }
}