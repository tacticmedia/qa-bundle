<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * One mode/viewport directory of screenshots. The review sidebar lists these directories.
 */
final class ScreenshotGroup
{
    public function __construct(
        public readonly string $mode,
        public readonly string $viewport,
        public readonly string $orientation,
        public readonly int $count,
    ) {
    }

    public function key(): string
    {
        return $this->mode.'/'.$this->viewport;
    }
}
