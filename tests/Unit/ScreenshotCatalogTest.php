<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use TacticMedia\QaBundle\Review\CapturedScreen;
use TacticMedia\QaBundle\Review\IgnoredReason;
use TacticMedia\QaBundle\Review\ScreenshotCatalog;
use TacticMedia\QaBundle\Review\ScreenshotGroup;

/**
 * The catalog recovers a capture's origin from its filename alone, so the grammar
 * produced by JourneyScreenshots::fileSafe() is the contract under test. Modes and
 * viewports are the directories that the tree contains, and the discovered order
 * follows from them.
 */
final class ScreenshotCatalogTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/screenshot-catalog-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->root);
    }

    public function testParsesTheCaptureOriginFromTheFilename(): void
    {
        $this->write('light', '1920x1080', 'landscape', 'AccountJourneyE2eTest-testWholeLife-009_Close Account - Begin');

        $screen = $this->catalog()->find('light', '1920x1080', 'AccountJourneyE2eTest-testWholeLife-009_Close Account - Begin');

        self::assertNotNull($screen);
        self::assertSame('AccountJourneyE2eTest', $screen->class);
        self::assertSame('testWholeLife', $screen->method);
        self::assertSame(9, $screen->sequence);
        self::assertSame('Close Account - Begin', $screen->label);
    }

    public function testOrdersScreensByClassThenSequenceAndSkipsUnparseableFiles(): void
    {
        $this->write('light', '1920x1080', 'landscape', 'BJourneyE2eTest-testB-001_First');
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-010_Tenth');
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-002_Second');
        $this->write('light', '1920x1080', 'landscape', 'not-a-capture');

        $names = array_map(
            static fn (CapturedScreen $screen): string => $screen->label,
            $this->catalog()->screens('light', '1920x1080'),
        );

        self::assertSame(['Second', 'Tenth', 'First'], $names);
    }

    #[TestDox('Groups are discovered from the tree, modes lexicographic and viewports by size')]
    public function testGroupsAreDiscoveredAndOrdered(): void
    {
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_One');
        $this->write('light', '390x844', 'portrait', 'AJourneyE2eTest-testA-001_One');
        $this->write('dark', '1024x768', 'landscape', 'AJourneyE2eTest-testA-001_One');

        $groups = $this->catalog()->groups();

        self::assertSame(
            ['dark/1024x768', 'light/390x844', 'light/1920x1080'],
            array_map(static fn (ScreenshotGroup $group): string => $group->key(), $groups),
        );
        self::assertSame('landscape', $groups[0]->orientation);
        self::assertSame('portrait', $groups[1]->orientation);
        self::assertSame(1, $groups[0]->count);
    }

    #[TestDox('A mode or viewport directory with no captures is not a group')]
    public function testEmptyDirectoriesAreOmitted(): void
    {
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_One');
        mkdir($this->root.'/dark/1920x1080/landscape', 0o777, true);

        self::assertCount(1, $this->catalog()->groups());
    }

    #[TestDox('A directory whose name is not a mode or a WxH viewport is ignored')]
    public function testMalformedDirectoriesAreIgnored(): void
    {
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_One');
        mkdir($this->root.'/Sepia/1920x1080/landscape', 0o777, true);
        mkdir($this->root.'/dark/huge/landscape', 0o777, true);
        mkdir($this->root.'/dark/800x600/sideways', 0o777, true);

        self::assertSame(['light/1920x1080'], array_map(
            static fn (ScreenshotGroup $group): string => $group->key(),
            $this->catalog()->groups(),
        ));
    }

    public function testNavigationWrapsAtBothEndsOfTheGroup(): void
    {
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_One');
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-002_Two');
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-003_Three');

        $first = $this->catalog()->navigation('light', '1920x1080', 'AJourneyE2eTest-testA-001_One');
        $middle = $this->catalog()->navigation('light', '1920x1080', 'AJourneyE2eTest-testA-002_Two');
        $last = $this->catalog()->navigation('light', '1920x1080', 'AJourneyE2eTest-testA-003_Three');

        self::assertSame('Three', $first->previous->label);
        self::assertSame('Two', $first->next->label);
        self::assertSame(1, $first->position);
        self::assertSame(3, $first->total);
        self::assertSame('One', $middle->previous->label);
        self::assertSame('Three', $middle->next->label);
        self::assertSame('One', $last->next->label);
    }

    /**
     * Up and down keep the screen and change the group, and wrap in sidebar order.
     * A group that does not contain this screen gives its grid instead.
     */
    public function testNavigationCrossesToTheAdjacentGroupsInSidebarOrder(): void
    {
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_One');
        $this->write('light', '1728x1117', 'landscape', 'AJourneyE2eTest-testA-001_One');
        $this->write('dark', '390x844', 'portrait', 'AJourneyE2eTest-testA-002_Two');

        $navigation = $this->catalog()->navigation('light', '1920x1080', 'AJourneyE2eTest-testA-001_One');

        self::assertSame('light/1728x1117', $navigation->above->key());
        self::assertSame('AJourneyE2eTest-testA-001_One', $navigation->screenAbove?->name);
        self::assertSame('dark/390x844', $navigation->below->key());
        self::assertNull($navigation->screenBelow);
    }

    public function testNavigatingFromACaptureTheGroupDoesNotHoldIsRejected(): void
    {
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_One');

        $this->expectException(\InvalidArgumentException::class);

        $this->catalog()->navigation('light', '1920x1080', 'AJourneyE2eTest-testA-404_Gone');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unresolvableProvider(): iterable
    {
        yield 'unknown mode' => ['sepia', '1920x1080', 'AJourneyE2eTest-testA-001_One'];
        yield 'unknown viewport' => ['light', '800x600', 'AJourneyE2eTest-testA-001_One'];
        yield 'traversal' => ['light', '1920x1080', '../../../etc/passwd'];
        yield 'missing sequence' => ['light', '1920x1080', 'AJourneyE2eTest-testA_One'];
        yield 'missing file' => ['light', '1920x1080', 'AJourneyE2eTest-testA-404_Gone'];
    }

    #[DataProvider('unresolvableProvider')]
    public function testUnresolvableScreensYieldNull(string $mode, string $viewport, string $name): void
    {
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_One');

        self::assertNull($this->catalog()->find($mode, $viewport, $name));
        self::assertNull($this->catalog()->absolutePath($mode, $viewport, $name));
    }

    public function testAnAbsentTreeYieldsNoGroups(): void
    {
        self::assertSame([], $this->catalog()->groups());
    }

    #[TestDox('The prompt path is project-relative inside the project and absolute outside it')]
    public function testDisplayPathIsRelativeToTheProject(): void
    {
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_One');

        self::assertSame(
            'light/1920x1080/landscape/AJourneyE2eTest-testA-001_One.png',
            (new ScreenshotCatalog($this->root, $this->root))->displayPath('light', '1920x1080', 'AJourneyE2eTest-testA-001_One'),
        );
        self::assertSame(
            $this->root.'/light/1920x1080/landscape/AJourneyE2eTest-testA-001_One.png',
            (new ScreenshotCatalog($this->root, '/somewhere/else'))->displayPath('light', '1920x1080', 'AJourneyE2eTest-testA-001_One'),
        );
    }

    #[TestDox('The .json sidecar beside a capture is found and parsed')]
    public function testTheSidecarBesideACaptureIsRead(): void
    {
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_One');
        $this->writeMetadata('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_One', (string) json_encode([
            'url' => 'https://localhost/admin/product',
            'title' => 'Products',
            'pageWidth' => 1920,
            'pageHeight' => 4200,
            'elements' => [],
            'testClass' => 'App\Tests\E2e\AJourneyE2eTest',
            'testFile' => 'tests/E2e/AJourneyE2eTest.php',
        ]));

        $catalog = $this->catalog();
        $metadata = $catalog->metadata('light', '1920x1080', 'AJourneyE2eTest-testA-001_One');

        self::assertNotNull($metadata);
        self::assertNotNull($catalog->metadataPath('light', '1920x1080', 'AJourneyE2eTest-testA-001_One'));
        self::assertSame('https://localhost/admin/product', $metadata->url);
        self::assertSame('tests/E2e/AJourneyE2eTest.php', $metadata->testFile);
    }

    #[TestDox('A capture with no sidecar, or an unreadable one, resolves to null')]
    public function testAnAbsentOrInvalidSidecarYieldsNull(): void
    {
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_One');
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-002_Two');
        $this->writeMetadata('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-002_Two', '{not json');

        $catalog = $this->catalog();

        self::assertNull($catalog->metadataPath('light', '1920x1080', 'AJourneyE2eTest-testA-001_One'));
        self::assertNull($catalog->metadata('light', '1920x1080', 'AJourneyE2eTest-testA-001_One'));
        self::assertNotNull($catalog->metadataPath('light', '1920x1080', 'AJourneyE2eTest-testA-002_Two'));
        self::assertNull($catalog->metadata('light', '1920x1080', 'AJourneyE2eTest-testA-002_Two'));
    }

    #[TestDox('A well-formed pair is read, so nothing about it is reported as ignored')]
    public function testAReadableCaptureIsNotReportedAsIgnored(): void
    {
        $this->writePair('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_Home');

        self::assertTrue($this->catalog()->allIgnored()->isEmpty());
    }

    #[TestDox('Each way that a file can fail the contract is reported with its own reason')]
    public function testEveryIgnoredReasonIsReported(): void
    {
        $this->writePair('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_Home');
        $this->write('light', '1920x1080', 'landscape', 'shot-2.png');
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-002_Detail.png');
        $this->write('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-003_Orphan.json');
        mkdir($this->root.'/light_mode', 0o777, true);
        mkdir($this->root.'/light/wide', 0o777, true);
        mkdir($this->root.'/light/1920x1080/sideways', 0o777, true);

        $ignored = $this->catalog()->allIgnored();

        $reasons = [];

        foreach ($ignored->entries as $entry) {
            $reasons[$entry->path] = $entry->reason;
        }

        self::assertSame(IgnoredReason::UnparsableName, $reasons['light/1920x1080/landscape/shot-2.png']);
        self::assertSame(IgnoredReason::MissingSidecar, $reasons['light/1920x1080/landscape/AJourneyE2eTest-testA-002_Detail.png']);
        self::assertSame(IgnoredReason::OrphanSidecar, $reasons['light/1920x1080/landscape/AJourneyE2eTest-testA-003_Orphan.json']);
        self::assertSame(IgnoredReason::UnrecognizedDirectory, $reasons['light_mode']);
        self::assertSame(IgnoredReason::UnrecognizedDirectory, $reasons['light/wide']);
        self::assertSame(IgnoredReason::UnrecognizedDirectory, $reasons['light/1920x1080/sideways']);
        self::assertSame(6, $ignored->total);
    }

    #[TestDox('A hidden file is not a capture, so it is not reported')]
    public function testHiddenFilesAreNotReported(): void
    {
        $this->writePair('light', '1920x1080', 'landscape', 'AJourneyE2eTest-testA-001_Home');
        file_put_contents($this->root.'/light/1920x1080/landscape/.DS_Store', 'junk');

        self::assertTrue($this->catalog()->allIgnored()->isEmpty());
    }

    #[TestDox('The group view reports only what its own directory contains')]
    public function testIgnoredIsScopedToOneGroup(): void
    {
        $this->write('light', '1920x1080', 'landscape', 'wrong.png');
        $this->write('dark', '1920x1080', 'landscape', 'alsowrong.png');

        $ignored = $this->catalog()->ignored('light', '1920x1080');

        self::assertSame(1, $ignored->total);
        self::assertSame('light/1920x1080/landscape/wrong.png', $ignored->entries[0]->path);
    }

    #[TestDox('The list is capped, and the hidden count is correct')]
    public function testTheListIsCapped(): void
    {
        for ($i = 0; $i < 25; ++$i) {
            $this->write('light', '1920x1080', 'landscape', \sprintf('wrong-%02d.png', $i));
        }

        $ignored = $this->catalog()->allIgnored();

        self::assertCount(20, $ignored->entries);
        self::assertSame(25, $ignored->total);
        self::assertSame(5, $ignored->hidden());
    }

    private function catalog(): ScreenshotCatalog
    {
        return new ScreenshotCatalog($this->root);
    }

    private function write(string $mode, string $viewport, string $orientation, string $name): void
    {
        $directory = \sprintf('%s/%s/%s/%s', $this->root, $mode, $viewport, $orientation);

        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        file_put_contents($directory.'/'.(str_contains($name, '.') ? $name : $name.'.png'), 'png');
    }

    private function writePair(string $mode, string $viewport, string $orientation, string $name): void
    {
        $this->write($mode, $viewport, $orientation, $name);
        $this->writeMetadata($mode, $viewport, $orientation, $name, '{}');
    }

    private function writeMetadata(string $mode, string $viewport, string $orientation, string $name, string $json): void
    {
        file_put_contents(\sprintf('%s/%s/%s/%s/%s.json', $this->root, $mode, $viewport, $orientation, $name), $json);
    }
}
