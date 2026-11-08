<?php

namespace LaravelCap\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use LaravelCap\Cap;
use LaravelCap\Middleware\VerifyCap;
use LaravelCap\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerifyCapTest extends TestCase
{
    #[Test]
    public function it_passes_request_when_token_is_valid(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response(['success' => true]),
        ]);

        $request = Request::create('/test', 'POST', ['cap-token' => 'valid-token']);
        $response = app(VerifyCap::class)->handle($request, fn($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_aborts_with_422_when_token_is_invalid(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response(['success' => false]),
        ]);

        $request = Request::create('/test', 'POST', ['cap-token' => 'invalid-token']);

        try {
            app(VerifyCap::class)->handle($request, fn($r) => new Response('ok'));
            $this->fail('HttpException attendue non levée.');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
            $this->assertEquals('Cap verification failed.', $e->getMessage());
        }
    }

    #[Test]
    public function it_aborts_when_token_field_is_absent(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response(['success' => false]),
        ]);

        $request = Request::create('/test', 'POST', []);

        try {
            app(VerifyCap::class)->handle($request, fn($r) => new Response('ok'));
            $this->fail('HttpException attendue non levée.');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }
    }

    #[Test]
    public function it_reads_token_from_custom_field_name(): void
    {
        $this->app['config']->set('cap.token_field', 'my-cap-token');

        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response(['success' => true]),
        ]);

        $request = Request::create('/test', 'POST', ['my-cap-token' => 'valid-token']);
        $response = app(VerifyCap::class)->handle($request, fn($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_passes_request_when_fail_open_and_server_is_unreachable(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::failedConnection(),
        ]);

        $this->app['config']->set('cap.fail_open', true);
        $this->app->forgetInstance(Cap::class);

        $request = Request::create('/test', 'POST', ['cap-token' => 'some-token']);
        $response = app(VerifyCap::class)->handle($request, fn($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Intégration HTTP : alias cap.verify via le kernel
    // -------------------------------------------------------------------------

    #[Test]
    public function middleware_alias_passes_valid_token_through_http_kernel(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response(['success' => true]),
        ]);

        Route::post('/cap-test', fn () => response('ok'))->middleware('cap.verify');

        $this->post('/cap-test', ['cap-token' => 'valid-token'])->assertStatus(200);
    }

    #[Test]
    public function middleware_alias_rejects_missing_token_through_http_kernel(): void
    {
        Route::post('/cap-test', fn () => response('ok'))->middleware('cap.verify');

        $this->post('/cap-test', [])->assertStatus(422);
    }
}
