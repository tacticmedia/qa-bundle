<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use TacticMedia\QaBundle\Test\JourneyScreenshots;

/**
 * The capture trait runs with no container, so what it reads from the environment
 * and what a host can override is the contract. Capturing itself needs a browser
 * and belongs to the journeys.
 *
 * Using the trait here also runs its #[Before] hook, which is what empties the
 * tree at run start.
 */
final class JourneyScreenshotsTest extends TestCase
{
    use JourneyScreenshots;

    private static string $root;

    public static function setUpBeforeClass(): void
    {
        self::$root = sys_get_temp_dir().'/journey-screenshots-'.bin2hex(random_bytes(6));

        mkdir(self::$root.'/light/390x844/portrait', 0o777, true);
        touch(self::$root.'/light/390x844/portrait/AJourneyE2eTest-testA-001_One.png');

        $_SERVER['QA_SCREENSHOTS_DIR'] = self::$root;
    }

    public static function tearDownAfterClass(): void
    {
        unset($_SERVER['QA_SCREENSHOTS_DIR']);

        if (is_dir(self::$root)) {
            rmdir(self::$root);
        }
    }

    #[TestDox('QA_SCREENSHOTS_DIR wins over the working-directory default')]
    public function testTheRootComesFromTheEnvironment(): void
    {
        self::assertSame(self::$root, self::screenshotRoot());
    }

    #[TestDox('The first test of a run empties the tree, keeping the root itself')]
    public function testThePreparationHookClearedTheTree(): void
    {
        self::assertDirectoryExists(self::$root);
        self::assertSame([], glob(self::$root.'/*'));
    }

    #[TestDox('The shipped viewports cover portrait and landscape, and are all usable sizes')]
    public function testTheDefaultViewports(): void
    {
        $viewports = self::screenshotViewports();

        self::assertNotEmpty($viewports);

        foreach ($viewports as $viewport) {
            self::assertGreaterThan(0, $viewport['width']);
            self::assertGreaterThan(0, $viewport['height']);
        }

        $orientations = array_map(
            static fn (array $viewport): string => $viewport['height'] >= $viewport['width'] ? 'portrait' : 'landscape',
            $viewports,
        );

        self::assertContains('portrait', $orientations);
        self::assertContains('landscape', $orientations);
    }

    public function testTheDefaultColorSchemes(): void
    {
        self::assertSame(['light', 'dark'], self::screenshotColorSchemes());
    }

    #[TestDox('The shared metadata script ships with the package and stays an expression')]
    public function testTheMetadataScriptIsAnExpression(): void
    {
        self::assertFileExists(\dirname(__DIR__, 2).'/resources/capture/metadata.js');

        $script = self::metadataScript();

        self::assertStringStartsWith('(() => {', $script);
        self::assertStringEndsWith('})()', $script);
    }
}
