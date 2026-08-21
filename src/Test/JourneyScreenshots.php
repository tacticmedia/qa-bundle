<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Test;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Component\Panther\Client;

/**
 * Full-page screenshots of every screen a journey settles on, per viewport and
 * color scheme:
 * <root>/<mode>/<WxH>/<orientation>/<TestCase>-<testMethod>-<NNN>_<label>.png,
 * where NNN is the capture's position in the journey. Each PNG gets a .json
 * sidecar of the same basename holding the page URL, title, element geometry and
 * the test the capture came from, which the review tool resolves annotations
 * against ({@see \TacticMedia\QaBundle\Review\ScreenMetadata}). The tree is
 * emptied at the start of each run, so it only ever holds the latest one. Use
 * the trait once, on a shared journey base class: the clear-once guard is a
 * trait static, which PHP copies into every class that uses the trait directly,
 * so a second using class would re-empty the tree mid-run.
 *
 * The trait carries no container: the root comes from QA_SCREENSHOTS_DIR or
 * defaults to var/screenshots under the working directory, and viewports and
 * colour schemes are protected static methods a test case overrides.
 */
trait JourneyScreenshots
{
    /**
     * Boxes and identities of the elements worth naming, in page pixels. Nowdoc,
     * never heredoc: the script contains ${...} template literals.
     */
    private const METADATA_SCRIPT = <<<'SCRIPT'
        (() => {
            const SEMANTIC = new Set(['a', 'button', 'input', 'select', 'textarea', 'label', 'form',
                'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'img', 'svg', 'nav', 'main', 'header', 'footer', 'aside', 'dialog', 'details', 'summary',
                'li', 'p', 'ul', 'ol', 'dl', 'turbo-frame']);
            const ATTRIBUTES = ['href', 'src', 'name', 'type', 'placeholder', 'alt', 'title', 'role',
                'aria-label', 'data-controller', 'for', 'action'];
            const cap = (value, limit) => value.length > limit ? value.slice(0, limit) : value;

            const selectorFor = (element) => {
                const parts = [];
                for (let node = element; node && node !== document.body; node = node.parentElement) {
                    if (node.id) {
                        parts.unshift('#' + CSS.escape(node.id));
                        return parts.join(' > ');
                    }
                    const tag = node.localName;
                    const peers = node.parentElement
                        ? [...node.parentElement.children].filter((child) => child.localName === tag)
                        : [];
                    parts.unshift(peers.length > 1 ? `${tag}:nth-of-type(${peers.indexOf(node) + 1})` : tag);
                }
                parts.unshift('body');
                return parts.join(' > ');
            };

            const directText = (element) => [...element.childNodes]
                .filter((node) => node.nodeType === Node.TEXT_NODE)
                .map((node) => node.textContent)
                .join(' ')
                .replace(/\s+/g, ' ')
                .trim();

            const elements = [];
            for (const element of document.body.querySelectorAll('*')) {
                const rect = element.getBoundingClientRect();
                if (rect.width < 2 || rect.height < 2) {
                    continue;
                }
                const tag = element.localName;
                const own = directText(element);
                if (!element.id && !SEMANTIC.has(tag) && '' === own
                    && !element.hasAttribute('data-controller') && !element.hasAttribute('role')) {
                    continue;
                }
                const attributes = {};
                for (const name of ATTRIBUTES) {
                    const value = element.getAttribute(name);
                    if (value) {
                        attributes[name] = cap(value, 120);
                    }
                }
                // innerText does not exist on SVGElement.
                const inner = (element.innerText ?? '').replace(/\s+/g, ' ').trim();
                elements.push({
                    selector: selectorFor(element),
                    tag,
                    x: Math.round(rect.x + window.scrollX),
                    y: Math.round(rect.y + window.scrollY),
                    width: Math.round(rect.width),
                    height: Math.round(rect.height),
                    text: inner.length > 0 && inner.length <= 200 ? cap(inner, 120) : (own ? cap(own, 120) : null),
                    classes: element.getAttribute('class') ? cap(element.getAttribute('class'), 200) : null,
                    attributes,
                });
            }

            return JSON.stringify({
                url: location.href,
                title: document.title,
                elements,
            });
        })()
        SCRIPT;

    private static bool $screenshotsCleared = false;

    private ?string $screenshotSlug = null;

