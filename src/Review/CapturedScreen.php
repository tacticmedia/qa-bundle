<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * One screen captured by a journey, identified by the basename its mode and
 * viewport copies share. Everything here is parsed out of that basename; where
 * the test lives is the sidecar's business ({@see ScreenMetadata::$testFile}),
 * because a basename cannot carry a path.
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

    /** Fragment id of this screen's card on the group grid; a label may contain spaces. */
    public function anchor(): string
    {
        return 'screen-'.str_replace(' ', '-', $this->name);
    }
}
