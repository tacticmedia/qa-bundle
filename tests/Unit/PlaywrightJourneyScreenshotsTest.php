<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Playwright\Page\PageInterface;
use TacticMedia\QaBundle\Test\PlaywrightJourneyScreenshots;

/**
 * The order of the page calls and the failure paths, against a stub page. The
 * capture in a real browser is tested by PlaywrightCaptureE2eTest.
 */
final class PlaywrightJourneyScreenshotsTest extends TestCase
{
    use PlaywrightJourneyScreenshots;
    private const METADATA = '{"url":"http://example.test/","title":"T","elements":[]}';

    private static string $root;

    /**
     * @var list<array{0: string, 1?: mixed, 2?: mixed}>
     */
    private array $calls = [];

    private int $measurements = 0;

    public static function setUpBeforeClass(): void
    {
        self::$root = sys_get_temp_dir().'/playwright-journey-screenshots-'.bin2hex(random_bytes(6));

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

    #[TestDox('The viewport grows to the measured height, twice, and the sidecar records the last height')]
    public function testTheViewportGrowsToTheDocument(): void
    {
        $this->captureFullPageScreenshot($this->page(), 'Home');

        self::assertSame(
            [
                ['setViewportSize', 390, 844],
                ['emulateMedia', ['colorScheme' => 'light']],
                ['evaluate', 'height'],
                ['setViewportSize', 390, 2000],
                ['evaluate', 'height'],
                ['setViewportSize', 390, 2100],
                ['evaluate', 'metadata'],
                ['emulateMedia', ['colorScheme' => 'light']],
                ['screenshot', self::$root.'/light/390x844/portrait/PlaywrightJourneyScreenshotsTest-testTheViewportGrowsToTheDocument-001_Home.png', ['scale' => 'css', 'animations' => 'disabled']],
                ['emulateMedia', ['colorScheme' => 'dark']],
                ['screenshot', self::$root.'/dark/390x844/portrait/PlaywrightJourneyScreenshotsTest-testTheViewportGrowsToTheDocument-001_Home.png', ['scale' => 'css', 'animations' => 'disabled']],
            ],
            \array_slice($this->calls, 1, 11),
        );

        $sidecar = json_decode(
            (string) file_get_contents(self::$root.'/dark/390x844/portrait/PlaywrightJourneyScreenshotsTest-testTheViewportGrowsToTheDocument-001_Home.json'),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($sidecar);
        self::assertSame([390, 2100], [$sidecar['pageWidth'], $sidecar['pageHeight']]);
        self::assertCount(10, glob(self::$root.'/*/*/*/PlaywrightJourneyScreenshotsTest-testTheViewportGrowsToTheDocument-001_Home.json') ?: []);
    }

    #[TestDox('After the capture the journey has its viewport and the default colour scheme again')]
    public function testTheCaptureRestoresThePage(): void
    {
        $this->captureFullPageScreenshot($this->page(), 'Home');

        self::assertSame(
            [['emulateMedia', ['colorScheme' => 'no-override']], ['setViewportSize', 1280, 720]],
            \array_slice($this->calls, -2),
        );
    }

    #[TestDox('A failed screenshot fails the capture, and the page is restored')]
    public function testAFailedScreenshotRestoresThePage(): void
    {
        $failure = null;

        try {
            $this->captureFullPageScreenshot($this->page(failingScreenshot: true), 'Home');
        } catch (\RuntimeException $caught) {
            $failure = $caught;
        }

        self::assertSame('The browser closed.', $failure?->getMessage());
        self::assertSame(
            [['emulateMedia', ['colorScheme' => 'no-override']], ['setViewportSize', 1280, 720]],
            \array_slice($this->calls, -2),
        );
    }

    #[TestDox('A label already captured in this test does not reach the page, and the next label is 002')]
    public function testARepeatedLabelIsSkipped(): void
    {
        $page = $this->page();

        $this->captureFullPageScreenshot($page, 'Home');
        $this->calls = [];
        $this->captureFullPageScreenshot($page, 'Home');

        self::assertSame([], $this->calls);

        $this->captureFullPageScreenshot($page, 'Cart');

        self::assertFileExists(self::$root.'/light/1920x1080/landscape/PlaywrightJourneyScreenshotsTest-testARepeatedLabelIsSkipped-002_Cart.json');
    }

    #[TestDox('A page with no fixed viewport fails the capture with the cause')]
    public function testAPageWithNoViewportFails(): void
    {
        try {
            $this->captureFullPageScreenshot($this->page(viewport: null), 'Home');
        } catch (AssertionFailedError $failure) {
            self::assertStringContainsString('fixed viewport', $failure->getMessage());
            self::assertSame([['viewportSize']], $this->calls);

            return;
        }

        self::fail('The capture ran on a page with no fixed viewport.');
    }

    /**
     * Each first measurement returns 2000 and each second returns 2100, so every
     * default viewport grows twice.
     *
     * @param array{width: int, height: int}|null $viewport
     */
    private function page(?array $viewport = ['width' => 1280, 'height' => 720], bool $failingScreenshot = false): PageInterface
    {
        $page = $this->createStub(PageInterface::class);

        $page->method('viewportSize')->willReturnCallback(function () use ($viewport): ?array {
            $this->calls[] = ['viewportSize'];

            return $viewport;
        });
        $page->method('setViewportSize')->willReturnCallback(function (int $width, int $height) use ($page): PageInterface {
            $this->calls[] = ['setViewportSize', $width, $height];

            return $page;
        });
        $page->method('emulateMedia')->willReturnCallback(function (mixed $options) use ($page): PageInterface {
            $this->calls[] = ['emulateMedia', $options];

            return $page;
        });
        $page->method('evaluate')->willReturnCallback(function (string $expression): mixed {
            if (self::metadataScript() === $expression) {
                $this->calls[] = ['evaluate', 'metadata'];

                return self::METADATA;
            }

            $this->calls[] = ['evaluate', 'height'];

            return 0 === $this->measurements++ % 2 ? 2000 : 2100;
        });
        $page->method('screenshot')->willReturnCallback(function (?string $path, mixed $options) use ($failingScreenshot): string {
            $this->calls[] = ['screenshot', $path, $options];

            if ($failingScreenshot) {
                throw new \RuntimeException('The browser closed.');
            }

            return (string) $path;
        });

        return $page;
    }
}
