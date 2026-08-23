<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * One entry the catalog did not read, named relative to the screenshot root.
 */
final readonly class IgnoredCapture
{
    public function __construct(
        public string $path,
        public IgnoredReason $reason,
    ) {
    }
}
