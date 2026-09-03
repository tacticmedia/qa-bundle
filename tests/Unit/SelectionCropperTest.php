<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use TacticMedia\QaBundle\Review\SelectionCropper;

/**
 * The agent reads the crop, so the geometry under test is the padding, the clamp
 * at the image edge, and the position of the outline at the selection after the
 * crop origin is subtracted.
 */
final class SelectionCropperTest extends TestCase
{
    private const SOURCE_WIDTH = 400;
    private const SOURCE_HEIGHT = 300;

    private string $root;

    private string $reviewDirectory;

    private string $source;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/selection-cropper-'.bin2hex(random_bytes(6));
        $this->reviewDirectory = $this->root.'/var/review';
        $this->source = $this->root.'/page.png';

        mkdir($this->root, 0o777, true);

        $image = imagecreatetruecolor(self::SOURCE_WIDTH, self::SOURCE_HEIGHT);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        imagepng($image, $this->source);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->root);
    }

    #[TestDox('An interior selection receives 80 px of context on every side')]
    public function testTheCropIsPaddedAroundTheSelection(): void
    {
        $path = $this->cropper()->crop($this->source, ['x' => 150, 'y' => 120, 'width' => 60, 'height' => 40], 'note-1');

        self::assertSame('var/review/crops/note-1.png', $path);
        self::assertSame([220, 200], \array_slice((array) getimagesize($this->root.'/'.$path), 0, 2));
    }

    #[TestDox('A review directory outside the project gives an absolute path')]
    public function testAnOutsideReviewDirectoryYieldsAnAbsolutePath(): void
    {
        $path = (new SelectionCropper($this->reviewDirectory, '/somewhere/else'))
            ->crop($this->source, ['x' => 150, 'y' => 120, 'width' => 60, 'height' => 40], 'note-1');

        self::assertSame($this->reviewDirectory.'/crops/note-1.png', $path);
    }

    #[TestDox('The outline is at the selection, offset by the crop origin')]
    public function testTheSelectionIsOutlined(): void
    {
        $path = $this->cropper()->crop($this->source, ['x' => 150, 'y' => 120, 'width' => 60, 'height' => 40], 'note-1');

        self::assertNotNull($path);

        $crop = imagecreatefrompng($this->root.'/'.$path);

        self::assertNotFalse($crop);
        self::assertSame(0xFF0000, imagecolorat($crop, 80, 80));
        self::assertSame(0xFFFFFF, imagecolorat($crop, 110, 100));
    }

    #[TestDox('A selection at the image edge is clamped to the image')]
    public function testTheCropClampsToTheImage(): void
    {
        $path = $this->cropper()->crop($this->source, ['x' => 0, 'y' => 0, 'width' => 50, 'height' => 50], 'note-2');

        self::assertNotNull($path);
        self::assertSame([130, 130], \array_slice((array) getimagesize($this->root.'/'.$path), 0, 2));
    }

    #[TestDox('An unreadable source yields null instead of throwing')]
    public function testAnUnreadableSourceFailsOpen(): void
    {
        $cropper = $this->cropper();
        $rectangle = ['x' => 10, 'y' => 10, 'width' => 20, 'height' => 20];

        file_put_contents($this->root.'/broken.png', 'not a png');

        self::assertNull($cropper->crop($this->root.'/missing.png', $rectangle, 'note-3'));
        self::assertNull($cropper->crop($this->root.'/broken.png', $rectangle, 'note-4'));
    }

    #[TestDox('Clearing empties the crop directory without removing it')]
    public function testClearRemovesTheGeneratedCrops(): void
    {
        $cropper = $this->cropper();
        $cropper->crop($this->source, ['x' => 10, 'y' => 10, 'width' => 20, 'height' => 20], 'note-5');

        $cropper->clear();

        self::assertDirectoryExists($this->reviewDirectory.'/crops');
        self::assertSame([], glob($this->reviewDirectory.'/crops/*.png'));
    }

    private function cropper(): SelectionCropper
    {
        return new SelectionCropper($this->reviewDirectory, $this->root);
    }
}
