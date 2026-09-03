<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Test;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Component\Panther\Client;

/**
 * Full-page screenshots of each screen that a journey settles on, for each
 * viewport and colour scheme:
 * <root>/<mode>/<WxH>/<orientation>/<TestCase>-<testMethod>-<NNN>_<label>.png,
 * where NNN is the position of the capture in the scenario. Each PNG has a .json
 * sidecar with the same basename. The sidecar contains the page URL, the title, the
 * element geometry and the test that produced the capture, and the review tool
 * matches annotations against it
 * ({@see \TacticMedia\QaBundle\Review\ScreenMetadata}). Each run empties the
 * tree, so the tree contains the latest run only. Use the trait once, on a shared
 * journey base class: the clear-once guard is a trait static, which PHP copies
 * into each class that uses the trait directly, so a second such class empties
 * the tree again during the run.
 *
 * The trait does not use the container. The root comes from QA_SCREENSHOTS_DIR,
 * or from var/screenshots under the working directory. Viewports and colour
 * schemes are protected static methods that a test case overrides.
 */
trait JourneyScreenshots
{
    private static ?string $metadataScript = null;

    private static bool $screenshotsCleared = false;

    private ?string $screenshotSlug = null;

    private int $screenshotSequence = 0;

    /**
     * @var list<string>
     */
    private array $capturedLabels = [];

    /**
     * iPhone portrait, iPad portrait and landscape, common desktop, high-end
     * laptop. CSS pixels; the orientation directory follows from the shape.
     *
     * @return list<array{width: int, height: int}>
     */
    protected static function screenshotViewports(): array
    {
        return [
            ['width' => 390, 'height' => 844],
            ['width' => 768, 'height' => 1024],
            ['width' => 1024, 'height' => 768],
            ['width' => 1920, 'height' => 1080],
            ['width' => 1728, 'height' => 1117],
        ];
    }

    /**
     * prefers-color-scheme values to emulate; each becomes a top-level directory.
     *
     * @return list<string>
     */
    protected static function screenshotColorSchemes(): array
    {
        return ['light', 'dark'];
    }

    protected static function screenshotRoot(): string
    {
        $configured = $_SERVER['QA_SCREENSHOTS_DIR'] ?? getenv('QA_SCREENSHOTS_DIR');

        return \is_string($configured) && '' !== $configured ? $configured : getcwd().'/var/screenshots';
    }

    #[Before]
    protected function prepareScreenshots(): void
    {
        if (!self::$screenshotsCleared) {
            self::$screenshotsCleared = true;
            $this->clearScreenshots();
        }

        $this->screenshotSequence = 0;
        $this->capturedLabels = [];
        // name() and nameWithDataSet() are both PHPUnit-internal; name() keeps the
        // scenario at the method name across data sets.
        $this->screenshotSlug = substr((string) strrchr(static::class, '\\'), 1).'-'.$this->name(); // @phpstan-ignore method.internal
    }

    /**
     * Full-page PNGs, one for each viewport and emulated colour scheme, through
     * the chromedriver CDP endpoint.
     *
     * The method skips a label already captured in this test, so a journey helper
     * that is called more than once captures its screen once, and a repeated label
     * suppresses a known repeat. A capture failure fails the journey, because a
     * missing screenshot with no message removes the value of the capture.
     */
    protected function captureFullPageScreenshot(Client $client, string $label): void
    {
        $driver = $client->getWebDriver();

        if (!$driver instanceof RemoteWebDriver || \in_array($label, $this->capturedLabels, true)) {
            return;
        }

        $this->capturedLabels[] = $label;
        $devtools = new ChromeDevToolsDriver($driver);
        $prefix = str_pad((string) ++$this->screenshotSequence, 3, '0', \STR_PAD_LEFT);

        try {
            foreach (static::screenshotViewports() as $viewport) {
                $this->captureViewport($devtools, $viewport, $prefix.'_'.$this->fileSafe($label));
            }
        } finally {
            // The journey continues with the correct viewport and scheme, including
            // after a capture failed.
            try {
                $devtools->execute('Emulation.clearDeviceMetricsOverride');
                $this->emulateColorScheme($devtools, '');
            } catch (\Throwable) {
            }
        }
    }

