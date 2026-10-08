<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use TacticMedia\QaBundle\Test\ScreenshotTree;

/**
 * The layout and the sidecar that both capture traits write, without a browser.
 */
final class ScreenshotTreeTest extends TestCase
{
    use ScreenshotTree;

    private static string $root;

    public static function setUpBeforeClass(): void
    {
        self::$root = sys_get_temp_dir().'/screenshot-tree-'.bin2hex(random_bytes(6));

        mkdir(self::$root, 0o777, true);

        $_SERVER['QA_SCREENSHOTS_DIR'] = self::$root;
    }

    public static function tearDownAfterClass(): void
    {
        unset($_SERVER['QA_SCREENSHOTS_DIR']);

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::$root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            \assert($entry instanceof \SplFileInfo);

            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir(self::$root);
    }

    #[TestDox('Each capture takes the next three-digit number and a file-safe label, and a repeated label takes none')]
    public function testTheStageNumbersEachNewLabel(): void
    {
        self::assertSame('001_Order -12-34', $this->nextScreenshotStage('Order #12/34'));
        self::assertSame('002_Home', $this->nextScreenshotStage('Home'));
        self::assertNull($this->nextScreenshotStage('Home'));
        self::assertSame('003_Cart', $this->nextScreenshotStage('Cart'));
    }

    #[TestDox('The group directory is the mode, the requested size and the orientation, and a square is portrait')]
    public function testTheGroupDirectory(): void
    {
        $landscape = $this->screenshotGroupDirectory('dark', ['width' => 1024, 'height' => 768]);
        $square = $this->screenshotGroupDirectory('light', ['width' => 800, 'height' => 800]);

        self::assertSame(self::$root.'/dark/1024x768/landscape', $landscape);
        self::assertSame(self::$root.'/light/800x800/portrait', $square);
        self::assertDirectoryExists($landscape);
        self::assertDirectoryExists($square);
    }

    #[TestDox('The basename is the test case, the test method and the stage')]
    public function testTheBasename(): void
    {
        self::assertSame('/g/ScreenshotTreeTest-testTheBasename-001_A', $this->screenshotBase('/g', '001_A'));
    }

    #[TestDox('A relative root resolves against the working directory of PHP, and an absolute root does not change')]
    public function testARelativeRootBecomesAbsolute(): void
    {
        try {
            $_SERVER['QA_SCREENSHOTS_DIR'] = 'var/shots';
            self::assertSame(getcwd().'/var/shots', $this->absoluteScreenshotRoot());

            $_SERVER['QA_SCREENSHOTS_DIR'] = 'C:\shots';
            self::assertSame('C:\shots', $this->absoluteScreenshotRoot());
        } finally {
            $_SERVER['QA_SCREENSHOTS_DIR'] = self::$root;
        }

        self::assertSame(self::$root, $this->absoluteScreenshotRoot());
    }

    #[TestDox('The sidecar keeps the script keys, then adds the page size and the test identity, with unescaped slashes')]
    public function testTheSidecar(): void
    {
        $sidecar = $this->screenshotSidecar('{"url":"https://example.test/a","title":"T","elements":[]}', '001_A', 390, 900);

        self::assertStringContainsString('"https://example.test/a"', $sidecar);
        self::assertSame(
            [
                'url' => 'https://example.test/a',
                'title' => 'T',
                'elements' => [],
                'pageWidth' => 390,
                'pageHeight' => 900,
                'testClass' => self::class,
                'testFile' => 'tests/Unit/ScreenshotTreeTest.php',
            ],
            json_decode($sidecar, true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    #[TestDox('Metadata that is not a JSON object fails the capture and names the stage')]
    public function testMetadataThatIsNotAnObjectFails(): void
    {
        try {
            $this->screenshotSidecar('"text"', '001_A', 1, 1);
        } catch (AssertionFailedError $failure) {
            self::assertStringContainsString('"001_A"', $failure->getMessage());

            return;
        }

        self::fail('A sidecar was written from metadata that is not a JSON object.');
    }
}
