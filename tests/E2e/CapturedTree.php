<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\E2e;

/**
 * The root and the assertions that both capture E2E tests share. Each test
 * captures /_dev/screenshots and the tall fixture page, in the order and with the
 * labels of js/tests/integration/capture.spec.ts.
 */
trait CapturedTree
{
    private static bool $temporaryRoot = false;

    /**
     * The capture trait empties its root, so a run without QA_SCREENSHOTS_DIR
     * writes to a temporary directory and not to var/screenshots.
     */
    private static function useATemporaryRootWhenNoneIsSet(): void
    {
        $configured = $_SERVER['QA_SCREENSHOTS_DIR'] ?? getenv('QA_SCREENSHOTS_DIR');

        if (\is_string($configured) && '' !== $configured) {
            return;
        }

        $_SERVER['QA_SCREENSHOTS_DIR'] = sys_get_temp_dir().'/qa-bundle-capture-'.bin2hex(random_bytes(6));
        self::$temporaryRoot = true;
    }

    private static function removeTheTemporaryRoot(): void
    {
        if (!self::$temporaryRoot) {
            return;
        }

        $root = static::screenshotRoot();

        unset($_SERVER['QA_SCREENSHOTS_DIR']);
        self::$temporaryRoot = false;

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

        rmdir($root);
    }

    /**
     * Two captures at five viewports in two modes, each with a PNG whose size is
     * the page size in its sidecar.
     */
    private function assertTheJourneyWroteTheTree(string $slug): void
    {
        $root = static::screenshotRoot();

        self::assertCount(10, glob($root.'/*/*/*', \GLOB_ONLYDIR) ?: [], 'Five viewports in light and dark give ten groups.');
        self::assertSame(
            [$slug.'-001_Review empty.png', $slug.'-002_Tall page.png'],
            array_map(basename(...), glob($root.'/light/1920x1080/landscape/'.$slug.'-*.png') ?: []),
            'Each label is captured once, in journey order.',
        );

        foreach (glob($root.'/*/*/*/'.$slug.'-*.png') ?: [] as $image) {
            $size = getimagesize($image);
            $sidecar = $this->sidecar(substr($image, 0, -4).'.json');

            self::assertIsArray($size);
            self::assertSame([$sidecar['pageWidth'], $sidecar['pageHeight']], [$size[0], $size[1]], $image);
        }

        $tall = $this->sidecar($root.'/light/1920x1080/landscape/'.$slug.'-002_Tall page.json');

        self::assertGreaterThan(1080, $tall['pageHeight'], 'The viewport grows to the height of the page.');
        self::assertNotEmpty($tall['elements']);
    }

    /**
     * @return array<string, mixed>
     */
    private function sidecar(string $path): array
    {
        $sidecar = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);

        self::assertIsArray($sidecar, $path);

        return $sidecar;
    }
}
