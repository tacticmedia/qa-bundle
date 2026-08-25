<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * The elements that a rectangle from the reviewer covers, matched against the
 * sidecar of the screenshot it was drawn on ({@see SelectionResolver}).
 */
final readonly class SelectionContext
{
    /**
     * @param list<MetadataElement> $selected
     * @param list<MetadataElement> $enclosing
     */
    public function __construct(
        public bool $layoutChanged,
        public array $selected,
        public array $enclosing,
    ) {
    }
}
