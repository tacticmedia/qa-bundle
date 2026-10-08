<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use TacticMedia\QaBundle\Test\ScreenshotTree;

/**
 * In the global namespace, static::class contains no namespace separator. PHPUnit
 * loads this file by path, so it needs no autoload entry.
 */
final class GlobalNamespaceScreenshotTreeTest extends TestCase
{
    use ScreenshotTree;

    /**
     * A directory that does not exist, so the clear-once hook removes nothing.
     */
    protected static function screenshotRoot(): string
    {
        return sys_get_temp_dir().'/global-namespace-screenshot-tree-'.bin2hex(random_bytes(6));
    }

    #[TestDox('A test case in the global namespace gives the journey part of the basename')]
    public function testTheBasenameNamesTheTestCase(): void
    {
        self::assertSame(
            '/g/GlobalNamespaceScreenshotTreeTest-testTheBasenameNamesTheTestCase-001_A',
            $this->screenshotBase('/g', '001_A'),
        );
    }
}
