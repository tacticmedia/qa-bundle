<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * One entry that the catalog did not read, with a path relative to the screenshot root.
 */
final readonly class IgnoredCapture
{
    public function __construct(
        public string $path,
        public IgnoredReason $reason,
    ) {
    }
}
