<?php

declare(strict_types=1);

namespace Akqa\SilverStripe\SSR;

/**
 * Result of invoking a Node.js (or other) process for React SSR.
 */
class NodeProcessResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
    ) {
    }


    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }
}
