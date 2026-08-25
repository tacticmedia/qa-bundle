<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * One screen that a journey captured. The basename identifies it, and the copies
 * for each mode and viewport use the same basename. Each property here comes from
 * that basename. The path of the test is in the sidecar
 * ({@see ScreenMetadata::$testFile}), because a basename cannot contain a path.
 */
final class CapturedScreen
{
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly string $method,
        public readonly int $sequence,
        public readonly string $label,
    ) {
    }

    /** Fragment id of the card for this screen on the group grid. A label can contain spaces. */
    public function anchor(): string
    {
        return 'screen-'.str_replace(' ', '-', $this->name);
    }
}
