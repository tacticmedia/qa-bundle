<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * The entries that a scan ignored. Without this report, a first error from an
 * external producer gives an empty review page with no explanation. The list has
 * a maximum length, and $total gives the full count.
 */
final readonly class IgnoredCaptures
{
    /**
     * @param list<IgnoredCapture> $entries
     */
    public function __construct(
        public array $entries = [],
        public int $total = 0,
    ) {
    }

    public function isEmpty(): bool
    {
        return 0 === $this->total;
    }

    public function hidden(): int
    {
        return max(0, $this->total - \count($this->entries));
    }
}
