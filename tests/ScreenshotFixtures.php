<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests;

/**
 * Writes and removes the screenshot tree a functional test reviews. The captures
 * are real PNGs, because the raw action serves them and the cropper decodes them.
 */
trait ScreenshotFixtures
{
    public const SCREEN = 'FixtureJourneyE2eTest-testJourney-001_Product List';
    public const SCREEN_NEXT = 'FixtureJourneyE2eTest-testJourney-002_Product Detail';
    public const PAGE_URL = 'https://localhost/fixture';
    public const PAGE_TITLE = 'Fixture Page';
    public const FIXTURE_SELECTOR = 'body > main > a';

    protected function seedFixtureTree(): void
    {
        $this->removeFixtureTree();

        foreach ([['light', '1920x1080', 'landscape'], ['dark', '1920x1080', 'landscape'], ['light', '390x844', 'portrait']] as [$mode, $viewport, $orientation]) {
            $this->writeCapture($mode, $viewport, $orientation, self::SCREEN);
            $this->writeCapture($mode, $viewport, $orientation, self::SCREEN_NEXT);
        }
    }

    /** The fixture host whose var/ holds the tree. Overridden by a test driving another kernel. */
    protected function fixtureProjectDirectory(): string
    {
        return __DIR__.'/Fixtures/app';
    }

    /** Both roots are derived rather than stored, so a test that skips before seeding still tears down. */
    protected function removeFixtureTree(): void
    {
        $this->removeTree($this->fixtureScreenshotRoot());
        $this->removeTree($this->fixtureProjectDirectory().'/var/review');
    }

    protected function fixtureScreenshotRoot(): string
    {
        return $this->fixtureProjectDirectory().'/var/screenshots';
    }

    private function writeCapture(string $mode, string $viewport, string $orientation, string $name): void
    {
        $directory = \sprintf('%s/%s/%s/%s', $this->fixtureScreenshotRoot(), $mode, $viewport, $orientation);

        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        [$width, $height] = array_map(intval(...), explode('x', $viewport));
        $width = max(1, $width);
        $height = max(1, $height);

        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 240, 240, 245));
        imagepng($image, $directory.'/'.$name.'.png');

        file_put_contents($directory.'/'.$name.'.json', (string) json_encode([
            'url' => self::PAGE_URL,
            'title' => self::PAGE_TITLE,
            'pageWidth' => $width,
            'pageHeight' => $height,
            'testClass' => 'App\Tests\E2e\FixtureJourneyE2eTest',
            'testFile' => 'tests/E2e/FixtureJourneyE2eTest.php',
            'elements' => [
                [
                    'selector' => 'body > main',
                    'tag' => 'main',
                    'x' => 0, 'y' => 0, 'width' => $width, 'height' => $height,
                    'text' => null, 'classes' => null, 'attributes' => [],
                ],
                // Centred, which is where a drag across the middle of the image lands.
                [
                    'selector' => self::FIXTURE_SELECTOR,
                    'tag' => 'a',
                    'x' => intdiv($width, 2) - 100,
                    'y' => intdiv($height, 2) - 60,
                    'width' => 200,
                    'height' => 120,
                    'text' => 'Fixture Link',
                    'classes' => 'text-red-600',
                    'attributes' => ['href' => '/fixture'],
                ],
            ],
        ], \JSON_THROW_ON_ERROR));
    }

    private function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
