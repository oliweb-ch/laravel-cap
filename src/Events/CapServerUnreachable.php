<?php

namespace LaravelCap\Events;

final class CapServerUnreachable
{
    public function __construct(
        public readonly string $endpoint,
        public readonly bool $failOpen,
        public readonly string $exceptionMessage,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
}