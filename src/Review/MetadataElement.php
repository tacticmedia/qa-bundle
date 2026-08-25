<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * One element of a captured page: its box in natural image pixels, and the
 * identity that lets a search find it in templates and translations.
 */
final class MetadataElement
{
    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly string $selector,
        public readonly string $tag,
        public readonly int $x,
        public readonly int $y,
        public readonly int $width,
        public readonly int $height,
        public readonly ?string $text,
        public readonly ?string $classes,
        public readonly array $attributes,
    ) {
    }

    public function area(): int
    {
        return $this->width * $this->height;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $selector = $data['selector'] ?? null;
        $tag = $data['tag'] ?? null;

        if (!\is_string($selector) || !\is_string($tag)) {
            return null;
        }

        $box = [];

        foreach (['x', 'y', 'width', 'height'] as $key) {
            $value = $data[$key] ?? null;

            if (!is_numeric($value)) {
                return null;
            }

            $box[$key] = (int) $value;
        }

        $text = $data['text'] ?? null;
        $classes = $data['classes'] ?? null;

        return new self(
            $selector,
            $tag,
            $box['x'],
            $box['y'],
            $box['width'],
            $box['height'],
            \is_string($text) ? $text : null,
            \is_string($classes) ? $classes : null,
            self::attributeShape($data['attributes'] ?? null),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function attributeShape(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $attributes = [];

        foreach ($value as $name => $attribute) {
            if (\is_string($name) && \is_string($attribute)) {
                $attributes[$name] = $attribute;
            }
        }

        return $attributes;
    }
}
