<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * Cuts the reviewed region out of a full-page screenshot so the agent reads a
 * small targeted image instead of one that can be 10000 px tall.
 *
 * The path handed back is what the prompt prints, so it is made relative to the
 * project directory whenever the crop lands inside it - an agent can open that.
 * A review directory outside the project yields an absolute path.
 */
final readonly class SelectionCropper
{
    private const SUBDIRECTORY = 'crops';
    private const PADDING = 80;
    private const OUTLINE = 2;

    private string $directory;

    public function __construct(
        string $reviewDirectory,
        private ?string $projectDirectory = null,
    ) {
        $this->directory = rtrim($reviewDirectory, '/').'/'.self::SUBDIRECTORY;
    }

    public function clear(): void
    {
        foreach (glob($this->directory.'/*.png') ?: [] as $file) {
            unlink($file);
        }
    }

    /**
     * Fails open: the prompt still carries the coordinates, so a crop that could
     * not be produced degrades the instructions rather than breaking the page.
     *
     * Both images stay function-local. A full-page PNG decodes to 50-100 MB, so
     * the prompt loop depends on each one being released when its frame returns.
     *
     * @param array{x: int, y: int, width: int, height: int} $rectangle
     *
     * @return string|null path of the written crop, as the prompt should print it
     */
    public function crop(string $sourcePath, array $rectangle, string $feedbackId): ?string
    {
        $source = @imagecreatefrompng($sourcePath);

        return false === $source ? null : $this->render($source, $rectangle, $feedbackId);
    }

    /**
     * @param array{x: int, y: int, width: int, height: int} $rectangle
     */
    private function render(\GdImage $source, array $rectangle, string $feedbackId): ?string
    {
        $left = max(0, $rectangle['x'] - self::PADDING);
        $top = max(0, $rectangle['y'] - self::PADDING);
        $width = min(imagesx($source), $rectangle['x'] + $rectangle['width'] + self::PADDING) - $left;
        $height = min(imagesy($source), $rectangle['y'] + $rectangle['height'] + self::PADDING) - $top;

        if (1 > $width || 1 > $height) {
            return null;
        }

        $crop = imagecrop($source, ['x' => $left, 'y' => $top, 'width' => $width, 'height' => $height]);

        if (false === $crop) {
            return null;
        }

        $red = imagecolorallocate($crop, 255, 0, 0);

        if (false === $red) {
            return null;
        }

        imagesetthickness($crop, self::OUTLINE);
        imagerectangle(
            $crop,
            $rectangle['x'] - $left,
            $rectangle['y'] - $top,
            $rectangle['x'] + $rectangle['width'] - $left,
            $rectangle['y'] + $rectangle['height'] - $top,
            $red,
        );

        return $this->store($crop, $feedbackId);
    }

    private function store(\GdImage $crop, string $feedbackId): ?string
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o777, true)) {
            return null;
        }

        $path = $this->directory.'/'.$feedbackId.'.png';

        return imagepng($crop, $path) ? $this->displayPath($path) : null;
    }

    private function displayPath(string $path): string
    {
        $prefix = null === $this->projectDirectory ? null : rtrim($this->projectDirectory, '/').'/';

        return null !== $prefix && str_starts_with($path, $prefix) ? substr($path, \strlen($prefix)) : $path;
    }
}
