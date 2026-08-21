<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * The .json sidecar a journey writes beside every screenshot
 * ({@see \TacticMedia\QaBundle\Test\JourneyScreenshots}). Capture time is the
 * only moment the live DOM exists, and deviceScaleFactor is 1 there, so these CSS
 * pixels are the PNG's pixels.
 *
 * testClass and testFile are null when the sidecar did not come from the trait;
 * the basename still names the test class.
 */
final readonly class ScreenMetadata
{
    /**
     * @param list<MetadataElement> $elements
     */
    public function __construct(
        public string $url,
        public string $title,
        public int $pageWidth,
        public int $pageHeight,
        public array $elements,
        public ?string $testClass = null,
        public ?string $testFile = null,
    ) {
    }

    public static function fromJson(string $json): ?self
    {
        $decoded = json_decode($json, true);

        if (!\is_array($decoded)) {
            return null;
        }

        $url = $decoded['url'] ?? null;
        $title = $decoded['title'] ?? null;
        $width = $decoded['pageWidth'] ?? null;
        $height = $decoded['pageHeight'] ?? null;

        if (!\is_string($url) || !\is_string($title) || !is_numeric($width) || !is_numeric($height)) {
            return null;
        }

        $rows = $decoded['elements'] ?? null;
        $elements = [];

        foreach (\is_array($rows) ? $rows : [] as $row) {
            if (\is_array($row) && ($element = MetadataElement::fromArray($row)) instanceof MetadataElement) {
                $elements[] = $element;
            }
        }

        $testClass = $decoded['testClass'] ?? null;
        $testFile = $decoded['testFile'] ?? null;

        return new self(
            $url,
            $title,
            (int) $width,
            (int) $height,
            $elements,
            \is_string($testClass) ? $testClass : null,
            \is_string($testFile) ? $testFile : null,
        );
    }
}
