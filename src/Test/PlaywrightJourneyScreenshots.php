<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Test;

use Playwright\Page\PageInterface;

/**
 * Full-page screenshots of each screen that a playwright-php journey settles on,
 * for each viewport and colour scheme, through the Playwright page API.
 * {@see ScreenshotTree} gives the layout, the sidecar and the overrides.
 */
trait PlaywrightJourneyScreenshots
{
    use ScreenshotTree;

    /**
     * Plain expressions and not function strings: the bridge runs a function
     * string through eval() in the page, which a strict content security policy
     * blocks.
     */
    private const DOCUMENT_HEIGHT_EXPRESSION = 'Math.ceil(Math.max(document.documentElement.scrollHeight, document.documentElement.offsetHeight, document.body === null ? 0 : document.body.scrollHeight, document.body === null ? 0 : document.body.offsetHeight))';

    /**
     * Full-page PNGs, one for each viewport and emulated colour scheme.
     *
     * The method skips a label already captured in this test. A capture failure
     * fails the journey, because a missing screenshot with no message removes the
     * value of the capture.
     */
    protected function captureFullPageScreenshot(PageInterface $page, string $label): void
    {
        $stage = $this->nextScreenshotStage($label);

        if (null === $stage) {
            return;
        }

        $original = $page->viewportSize();

        self::assertNotNull($original, 'The capture needs a fixed viewport. Do not create the browser context with "viewport" set to null.');

        try {
            foreach (static::screenshotViewports() as $viewport) {
                $this->captureViewport($page, $viewport, $stage);
            }
        } finally {
            // The journey continues with the correct viewport and scheme, including
            // after a capture failed. The library drops a null colorScheme and sends
            // nothing, so the reset is 'no-override'.
            try {
                $page->emulateMedia(['colorScheme' => 'no-override']);
                $page->setViewportSize($original['width'], $original['height']);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * The document height is only known at the device width, so the method
     * measures the layout there and then increases the viewport to the height of
     * the complete page. The method measures a second time after the increase,
     * because vh-sized elements increase with the viewport. The viewport height
     * then equals the image height and the height in the sidecar.
     *
     * @param array{width: int, height: int} $viewport
     */
    private function captureViewport(PageInterface $page, array $viewport, string $stage): void
    {
        $modes = static::screenshotColorSchemes();

        $page->setViewportSize($viewport['width'], $viewport['height']);

        if ([] !== $modes) {
            $page->emulateMedia(['colorScheme' => $modes[0]]);
        }

        $height = max($viewport['height'], $this->documentHeight($page));

        if ($height > $viewport['height']) {
            $page->setViewportSize($viewport['width'], $height);
            $grown = $this->documentHeight($page);

            if ($grown > $height) {
                $height = $grown;
                $page->setViewportSize($viewport['width'], $height);
            }
        }

        // Each mode uses this layout, so one measurement is sufficient for all of them.
        $json = $page->evaluate(self::metadataScript());

        self::assertIsString($json, \sprintf('Metadata script for "%s" returned %s, expected a JSON string.', $stage, get_debug_type($json)));

        $metadata = $this->screenshotSidecar($json, $stage, $viewport['width'], $height);

        foreach ($modes as $mode) {
            $base = $this->screenshotBase($this->screenshotGroupDirectory($mode, $viewport), $stage);

            $page->emulateMedia(['colorScheme' => $mode]);
            // The bridge writes the file, so the path is absolute. CSS scale gives one
            // image pixel for each CSS pixel at any deviceScaleFactor of the context.
            $page->screenshot($base.'.png', ['scale' => 'css', 'animations' => 'disabled']);

            self::assertNotFalse(@file_put_contents($base.'.json', $metadata), \sprintf('Could not write %s.json.', $base));
        }
    }

    private function documentHeight(PageInterface $page): int
    {
        $height = $page->evaluate(self::DOCUMENT_HEIGHT_EXPRESSION);

        return \is_int($height) || \is_float($height) ? (int) ceil($height) : 0;
    }
}
