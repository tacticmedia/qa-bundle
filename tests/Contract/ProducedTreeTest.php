<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Contract;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use TacticMedia\QaBundle\Review\IgnoredCapture;
use TacticMedia\QaBundle\Review\ScreenMetadata;
use TacticMedia\QaBundle\Review\ScreenshotCatalog;

/**
 * Runs the PHP reader over a tree that a different producer wrote. Set
 * QA_SCREENSHOTS_DIR to the tree that the npm package produced and run
 * `vendor/bin/phpunit --group contract`; without it the group skips.
 *
 * This is the only test that shows that docs/screenshot-sets.md is a contract
 * and not a description of one implementation.
 */
#[Group('contract')]
final class ProducedTreeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $root = $_SERVER['QA_SCREENSHOTS_DIR'] ?? getenv('QA_SCREENSHOTS_DIR');

        if (!\is_string($root) || '' === $root || !is_dir($root)) {
            self::markTestSkipped('Set QA_SCREENSHOTS_DIR to a produced screenshot tree.');
        }

        $this->root = $root;
    }

    #[TestDox('Every capture in the tree is readable, so the reader ignores nothing')]
    public function testTheReaderIgnoresNothing(): void
    {
        $ignored = $this->catalog()->allIgnored();

        self::assertSame([], array_map(
            static fn (IgnoredCapture $entry): string => $entry->path.' ('.$entry->reason->value.')',
            $ignored->entries,
        ));
        self::assertSame(0, $ignored->total);
    }

    #[TestDox('The five default viewports in light and dark give ten groups')]
    public function testTheTreeDiscoversAsGroups(): void
    {
        $groups = $this->catalog()->groups();

        self::assertCount(10, $groups);

        foreach ($groups as $group) {
            self::assertGreaterThan(0, $group->count);
        }
    }

    #[TestDox('The sidecar parses, and its page size is the PNG size exactly')]
    public function testTheSidecarMatchesTheImage(): void
    {
        $catalog = $this->catalog();
        $checked = 0;

        foreach ($catalog->groups() as $group) {
            foreach ($catalog->screens($group->mode, $group->viewport) as $screen) {
                $metadata = $catalog->metadata($group->mode, $group->viewport, $screen->name);

                self::assertInstanceOf(ScreenMetadata::class, $metadata, $screen->name);

                $path = $catalog->absolutePath($group->mode, $group->viewport, $screen->name);

                self::assertIsString($path);

                $size = getimagesize($path);

                self::assertIsArray($size);
                self::assertSame([$metadata->pageWidth, $metadata->pageHeight], [$size[0], $size[1]], $screen->name);

                ++$checked;
            }
        }

        self::assertGreaterThan(0, $checked);
    }

    #[TestDox('The basename that the review page reads contains the producer identity')]
    public function testTheOriginIsRecoverableFromTheBasename(): void
    {
        $catalog = $this->catalog();
        $screens = $catalog->screens('light', '1920x1080');

        self::assertNotEmpty($screens);

        foreach ($screens as $screen) {
            self::assertMatchesRegularExpression('/^\w+$/', $screen->class);
            self::assertMatchesRegularExpression('/^\w+$/', $screen->method);
            self::assertGreaterThan(0, $screen->sequence);
            self::assertNotSame('', $screen->label);
        }
    }

    private function catalog(): ScreenshotCatalog
    {
        return new ScreenshotCatalog($this->root);
    }
}