    private int $screenshotSequence = 0;

    /**
     * @var list<string>
     */
    private array $capturedStages = [];

    /**
     * iPhone portrait, iPad portrait and landscape, common desktop, high-end
     * laptop (MacBook Pro 16 logical resolution). CSS pixels; the orientation
     * directory follows from the shape.
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
        $this->capturedStages = [];
        // name() is PHPUnit-internal but the only source of the running test's
        // method name.
        $this->screenshotSlug = substr((string) strrchr(static::class, '\\'), 1).'-'.$this->name(); // @phpstan-ignore method.internal
    }

    /**
     * Full-page PNGs, one per viewport and emulated color scheme, via the
     * chromedriver CDP endpoint.
     *
     * A stage already captured in this test is skipped, so a journey helper
     * called more than once shoots its screen once and a deliberate label reuse
     * suppresses a known repeat. A capture failure fails the journey - a
     * silently missing screenshot defeats the point of taking them.
     */
    protected function captureFullPageScreenshot(Client $client, string $stage): void
    {
        $driver = $client->getWebDriver();

        if (!$driver instanceof RemoteWebDriver || \in_array($stage, $this->capturedStages, true)) {
            return;
        }

        $this->capturedStages[] = $stage;
        $devtools = new ChromeDevToolsDriver($driver);
        $prefix = str_pad((string) ++$this->screenshotSequence, 3, '0', \STR_PAD_LEFT);

        try {
            foreach (static::screenshotViewports() as $viewport) {
                $this->captureViewport($devtools, $viewport, $prefix.'_'.$this->fileSafe($stage));
            }
        } finally {
            // The journey keeps running with the real viewport and scheme,
            // even when a capture failed halfway.
            try {
                $devtools->execute('Emulation.clearDeviceMetricsOverride');
                $this->emulateColorScheme($devtools, '');
            } catch (\Throwable) {
            }
        }
    }

    /**
     * The document height only exists at the device width, so the layout is
     * measured there first and the viewport then grown to fit the whole page -
     * captureBeyondViewport alone can return a viewport-sized image. One
     * re-measure after growing, because vh-sized elements grow along.
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

        // Every mode shares this layout, so one measurement covers them all.
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

            // A deleted bind-mount source leaves an unreadable stub here, and is the only
            // condition under which this produces no output yet still passes. Suppressed
            // so the assertion reports the cause rather than a warning naming a path.
            if (!is_dir($directory) && !@mkdir($directory, 0777, true)) {
                self::fail(\sprintf('Could not create %s. Recreate the screenshot root and restart the service that owns it.', $directory));
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
     * A missing sidecar defeats the point of writing them, so a failed evaluation
     * fails the journey, same posture as a failed screenshot.
     *
     * The page size is stamped from the capture clip rather than read from the
     * page: when the re-measure above grows the document again, the viewport
     * override still holds the previous height, so window.innerHeight comes back a
     * few pixels short of the image. The resolver matches a stored rectangle
     * against these numbers, so they have to be the PNG's exactly.
     */
    private function captureMetadata(ChromeDevToolsDriver $devtools, string $stage, int $width, int $height): string
    {
        $result = $devtools->execute('Runtime.evaluate', [
            'expression' => self::METADATA_SCRIPT,
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
     * Where the journey lives, relative to the working directory when it sits
     * under it, so the prompt names a path an agent can open.
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
     * A separator in a label would write into a directory that does not exist.
     */
    private function fileSafe(string $stage): string
    {
        return (string) preg_replace('/[^A-Za-z0-9 _-]+/', '-', $stage);
    }

    /**
     * Empties the tree so what is left is always the latest run and nothing has
     * to be cleared by hand. The once-only guard is a trait static, so the
     * guarantee is per using class: share one base class or the tree is
     * re-emptied mid-run. The root itself survives - it is
     * typically a bind-mount target, and deleting it leaves the container with an
     * unreadable stub until the service restarts.
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
     * Emulates prefers-color-scheme; an empty value clears the emulation.
     */
    private function emulateColorScheme(ChromeDevToolsDriver $devtools, string $value): void
    {
        $devtools->execute('Emulation.setEmulatedMedia', [
            'features' => [['name' => 'prefers-color-scheme', 'value' => $value]],
        ]);
    }
}
