<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * Converts a stored rectangle into the elements that it covers. The match occurs
 * at prompt-build time against the current sidecar, so a note stays usable after
 * a re-run. The staleness check detects a page whose size changed after the note
 * was written.
 */
final readonly class SelectionResolver
{
    /** Page size tolerance, and the margin that an enclosing box receives on each edge. */
    private const SLACK = 2;

    /** The fraction of the area of an element that must be inside the rectangle. */
    private const COVERAGE = 0.6;
    private const MAX_SELECTED = 5;
    private const MAX_ENCLOSING = 3;
    private const MAX_INTERSECTING = 3;

    /**
     * @param array{x: int, y: int, width: int, height: int} $rectangle
     * @param array{width: int, height: int}                 $imageSize
     */
    public function resolve(ScreenMetadata $metadata, array $rectangle, array $imageSize): SelectionContext
    {
        if (abs($metadata->pageWidth - $imageSize['width']) > self::SLACK
            || abs($metadata->pageHeight - $imageSize['height']) > self::SLACK) {
            return new SelectionContext(true, [], []);
        }

        $selected = $this->distinct($this->covered($metadata->elements, $rectangle));

        if ([] === $selected) {
            $selected = \array_slice($this->intersecting($metadata->elements, $rectangle), 0, self::MAX_INTERSECTING);
        }

        return new SelectionContext(false, $selected, $this->enclosing($metadata->elements, $rectangle));
    }

    /**
     * @param list<MetadataElement>                          $elements
     * @param array{x: int, y: int, width: int, height: int} $rectangle
     *
     * @return list<MetadataElement>
     */
    private function covered(array $elements, array $rectangle): array
    {
        return $this->byArea(array_filter(
            $elements,
            fn (MetadataElement $element): bool => 0 < $element->area()
                && $this->intersection($element, $rectangle) / $element->area() >= self::COVERAGE,
        ));
    }

    /**
     * @param list<MetadataElement>                          $elements
     * @param array{x: int, y: int, width: int, height: int} $rectangle
     *
     * @return list<MetadataElement>
     */
    private function intersecting(array $elements, array $rectangle): array
    {
        return $this->byArea(array_filter(
            $elements,
            fn (MetadataElement $element): bool => 0 < $this->intersection($element, $rectangle),
        ));
    }

    /**
     * Gives the container that holds the selection, including a selection that
     * covers no complete element.
     *
     * @param list<MetadataElement>                          $elements
     * @param array{x: int, y: int, width: int, height: int} $rectangle
     *
     * @return list<MetadataElement>
     */
    private function enclosing(array $elements, array $rectangle): array
    {
        return \array_slice($this->byArea(array_filter(
            $elements,
            static fn (MetadataElement $element): bool => $element->x - self::SLACK <= $rectangle['x']
                && $element->y - self::SLACK <= $rectangle['y']
                && $element->x + $element->width + self::SLACK >= $rectangle['x'] + $rectangle['width']
                && $element->y + $element->height + self::SLACK >= $rectangle['y'] + $rectangle['height'],
        )), 0, self::MAX_ENCLOSING);
    }

    /**
     * Smallest first. Removes each box that contains a box already selected, so
     * the result gives the link and not the cell or the row that contains it.
     *
     * @param list<MetadataElement> $sorted
     *
     * @return list<MetadataElement>
     */
    private function distinct(array $sorted): array
    {
        $taken = [];

        foreach ($sorted as $element) {
            if (array_any($taken, static fn (MetadataElement $inner): bool => self::contains($element, $inner))) {
                continue;
            }

            $taken[] = $element;

            if (self::MAX_SELECTED === \count($taken)) {
                break;
            }
        }

        return $taken;
    }

    private static function contains(MetadataElement $outer, MetadataElement $inner): bool
    {
        return $outer->x <= $inner->x
            && $outer->y <= $inner->y
            && $outer->x + $outer->width >= $inner->x + $inner->width
            && $outer->y + $outer->height >= $inner->y + $inner->height;
    }

    /**
     * @param array{x: int, y: int, width: int, height: int} $rectangle
     */
    private function intersection(MetadataElement $element, array $rectangle): int
    {
        $width = min($element->x + $element->width, $rectangle['x'] + $rectangle['width']) - max($element->x, $rectangle['x']);
        $height = min($element->y + $element->height, $rectangle['y'] + $rectangle['height']) - max($element->y, $rectangle['y']);

        return 0 < $width && 0 < $height ? $width * $height : 0;
    }

    /**
     * @param array<int, MetadataElement> $elements
     *
     * @return list<MetadataElement>
     */
    private function byArea(array $elements): array
    {
        $sorted = array_values($elements);

        usort($sorted, static fn (MetadataElement $a, MetadataElement $b): int => $a->area() <=> $b->area());

        return $sorted;
    }
}
