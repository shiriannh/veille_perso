<?php

namespace App\Service;

class ContentAdmissionResult
{
    public const ADMIT = 'admit';
    public const QUARANTINE = 'quarantine';
    public const REJECT = 'reject';

    /**
     * @param array<int, string> $signals
     */
    public function __construct(
        public readonly string $outcome,
        public readonly int $score,
        public readonly string $reason,
        public readonly array $signals = [],
    ) {
    }

    public function isAdmitted(): bool
    {
        return $this->outcome === self::ADMIT;
    }
}
