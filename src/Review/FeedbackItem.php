<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

use Symfony\Component\Uid\Uuid;

/**
 * One note left on a screenshot, optionally bound to a rectangle. The rectangle
 * and the image size are in natural image pixels, so a note keeps its meaning
 * whatever width the review page renders the screenshot at.
 */
final class FeedbackItem
{
    /**
     * @param array{x: int, y: int, width: int, height: int}|null $rectangle
     * @param array{width: int, height: int}|null                 $imageSize
     */
    public function __construct(
        public readonly string $id,
        public readonly string $mode,
        public readonly string $viewport,
        public readonly string $name,
        public readonly ?array $rectangle,
        public readonly ?array $imageSize,
        public readonly string $note,
        public readonly string $createdAt,
    ) {
    }

    public function isWholeScreen(): bool
    {
        return null === $this->rectangle;
    }

    public function screenshotKey(): string
    {
        return \sprintf('%s/%s/%s', $this->mode, $this->viewport, $this->name);
    }

    /**
     * @param array{x: int, y: int, width: int, height: int}|null $rectangle
     * @param array{width: int, height: int}|null                 $imageSize
     */
    public static function create(
        string $mode,
        string $viewport,
        string $name,
        ?array $rectangle,
        ?array $imageSize,
        string $note,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self(
            Uuid::v4()->toRfc4122(),
            $mode,
            $viewport,
            $name,
            $rectangle,
            $rectangle ? $imageSize : null,
            $note,
            $createdAt->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        foreach (['id', 'mode', 'viewport', 'name', 'note', 'createdAt'] as $key) {
            if (!\is_string($data[$key] ?? null)) {
                return null;
            }
        }

        return new self(
            $data['id'],
            $data['mode'],
            $data['viewport'],
            $data['name'],
            self::rectangleShape($data['rectangle'] ?? null),
            self::sizeShape($data['imageSize'] ?? null),
            $data['note'],
            $data['createdAt'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'mode' => $this->mode,
            'viewport' => $this->viewport,
            'name' => $this->name,
            'rectangle' => $this->rectangle,
            'imageSize' => $this->imageSize,
            'note' => $this->note,
            'createdAt' => $this->createdAt,
        ];
    }

    public function withNote(string $note): self
    {
        return new self(
            $this->id,
            $this->mode,
            $this->viewport,
            $this->name,
            $this->rectangle,
            $this->imageSize,
            $note,
            $this->createdAt,
        );
    }

    /**
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    private static function rectangleShape(mixed $value): ?array
    {
        if (!\is_array($value) || !self::numeric($value, ['x', 'y', 'width', 'height'])) {
            return null;
        }

        return [
            'x' => (int) $value['x'],
            'y' => (int) $value['y'],
            'width' => (int) $value['width'],
            'height' => (int) $value['height'],
        ];
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private static function sizeShape(mixed $value): ?array
    {
        if (!\is_array($value) || !self::numeric($value, ['width', 'height'])) {
            return null;
        }

        return ['width' => (int) $value['width'], 'height' => (int) $value['height']];
    }

    /**
     * @param array<array-key, mixed> $value
     * @param list<string>            $keys
     */
    private static function numeric(array $value, array $keys): bool
    {
        return array_all($keys, fn (string $key): bool => is_numeric($value[$key] ?? null));
    }
}
