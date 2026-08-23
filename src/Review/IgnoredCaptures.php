<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * What a scan passed over. A foreign producer's first mistake otherwise shows up
 * as an empty review page with no explanation, so the page reports this rather
 * than skipping in silence. The list is capped and $total carries the real count.
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
