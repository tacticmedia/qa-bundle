<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use TacticMedia\QaBundle\Review\MetadataElement;
use TacticMedia\QaBundle\Review\ScreenMetadata;
use TacticMedia\QaBundle\Review\SelectionResolver;

/**
 * A reviewer drags over a visible area and not over one node, so the resolver
 * must select the distinct leaf elements from the nested boxes that the rectangle
 * covers.
 */
final class SelectionResolverTest extends TestCase
{
    private const IMAGE_SIZE = ['width' => 1000, 'height' => 2000];

    #[TestDox('Dragging over a table cell reports the link inside it, not the cell or the row')]
    public function testTheSmallestDistinctElementsWin(): void
    {
        $context = (new SelectionResolver())->resolve(
            $this->page($this->tableElements()),
            ['x' => 0, 'y' => 100, 'width' => 300, 'height' => 40],
            self::IMAGE_SIZE,
        );

        self::assertFalse($context->layoutChanged);
        self::assertSame(['a'], $this->tags($context->selected));
    }

    #[TestDox('Enclosing names the containers smallest first, three at most')]
    public function testEnclosingIsCappedAndOrdered(): void
    {
        $context = (new SelectionResolver())->resolve(
            $this->page($this->tableElements()),
            ['x' => 0, 'y' => 100, 'width' => 300, 'height' => 40],
            self::IMAGE_SIZE,
        );

        self::assertSame(['td', 'tr', 'table'], $this->tags($context->enclosing));
    }

    #[TestDox('A rectangle over many leaves reports five of them')]
    public function testSelectedIsCappedAtFive(): void
    {
        $elements = [];

        foreach (range(0, 5) as $index) {
            $elements[] = $this->element('span', 10, 10 + 30 * $index, 100, 20);
        }

        $context = (new SelectionResolver())->resolve(
            $this->page($elements),
            ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 200],
            self::IMAGE_SIZE,
        );

        self::assertCount(5, $context->selected);
    }

    #[TestDox('A rectangle over empty space falls back to the elements it intersects')]
    public function testEmptySpaceFallsBackToIntersectingElements(): void
    {
        $context = (new SelectionResolver())->resolve(
            $this->page($this->tableElements()),
            ['x' => 0, 'y' => 0, 'width' => 1000, 'height' => 60],
            self::IMAGE_SIZE,
        );

        self::assertSame(['table', 'main'], $this->tags($context->selected));
    }

    #[TestDox('A page that changed height since the note was written resolves to nothing')]
    public function testALayoutChangeEmptiesBothLists(): void
    {
        $context = (new SelectionResolver())->resolve(
            $this->page($this->tableElements(), 1800),
            ['x' => 0, 'y' => 100, 'width' => 300, 'height' => 40],
            self::IMAGE_SIZE,
        );

        self::assertTrue($context->layoutChanged);
        self::assertSame([], $context->selected);
        self::assertSame([], $context->enclosing);
    }

    /**
     * A link in a cell, in a row, in a table, on a page.
     *
     * @return list<MetadataElement>
     */
    private function tableElements(): array
    {
        return [
            $this->element('main', 0, 0, 1000, 1800),
            $this->element('table', 0, 0, 1000, 400),
            $this->element('tr', 0, 100, 1000, 40),
            $this->element('td', 0, 100, 300, 40),
            $this->element('a', 8, 110, 120, 20),
        ];
    }

    /**
     * @param list<MetadataElement> $elements
     */
    private function page(array $elements, int $pageHeight = 2000): ScreenMetadata
    {
        return new ScreenMetadata('https://localhost/admin/product', 'Products', 1000, $pageHeight, $elements);
    }

    private function element(string $tag, int $x, int $y, int $width, int $height): MetadataElement
    {
        return new MetadataElement($tag, $tag, $x, $y, $width, $height, null, null, []);
    }

    /**
     * @param list<MetadataElement> $elements
     *
     * @return list<string>
     */
    private function tags(array $elements): array
    {
        return array_map(static fn (MetadataElement $element): string => $element->tag, $elements);
    }
}
