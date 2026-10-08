<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Test;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Symfony\Component\Panther\Client;

/**
 * Full-page screenshots of each screen that a Symfony Panther journey settles on,
 * for each viewport and colour scheme, through the chromedriver CDP endpoint.
 * {@see ScreenshotTree} gives the layout, the sidecar and the overrides.
 */
trait JourneyScreenshots
{
    use ScreenshotTree;

    /**
     * Full-page PNGs, one for each viewport and emulated colour scheme.
     *
     * The method skips a label already captured in this test. A capture failure
     * fails the journey, because a missing screenshot with no message removes the
     * value of the capture.
     */
    protected function captureFullPageScreenshot(Client $client, string $label): void
    {
        $driver = $client->getWebDriver();

        if (!$driver instanceof RemoteWebDriver) {
            return;
        }

        $stage = $this->nextScreenshotStage($label);

        if (null === $stage) {
            return;
        }

        $devtools = new ChromeDevToolsDriver($driver);

        try {
            foreach (static::screenshotViewports() as $viewport) {
                $this->captureViewport($devtools, $viewport, $stage);
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

        foreach (static::screenshotColorSchemes() as $mode) {
            $directory = $this->screenshotGroupDirectory($mode, $viewport);

            $this->emulateColorScheme($devtools, $mode);

            $shot = $devtools->execute('Page.captureScreenshot', $parameters);
            $data = \is_string($shot['data'] ?? null) ? base64_decode($shot['data'], true) : false;

            self::assertNotFalse($data, \sprintf('Chrome returned no image data for "%s" at %dx%d %s.', $stage, $viewport['width'], $viewport['height'], $mode));
            self::assertNotSame('', $data, \sprintf('Chrome returned an empty image for "%s" at %dx%d %s.', $stage, $viewport['width'], $viewport['height'], $mode));

            $base = $this->screenshotBase($directory, $stage);

            self::assertNotFalse(@file_put_contents($base.'.png', $data), \sprintf('Could not write %s.png.', $base));
            self::assertNotFalse(@file_put_contents($base.'.json', $metadata), \sprintf('Could not write %s.json.', $base));
        }
    }

    /**
     * A missing sidecar removes the value of the capture, so a failed evaluation
     * fails the journey, in the same way as a failed screenshot.
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

        return $this->screenshotSidecar($json, $stage, $width, $height);
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
