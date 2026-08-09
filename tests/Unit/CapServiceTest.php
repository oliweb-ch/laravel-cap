<?php

namespace LaravelCap\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LaravelCap\Cap;
use LaravelCap\Exceptions\CapVerificationException;
use LaravelCap\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CapServiceTest extends TestCase
{
    #[Test]
    public function it_returns_true_when_cap_responds_with_success(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response(['success' => true]),
        ]);

        $this->assertTrue(app(Cap::class)->verify('valid-token'));
    }

    #[Test]
    public function it_returns_false_when_cap_responds_with_failure(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response(['success' => false]),
        ]);

        $this->assertFalse(app(Cap::class)->verify('invalid-token'));
    }

    #[Test]
    public function it_returns_false_on_http_server_error(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response([], 500),
        ]);

        $this->assertFalse(app(Cap::class)->verify('any-token'));
    }

    #[Test]
    public function it_returns_false_on_network_failure(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::failedConnection(),
        ]);

        $this->assertFalse(app(Cap::class)->verify('any-token'));
    }

    #[Test]
    public function it_throws_on_verify_or_fail_when_invalid(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response(['success' => false]),
        ]);

        $this->expectException(CapVerificationException::class);

        app(Cap::class)->verifyOrFail('invalid-token');
    }

    #[Test]
    public function it_sends_correct_payload_to_siteverify(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response(['success' => true]),
        ]);

        app(Cap::class)->verify('my-token');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://cap.test/site-key/siteverify'
                && $request['secret'] === 'test-secret'
                && $request['response'] === 'my-token';
        });
    }

    #[Test]
    public function it_returns_true_on_network_failure_when_fail_open(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::failedConnection(),
        ]);

        $this->assertTrue($this->capWithFailOpen()->verify('any-token'));
    }

    #[Test]
    public function it_returns_true_on_http_server_error_when_fail_open(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response([], 500),
        ]);

        $this->assertTrue($this->capWithFailOpen()->verify('any-token'));
    }

    #[Test]
    public function it_still_returns_false_on_invalid_token_when_fail_open(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response(['success' => false]),
        ]);

        $this->assertFalse($this->capWithFailOpen()->verify('invalid-token'));
    }

    // -------------------------------------------------------------------------
    // Cas limites : endpoint sans slash final
    // -------------------------------------------------------------------------

    #[Test]
    public function it_calls_correct_siteverify_url_when_endpoint_has_no_trailing_slash(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response(['success' => true]),
        ]);

        $this->app['config']->set('cap.endpoint', 'https://cap.test/site-key'); // sans slash final
        $this->app->forgetInstance(Cap::class);

        app(Cap::class)->verify('any-token');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://cap.test/site-key/siteverify';
        });
    }

    // -------------------------------------------------------------------------
    // Cas limites : corps de réponse atypiques (200 OK)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_false_when_success_key_is_absent_from_200_response(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response([]), // {}
        ]);

        $this->assertFalse(app(Cap::class)->verify('any-token'));
    }

    #[Test]
    public function it_returns_false_when_success_value_is_null_in_200_response(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response(['success' => null]),
        ]);

        $this->assertFalse(app(Cap::class)->verify('any-token'));
    }

    #[Test]
    public function it_returns_false_without_exception_when_response_body_is_invalid_json(): void
    {
        Http::fake([
            'https://cap.test/site-key/siteverify' => Http::response('this-is-not-json', 200),
        ]);

        // Ne doit pas lever d'exception ; json() retourne null → false
        $result = app(Cap::class)->verify('any-token');
        $this->assertFalse($result);
    }

    private function capWithFailOpen(): Cap
    {
        $this->app['config']->set('cap.fail_open', true);
        $this->app->forgetInstance(Cap::class);

        return app(Cap::class);
    }
}
