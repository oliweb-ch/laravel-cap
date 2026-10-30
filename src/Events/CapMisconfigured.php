<?php

namespace LaravelCap\Events;

final class CapMisconfigured
{
    public function __construct(
        public readonly string $endpoint,
        public readonly int $statusCode,
    ) {}
}