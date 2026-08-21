<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * Where a capture sits among its neighbours: the screens either side of it in its
 * own group, and the groups either side of that group in sidebar order. Every
 * neighbour wraps, so stepping never dead-ends.
 */
final readonly class ScreenNavigation
{
    /**
     * @param CapturedScreen|null $screenAbove null when that group lacks this capture, which an interrupted run leaves behind
     */
    public function __construct(
        public int $position,
        public int $total,
        public CapturedScreen $previous,
        public CapturedScreen $next,
        public ScreenshotGroup $above,
        public ?CapturedScreen $screenAbove,
        public ScreenshotGroup $below,
        public ?CapturedScreen $screenBelow,
    ) {
    }
}
