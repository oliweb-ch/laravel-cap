<?php

namespace LaravelCap;

use Illuminate\Http\Client\Factory as HttpFactory;
use LaravelCap\Events\CapMisconfigured;
use LaravelCap\Events\CapServerUnreachable;
use LaravelCap\Exceptions\CapVerificationException;

class Cap
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
    ) {}

    public function verify(string $token): bool
    {
        try {
            $response = $this->http
                ->timeout($this->config['timeout'])
                ->asJson()
                ->post($this->siteVerifyUrl(), [
                    'secret'   => $this->config['secret'],
                    'response' => $token,
                ]);
        } catch (\Throwable $e) {
            event(new CapServerUnreachable(
                endpoint: $this->siteVerifyUrl(),
                failOpen: $this->failOpen(),
                exceptionMessage: $e->getMessage(),
                occurredAt: new \DateTimeImmutable(),
            ));
            return $this->failOpen();
        }

        if ($response->serverError()) {
            event(new CapServerUnreachable(
                endpoint: $this->siteVerifyUrl(),
                failOpen: $this->failOpen(),
                exceptionMessage: 'HTTP ' . $response->status(),
                occurredAt: new \DateTimeImmutable(),
            ));
            return $this->failOpen();
        }

        if ($response->clientError()) {
            event(new CapMisconfigured(
                endpoint: $this->siteVerifyUrl(),
                statusCode: $response->status(),
            ));
            return $this->failOpen();
        }

        return (bool) ($response->json('success') ?? false);
    }

    private function failOpen(): bool
    {
        return (bool) ($this->config['fail_open'] ?? false);
    }

    public function verifyOrFail(string $token): void
    {
        if (! $this->verify($token)) {
            throw new CapVerificationException();
        }
    }

    private function siteVerifyUrl(): string
    {
        return rtrim($this->config['endpoint'], '/') . '/siteverify';
    }
}
