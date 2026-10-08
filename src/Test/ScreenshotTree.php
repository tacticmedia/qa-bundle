<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Test;

use PHPUnit\Framework\Attributes\Before;

/**
 * The part of a capture that does not depend on the browser driver:
 * <root>/<mode>/<WxH>/<orientation>/<TestCase>-<testMethod>-<NNN>_<label>.png,
 * where NNN is the position of the capture in the scenario. Each PNG has a .json
 * sidecar with the same basename. The sidecar contains the page URL, the title, the
 * element geometry and the test that produced the capture, and the review tool
 * matches annotations against it
 * ({@see \TacticMedia\QaBundle\Review\ScreenMetadata}). Each run empties the
 * tree, so the tree contains the latest run only.
 *
 * {@see JourneyScreenshots} and {@see PlaywrightJourneyScreenshots} add the
 * browser calls. Use one of them once, on a shared journey base class: the
 * clear-once guard is a trait static, which PHP copies into each class that uses
 * the trait directly, so a second such class empties the tree again during the run.
 *
 * The trait does not use the container. The root comes from QA_SCREENSHOTS_DIR,
 * or from var/screenshots under the working directory. Viewports and colour
 * schemes are protected static methods that a test case overrides.
 */
trait ScreenshotTree
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
        $this->screenshotSlug = (new \ReflectionClass(static::class))->getShortName().'-'.$this->name(); // @phpstan-ignore method.internal
    }

    /**
     * NNN_label for the next capture, or null for a label already captured in this
     * test, so a journey helper that is called more than once captures its screen
     * once, and a repeated label suppresses a known repeat.
     */
    private function nextScreenshotStage(string $label): ?string
    {
        if (\in_array($label, $this->capturedLabels, true)) {
            return null;
        }

        $this->capturedLabels[] = $label;

        return str_pad((string) ++$this->screenshotSequence, 3, '0', \STR_PAD_LEFT).'_'.$this->fileSafe($label);
    }

    /**
     * Creates the group directory when it does not exist.
     *
     * @param array{width: int, height: int} $viewport
     */
    private function screenshotGroupDirectory(string $mode, array $viewport): string
    {
        $directory = \sprintf(
            '%s/%s/%dx%d/%s',
            $this->absoluteScreenshotRoot(),
            $mode,
            $viewport['width'],
            $viewport['height'],
            $viewport['height'] >= $viewport['width'] ? 'portrait' : 'landscape',
        );

        // The warning is suppressed so that the failure message is the only output.
        if (!is_dir($directory) && !@mkdir($directory, 0777, true)) {
            self::fail(\sprintf('Could not create %s.', $directory));
        }

        return $directory;
    }

    /**
     * The path without the extension, shared by the PNG and its sidecar.
     */
    private function screenshotBase(string $directory, string $stage): string
    {
        return \sprintf('%s/%s-%s', $directory, $this->screenshotSlug ?? 'unknown-test', $stage);
    }

    /**
     * A browser process with another working directory can write the PNG, so a
     * relative root resolves against the working directory of PHP.
     */
    private function absoluteScreenshotRoot(): string
    {
        $root = static::screenshotRoot();

        return 1 === preg_match('#^([/\\\\]|[A-Za-z]:[/\\\\])#', $root) ? $root : getcwd().'/'.$root;
    }

    /**
     * The page size comes from the capture. The resolver matches a stored
     * rectangle against these numbers, so they must equal the size of the PNG.
     */
    private function screenshotSidecar(string $json, string $stage, int $width, int $height): string
    {
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
     * Boxes and identities of the elements that the review reports, in page
     * pixels. The JavaScript capture package reads the same file, so the script is
     * on disk and not inline here. It must stay an expression, because each
     * producer evaluates it for its value.
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
}
