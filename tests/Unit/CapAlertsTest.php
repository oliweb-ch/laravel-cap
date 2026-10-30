<?php

namespace LaravelCap\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use LaravelCap\Cap;
use LaravelCap\Events\CapMisconfigured;
use LaravelCap\Events\CapServerUnreachable;
use LaravelCap\Listeners\SendCapAlertMail;
use LaravelCap\Notifications\CapAlertNotification;
use LaravelCap\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CapAlertsTest extends TestCase
{
    #[Test]
    public function it_dispatches_server_unreachable_on_connection_exception(): void
    {
        Event::fake();
        Http::fake(['*' => Http::failedConnection()]);

        app(Cap::class)->verify('any-token');

        Event::assertDispatched(CapServerUnreachable::class, function ($event) {
            return str_contains($event->endpoint, 'siteverify')
                && ! str_contains(serialize($event), 'test-secret')
                && $event->occurredAt instanceof \DateTimeImmutable;
        });
    }

    #[Test]
    public function it_dispatches_server_unreachable_on_5xx(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response([], 500)]);

        app(Cap::class)->verify('any-token');

        Event::assertDispatched(CapServerUnreachable::class);
    }

    #[Test]
    public function it_dispatches_server_unreachable_on_503(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response([], 503)]);

        app(Cap::class)->verify('any-token');

        Event::assertDispatched(CapServerUnreachable::class);
    }

    #[Test]
    public function it_dispatches_misconfigured_on_401(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response([], 401)]);

        app(Cap::class)->verify('any-token');

        Event::assertDispatched(CapMisconfigured::class, fn ($e) => $e->statusCode === 401);
    }

    #[Test]
    public function it_dispatches_misconfigured_on_403(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response([], 403)]);

        app(Cap::class)->verify('any-token');

        Event::assertDispatched(CapMisconfigured::class, fn ($e) => $e->statusCode === 403);
    }

    #[Test]
    public function it_dispatches_misconfigured_on_404(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response([], 404)]);

        app(Cap::class)->verify('any-token');

        Event::assertDispatched(CapMisconfigured::class, fn ($e) => $e->statusCode === 404);
    }

    #[Test]
    public function it_dispatches_no_event_on_token_refused(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response(['success' => false])]);

        $result = app(Cap::class)->verify('bad-token');

        $this->assertFalse($result);
        Event::assertNotDispatched(CapServerUnreachable::class);
        Event::assertNotDispatched(CapMisconfigured::class);
    }

    #[Test]
    public function it_dispatches_no_event_on_token_accepted(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response(['success' => true])]);

        $result = app(Cap::class)->verify('valid-token');

        $this->assertTrue($result);
        Event::assertNotDispatched(CapServerUnreachable::class);
        Event::assertNotDispatched(CapMisconfigured::class);
    }

    #[Test]
    public function fail_open_returns_true_on_exception_and_event_is_dispatched(): void
    {
        Event::fake();
        Http::fake(['*' => Http::failedConnection()]);

        $this->app['config']->set('cap.fail_open', true);
        $this->app->forgetInstance(Cap::class);

        $result = app(Cap::class)->verify('any-token');

        $this->assertTrue($result);
        Event::assertDispatched(CapServerUnreachable::class, fn ($e) => $e->failOpen === true);
    }

    #[Test]
    public function fail_open_returns_true_on_5xx_and_event_is_dispatched(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response([], 500)]);

        $this->app['config']->set('cap.fail_open', true);
        $this->app->forgetInstance(Cap::class);

        $result = app(Cap::class)->verify('any-token');

        $this->assertTrue($result);
        Event::assertDispatched(CapServerUnreachable::class, fn ($e) => $e->failOpen === true);
    }

    #[Test]
    public function fail_open_returns_true_on_4xx_and_misconfigured_event_is_dispatched(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response([], 403)]);

        $this->app['config']->set('cap.fail_open', true);
        $this->app->forgetInstance(Cap::class);

        $result = app(Cap::class)->verify('any-token');

        $this->assertTrue($result);
        Event::assertDispatched(CapMisconfigured::class);
    }

    #[Test]
    public function listener_is_not_registered_without_alert_mail(): void
    {
        // cap.alerts.mail is null by default in TestCase
        $this->assertFalse(
            $this->app->make('events')->hasListeners(CapServerUnreachable::class)
        );
    }

    #[Test]
    public function listener_is_registered_when_alert_mail_is_configured(): void
    {
        $this->app['config']->set('cap.alerts.mail', 'admin@example.com');
        // Re-boot the service provider to trigger registerAlertsListener
        $provider = new \LaravelCap\CapServiceProvider($this->app);
        $provider->boot();

        $this->assertTrue(
            $this->app->make('events')->hasListeners(CapServerUnreachable::class)
        );
    }

    #[Test]
    public function listener_throttles_duplicate_server_unreachable_alerts(): void
    {
        Notification::fake();
        $this->app['config']->set('cap.alerts.mail', 'admin@example.com');
        $this->app['config']->set('cap.alerts.throttle_minutes', 15);

        $event = new CapServerUnreachable(
            endpoint: 'https://cap.test/site-key/siteverify',
            failOpen: false,
            exceptionMessage: 'Connection refused',
            occurredAt: new \DateTimeImmutable(),
        );

        $listener = new SendCapAlertMail();
        $listener->handleServerUnreachable($event);
        $listener->handleServerUnreachable($event); // second call should be throttled

        Notification::assertSentOnDemandTimes(CapAlertNotification::class, 1);
    }

    #[Test]
    public function listener_uses_distinct_throttle_keys_per_event_type(): void
    {
        Notification::fake();
        $this->app['config']->set('cap.alerts.mail', 'admin@example.com');
        $this->app['config']->set('cap.alerts.throttle_minutes', 15);

        $unreachable = new CapServerUnreachable(
            endpoint: 'https://cap.test/site-key/siteverify',
            failOpen: false,
            exceptionMessage: 'Timeout',
            occurredAt: new \DateTimeImmutable(),
        );
        $misconfigured = new CapMisconfigured(
            endpoint: 'https://cap.test/site-key/siteverify',
            statusCode: 401,
        );

        $listener = new SendCapAlertMail();
        $listener->handleServerUnreachable($unreachable);
        $listener->handleMisconfigured($misconfigured);

        // Both should be sent (different throttle keys)
        Notification::assertSentOnDemandTimes(CapAlertNotification::class, 2);
    }

    #[Test]
    public function secret_is_never_included_in_event_data(): void
    {
        Event::fake();
        Http::fake(['*' => Http::failedConnection()]);

        app(Cap::class)->verify('any-token');

        Event::assertDispatched(CapServerUnreachable::class, function ($event) {
            $serialized = json_encode([
                'endpoint'         => $event->endpoint,
                'failOpen'         => $event->failOpen,
                'exceptionMessage' => $event->exceptionMessage,
            ]);
            return ! str_contains($serialized, 'test-secret');
        });
    }
}