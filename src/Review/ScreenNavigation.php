<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * The position of a capture between its neighbours: the screens before and after
 * it in its own group, and the groups before and after that group in sidebar
 * order. Each axis is circular, so a step always gives a target. The screen in an
 * adjacent group is null when that group does not contain this capture, which
 * occurs after an interrupted run or when a screen was captured at a subset of
 * the viewports.
 */
final readonly class ScreenNavigation
{
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
