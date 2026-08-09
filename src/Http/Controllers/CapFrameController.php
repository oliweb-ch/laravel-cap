<?php

namespace LaravelCap\Http\Controllers;

use Illuminate\Http\Response;

class CapFrameController
{
    public function __invoke(): Response
    {
        return response()
            ->view('cap::frame')
            ->withHeaders([
                'Content-Security-Policy' =>
                    "default-src 'none'; " .
                    "script-src 'self' 'unsafe-inline' 'unsafe-eval' 'wasm-unsafe-eval' blob:; " .
                    "style-src 'self' 'unsafe-inline'; " .
                    "connect-src {$this->connectSrc()}; " .
                    "worker-src blob:; " .
                    "img-src data:; " .
                    "frame-ancestors 'self';",
                'X-Frame-Options' => 'SAMEORIGIN',
                'Cache-Control'   => 'no-store',
            ]);
    }

    /**
     * Construit la valeur de connect-src à partir de l'origine de cap.endpoint.
     * Retombe sur 'self' seul si l'endpoint est absent ou mal formé.
     */
    private function connectSrc(): string
    {
        $endpoint = config('cap.endpoint') ?? '';
        $host     = parse_url($endpoint, PHP_URL_HOST);

        if (!$host) {
            return "'self'";
        }

        $scheme = parse_url($endpoint, PHP_URL_SCHEME) ?? '';

        if (!in_array($scheme, ['http', 'https'], true)) {
            return "'self'";
        }

        $port   = parse_url($endpoint, PHP_URL_PORT);
        $origin = $scheme . '://' . $host . ($port ? ':' . $port : '');

        return "'self' " . $origin;
    }
}
