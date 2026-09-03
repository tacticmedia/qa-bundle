<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TacticMedia\QaBundle\Review\FeedbackItem;
use TacticMedia\QaBundle\Review\FeedbackStore;

/**
 * The store is the only state that the review keeps between requests, so each
 * assertion here reloads through a new instance to test the file round trip.
 */
final class FeedbackStoreTest extends TestCase
{
    private string $directory;

    private string $path;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/screenshot-review-'.bin2hex(random_bytes(6));
        $this->path = $this->directory.'/screenshot-feedback.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testTheStoreCreatesItsDirectoryOnFirstWrite(): void
    {
        self::assertDirectoryDoesNotExist($this->directory);

        $this->store()->add($this->item('light', '1920x1080', 'AJourneyE2eTest-testA-001_One', null, 'a'));

        self::assertFileExists($this->path);
    }

    public function testAnItemSurvivesTheFileRoundTrip(): void
    {
        $this->store()->add($this->item('light', '1920x1080', 'AJourneyE2eTest-testA-001_One', [
            'x' => 10, 'y' => 2400, 'width' => 300, 'height' => 120,
        ], 'The heading overlaps the badge.'));

        $items = $this->store()->all();

        self::assertCount(1, $items);
        self::assertSame('The heading overlaps the badge.', $items[0]->note);
        self::assertSame(['x' => 10, 'y' => 2400, 'width' => 300, 'height' => 120], $items[0]->rectangle);
        self::assertSame(['width' => 1920, 'height' => 4000], $items[0]->imageSize);
        self::assertFalse($items[0]->isWholeScreen());
    }

    public function testAWholeScreenNoteDropsTheImageSize(): void
    {
        $this->store()->add($this->item('dark', '390x844', 'AJourneyE2eTest-testA-001_One', null, 'Too dark.'));

        $items = $this->store()->all();

        self::assertTrue($items[0]->isWholeScreen());
        self::assertNull($items[0]->imageSize);
    }

    public function testEditingChangesOnlyTheNote(): void
    {
        $store = $this->store();
        $store->add($this->item('light', '1920x1080', 'AJourneyE2eTest-testA-001_One', null, 'First take.'));

        $id = $store->all()[0]->id;

        $store->updateNote($id, 'Second take.');

        $item = $this->store()->get($id);

        self::assertNotNull($item);
        self::assertSame('Second take.', $item->note);
        self::assertSame('AJourneyE2eTest-testA-001_One', $item->name);
    }

    public function testWithdrawingRemovesOnlyThatItem(): void
    {
        $store = $this->store();
        $store->add($this->item('light', '1920x1080', 'AJourneyE2eTest-testA-001_One', null, 'Keep.'));
        $store->add($this->item('light', '1920x1080', 'AJourneyE2eTest-testA-002_Two', null, 'Drop.'));

        $store->remove($store->all()[1]->id);

        self::assertSame(['Keep.'], array_map(static fn (FeedbackItem $item): string => $item->note, $this->store()->all()));
    }

    public function testCountsAreGroupedForTheSidebarAndTheGrid(): void
    {
        $store = $this->store();
        $store->add($this->item('light', '1920x1080', 'AJourneyE2eTest-testA-001_One', null, 'a'));
        $store->add($this->item('light', '1920x1080', 'AJourneyE2eTest-testA-001_One', null, 'b'));
        $store->add($this->item('dark', '390x844', 'AJourneyE2eTest-testA-002_Two', null, 'c'));

        self::assertSame(['light/1920x1080' => 2, 'dark/390x844' => 1], $store->countsByGroup());
        self::assertSame(['AJourneyE2eTest-testA-001_One' => 2], $store->countsByScreen('light', '1920x1080'));
        self::assertCount(2, $store->forScreenshot('light', '1920x1080', 'AJourneyE2eTest-testA-001_One'));
    }

    public function testClearEmptiesTheStore(): void
    {
        $store = $this->store();
        $store->add($this->item('light', '1920x1080', 'AJourneyE2eTest-testA-001_One', null, 'a'));

        $store->clear();

        self::assertSame([], $this->store()->all());
    }

    public function testAMissingOrCorruptFileReadsAsEmpty(): void
    {
        self::assertSame([], $this->store()->all());

        $this->seed('{not json');

        self::assertSame([], $this->store()->all());
    }

    public function testRowsMissingRequiredFieldsAreSkipped(): void
    {
        $this->seed((string) json_encode([
            ['id' => 'kept', 'mode' => 'light', 'viewport' => '1920x1080', 'name' => 'A-b-001_C', 'note' => 'n', 'createdAt' => 'now'],
            ['id' => 'dropped', 'mode' => 'light'],
        ]));

        $items = $this->store()->all();

        self::assertCount(1, $items);
        self::assertSame('kept', $items[0]->id);
    }

    private function seed(string $json): void
    {
        mkdir($this->directory, 0o777, true);
        file_put_contents($this->path, $json);
    }

    private function store(): FeedbackStore
    {
        return new FeedbackStore($this->directory);
    }

    /**
     * @param array{x: int, y: int, width: int, height: int}|null $rectangle
     */
    private function item(string $mode, string $viewport, string $name, ?array $rectangle, string $note): FeedbackItem
    {
        return FeedbackItem::create(
            $mode,
            $viewport,
            $name,
            $rectangle,
            ['width' => 1920, 'height' => 4000],
            $note,
            new \DateTimeImmutable('2026-08-06T10:00:00+09:30'),
        );
    }
}
