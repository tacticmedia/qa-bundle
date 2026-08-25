<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * Extracts the reviewed region from a full-page screenshot, so that the agent
 * reads a small image instead of an image that can be 10000 px high.
 *
 * The prompt prints the returned path. The path is relative to the project
 * directory when the crop is inside it, so an agent can open it. A review
 * directory outside the project gives an absolute path.
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
     * Fails open. The prompt contains the coordinates, so a crop that cannot be
     * produced reduces the detail in the instructions but does not cause an error.
     *
     * Both images stay local to the function. A full-page PNG decodes to 50-100 MB,
     * so the prompt loop needs each one to be released when the frame returns.
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
