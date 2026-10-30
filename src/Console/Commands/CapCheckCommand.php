<?php

namespace LaravelCap\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use LaravelCap\Events\CapServerUnreachable;

class CapCheckCommand extends Command
{
    protected $signature = 'cap:check
                            {--notify : Dispatch alert events on failure}';

    protected $description = 'Probe the Cap server health endpoint (GET /health)';

    public function handle(): int
    {
        $endpoint = config('cap.endpoint', '');
        $parsed   = parse_url($endpoint);

        if (empty($parsed['host'])) {
            $this->error('cap.endpoint is not configured.');
            return self::FAILURE;
        }

        $scheme     = $parsed['scheme'] ?? 'https';
        $host       = $parsed['host'];
        $port       = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $healthUrl  = "{$scheme}://{$host}{$port}/health";

        $this->line("Probing: {$healthUrl}");

        try {
            $response = Http::timeout((int) config('cap.timeout', 5))
                ->get($healthUrl);
        } catch (\Throwable $e) {
            $this->error("Cap server unreachable: {$e->getMessage()}");
            if ($this->option('notify')) {
                event(new CapServerUnreachable(
                    endpoint: $healthUrl,
                    failOpen: (bool) config('cap.fail_open', false),
                    exceptionMessage: $e->getMessage(),
                    occurredAt: new \DateTimeImmutable(),
                ));
            }
            return self::FAILURE;
        }

        $status = $response->json('status', 'unknown');

        if ($status === 'ok') {
            $this->info("Cap server is healthy (status: ok).");
            return self::SUCCESS;
        }

        $this->error("Cap server responded but is not healthy (status: {$status}, HTTP {$response->status()}).");
        if ($this->option('notify')) {
            event(new CapServerUnreachable(
                endpoint: $healthUrl,
                failOpen: (bool) config('cap.fail_open', false),
                exceptionMessage: "Health status: {$status} (HTTP {$response->status()})",
                occurredAt: new \DateTimeImmutable(),
            ));
        }
        return 2;
    }
}