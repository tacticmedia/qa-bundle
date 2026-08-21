<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * Screenshot review notes, kept in a JSON file so a review survives page changes
 * and browser restarts. The file sits under the review directory, outside the
 * screenshot tree, which the journeys empty at the start of every run.
 */
final readonly class FeedbackStore
{
    private const FILENAME = 'screenshot-feedback.json';

    private string $path;

    public function __construct(string $reviewDirectory)
    {
        $this->path = rtrim($reviewDirectory, '/').'/'.self::FILENAME;
    }

    /**
     * @return list<FeedbackItem>
     */
    public function all(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);

        if (!\is_array($decoded)) {
            return [];
        }

        $items = [];

        foreach ($decoded as $row) {
            if (\is_array($row) && ($item = FeedbackItem::fromArray($row)) instanceof FeedbackItem) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function get(string $id): ?FeedbackItem
    {
        foreach ($this->all() as $feedbackItem) {
            if ($feedbackItem->id === $id) {
                return $feedbackItem;
            }
        }

        return null;
    }

    public function add(FeedbackItem $item): void
    {
        $items = $this->all();
        $items[] = $item;

        $this->write($items);
    }

    public function updateNote(string $id, string $note): void
    {
        $this->write(array_map(
            static fn (FeedbackItem $item): FeedbackItem => $item->id === $id ? $item->withNote($note) : $item,
            $this->all(),
        ));
    }

    public function remove(string $id): void
    {
        $this->write(array_values(array_filter(
            $this->all(),
            static fn (FeedbackItem $item): bool => $item->id !== $id,
        )));
    }

    public function clear(): void
    {
        $this->write([]);
    }

    /**
     * @return list<FeedbackItem>
     */
    public function forScreenshot(string $mode, string $viewport, string $name): array
    {
        $key = \sprintf('%s/%s/%s', $mode, $viewport, $name);

        return array_values(array_filter(
            $this->all(),
            static fn (FeedbackItem $item): bool => $item->screenshotKey() === $key,
        ));
    }

    /**
     * @return array<string, int> keyed by "<mode>/<viewport>"
     */
    public function countsByGroup(): array
    {
        $counts = [];

        foreach ($this->all() as $feedbackItem) {
            $key = $feedbackItem->mode.'/'.$feedbackItem->viewport;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<string, int> keyed by screen name
     */
    public function countsByScreen(string $mode, string $viewport): array
    {
        $counts = [];

        foreach ($this->all() as $feedbackItem) {
            if ($feedbackItem->mode === $mode && $feedbackItem->viewport === $viewport) {
                $counts[$feedbackItem->name] = ($counts[$feedbackItem->name] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param list<FeedbackItem> $items
     */
    private function write(array $items): void
    {
        $directory = \dirname($this->path);

        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        file_put_contents(
            $this->path,
            json_encode(
                array_map(static fn (FeedbackItem $item): array => $item->toArray(), $items),
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
            ),
            \LOCK_EX,
        );
    }
}