    /**
     * The document height is only known at the device width, so the method
     * measures the layout there and then increases the viewport to the height of
     * the complete page, which is also the clip height. The method measures a
     * second time after the increase, because vh-sized elements increase with the
     * viewport.
     *
     * @param array{width: int, height: int} $viewport
     */
    private function captureViewport(ChromeDevToolsDriver $devtools, array $viewport, string $stage): void
    {
        $this->overrideViewport($devtools, $viewport['width'], $viewport['height']);
        $height = max($viewport['height'], $this->contentHeight($devtools) ?? 0);

        if ($height > $viewport['height']) {
            $this->overrideViewport($devtools, $viewport['width'], $height);
            $height = max($height, $this->contentHeight($devtools) ?? 0);
        }

        $parameters = [
            'captureBeyondViewport' => true,
            'clip' => [
                'x' => 0,
                'y' => 0,
                'width' => (float) $viewport['width'],
                'height' => (float) $height,
                'scale' => 1,
            ],
        ];

        // Each mode uses this layout, so one measurement is sufficient for all of them.
        $metadata = $this->captureMetadata($devtools, $stage, $viewport['width'], $height);
        $orientation = $viewport['height'] >= $viewport['width'] ? 'portrait' : 'landscape';

        foreach (static::screenshotColorSchemes() as $mode) {
            $directory = \sprintf(
                '%s/%s/%dx%d/%s',
                static::screenshotRoot(),
                $mode,
                $viewport['width'],
                $viewport['height'],
                $orientation,
            );

            // The warning is suppressed so that the failure message is the only output.
            if (!is_dir($directory) && !@mkdir($directory, 0777, true)) {
                self::fail(\sprintf('Could not create %s.', $directory));
            }

            $this->emulateColorScheme($devtools, $mode);

            $shot = $devtools->execute('Page.captureScreenshot', $parameters);
            $data = \is_string($shot['data'] ?? null) ? base64_decode($shot['data'], true) : false;

            self::assertNotFalse($data, \sprintf('Chrome returned no image data for "%s" at %dx%d %s.', $stage, $viewport['width'], $viewport['height'], $mode));
            self::assertNotSame('', $data, \sprintf('Chrome returned an empty image for "%s" at %dx%d %s.', $stage, $viewport['width'], $viewport['height'], $mode));

            $base = \sprintf(
                '%s/%s-%s',
                $directory,
                $this->screenshotSlug ?? 'unknown-test',
                $stage,
            );

            self::assertNotFalse(@file_put_contents($base.'.png', $data), \sprintf('Could not write %s.png.', $base));
            self::assertNotFalse(@file_put_contents($base.'.json', $metadata), \sprintf('Could not write %s.json.', $base));
        }
    }

    /**
     * Boxes and identities of the elements that the review reports, in page
     * pixels. The JavaScript capture package reads the same file, so the script is
     * on disk and not inline here. It must stay an expression, because both
     * producers evaluate it for its value.
     */
    private static function metadataScript(): string
    {
        if (null !== self::$metadataScript) {
            return self::$metadataScript;
        }

        $path = \dirname(__DIR__, 2).'/resources/capture/metadata.js';
        $script = @file_get_contents($path);

        self::assertIsString($script, \sprintf('Could not read %s. Reinstall tacticmedia/qa-bundle.', $path));

        return self::$metadataScript = rtrim($script);
    }

    /**
     * A missing sidecar removes the value of the capture, so a failed evaluation
     * fails the journey, in the same way as a failed screenshot.
     *
     * The page size comes from the capture clip. The resolver matches a stored
     * rectangle against these numbers, so they must equal the size of the PNG.
     */
    private function captureMetadata(ChromeDevToolsDriver $devtools, string $stage, int $width, int $height): string
    {
        $result = $devtools->execute('Runtime.evaluate', [
            'expression' => self::metadataScript(),
            'returnByValue' => true,
        ]);

        $json = $result['result']['value'] ?? null;
        $failure = $result['exceptionDetails']['exception']['description'] ?? 'no value returned';

        self::assertIsString($json, \sprintf('Metadata script failed for "%s": %s', $stage, \is_string($failure) ? $failure : 'no value returned'));

        $metadata = json_decode($json, true);

        self::assertIsArray($metadata, \sprintf('Metadata for "%s" is not a JSON object.', $stage));

        return json_encode(
            [
                ...$metadata,
                'pageWidth' => $width,
                'pageHeight' => $height,
                'testClass' => static::class,
                'testFile' => $this->testFile(),
            ],
            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * The path of the journey file, relative to the working directory when it is
     * inside it, so that the prompt gives a path an agent can open.
     */
    private function testFile(): ?string
    {
        $file = (new \ReflectionClass(static::class))->getFileName();

        if (false === $file) {
            return null;
        }

        $root = getcwd();

        return \is_string($root) && str_starts_with($file, $root.'/') ? substr($file, \strlen($root) + 1) : $file;
    }

    /**
     * A separator in a label would address a directory that does not exist.
     */
    private function fileSafe(string $label): string
    {
        return (string) preg_replace('/[^A-Za-z0-9 _-]+/', '-', $label);
    }

    /**
     * Empties the tree, so that it contains the latest run and needs no manual
     * removal. The root directory stays, because it is usually a bind-mount
     * target.
     */
    private function clearScreenshots(): void
    {
        $root = static::screenshotRoot();

        if (!is_dir($root)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            \assert($entry instanceof \SplFileInfo);

            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
    }

    private function overrideViewport(ChromeDevToolsDriver $devtools, int $width, int $height): void
    {
        $devtools->execute('Emulation.setDeviceMetricsOverride', [
            'width' => $width,
            'height' => $height,
            'deviceScaleFactor' => 1,
            'mobile' => false,
        ]);
    }

    private function contentHeight(ChromeDevToolsDriver $devtools): ?int
    {
        $metrics = $devtools->execute('Page.getLayoutMetrics');
        $size = $metrics['cssContentSize'] ?? $metrics['contentSize'] ?? null;

        return \is_array($size) && isset($size['height']) ? (int) ceil((float) $size['height']) : null;
    }

    /**
     * An empty value clears the emulation.
     */
    private function emulateColorScheme(ChromeDevToolsDriver $devtools, string $value): void
    {
        $devtools->execute('Emulation.setEmulatedMedia', [
            'features' => [['name' => 'prefers-color-scheme', 'value' => $value]],
        ]);
    }
}
