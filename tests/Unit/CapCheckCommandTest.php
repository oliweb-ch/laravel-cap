<?php

namespace LaravelCap\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use LaravelCap\Events\CapServerUnreachable;
use LaravelCap\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CapCheckCommandTest extends TestCase
{
    #[Test]
    public function it_exits_0_when_server_is_healthy(): void
    {
        Http::fake([
            'https://cap.test/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->artisan('cap:check')
            ->assertExitCode(0)
            ->expectsOutputToContain('healthy');
    }

    #[Test]
    public function it_exits_1_on_network_exception(): void
    {
        Http::fake([
            'https://cap.test/health' => Http::failedConnection(),
        ]);

        $this->artisan('cap:check')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_exits_2_when_health_status_is_unavailable(): void
    {
        Http::fake([
            'https://cap.test/health' => Http::response(['status' => 'unavailable'], 503),
        ]);

        $this->artisan('cap:check')
            ->assertExitCode(2);
    }

    #[Test]
    public function it_exits_2_when_status_json_is_unavailable_even_on_200(): void
    {
        Http::fake([
            'https://cap.test/health' => Http::response(['status' => 'unavailable'], 200),
        ]);

        $this->artisan('cap:check')
            ->assertExitCode(2);
    }

    #[Test]
    public function it_dispatches_event_with_notify_on_network_failure(): void
    {
        Event::fake();
        Http::fake([
            'https://cap.test/health' => Http::failedConnection(),
        ]);

        $this->artisan('cap:check --notify')
            ->assertExitCode(1);

        Event::assertDispatched(CapServerUnreachable::class);
    }

    #[Test]
    public function it_dispatches_event_with_notify_when_unhealthy(): void
    {
        Event::fake();
        Http::fake([
            'https://cap.test/health' => Http::response(['status' => 'unavailable'], 503),
        ]);

        $this->artisan('cap:check --notify')
            ->assertExitCode(2);

        Event::assertDispatched(CapServerUnreachable::class);
    }

    #[Test]
    public function it_does_not_dispatch_event_without_notify_flag(): void
    {
        Event::fake();
        Http::fake([
            'https://cap.test/health' => Http::failedConnection(),
        ]);

        $this->artisan('cap:check')
            ->assertExitCode(1);

        Event::assertNotDispatched(CapServerUnreachable::class);
    }

    #[Test]
    public function it_calls_health_url_derived_from_endpoint_without_site_key(): void
    {
        Http::fake([
            'https://cap.test/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->artisan('cap:check')->assertExitCode(0);

        Http::assertSent(fn ($request) => $request->url() === 'https://cap.test/health');
    }

    #[Test]
    public function it_includes_port_in_health_url(): void
    {
        $this->app['config']->set('cap.endpoint', 'https://cap.test:8443/site-key/');
        Http::fake([
            'https://cap.test:8443/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->artisan('cap:check')->assertExitCode(0);

        Http::assertSent(fn ($request) => $request->url() === 'https://cap.test:8443/health');
    }
}