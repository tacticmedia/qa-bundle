<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use TacticMedia\QaBundle\Review\ScreenMetadata;

/**
 * The sidecar is written by a browser script and read back a run later, so the
 * parser treats every document as untrusted: a wrong shape yields null and a bad
 * element row is dropped, never an exception.
 */
final class ScreenMetadataTest extends TestCase
{
    #[TestDox('A well-formed sidecar becomes the page and its elements')]
    public function testAValidDocumentParses(): void
    {
        $metadata = ScreenMetadata::fromJson((string) json_encode([
            'url' => 'https://localhost/admin/product',
            'title' => 'Products',
            'pageWidth' => 1920,
            'pageHeight' => 4200,
            'elements' => [
                [
                    'selector' => 'body > main > table > tbody > tr > td > a',
                    'tag' => 'a',
                    'x' => 40,
                    'y' => 300,
                    'width' => 120,
                    'height' => 20,
                    'text' => 'Charcoal 10 kg',
                    'classes' => 'font-medium text-gray-900',
                    'attributes' => ['href' => '/admin/product/1', 'role' => 3],
                ],
            ],
        ]));

        self::assertNotNull($metadata);
        self::assertSame('https://localhost/admin/product', $metadata->url);
        self::assertSame('Products', $metadata->title);
        self::assertSame(1920, $metadata->pageWidth);
        self::assertSame(4200, $metadata->pageHeight);
        self::assertCount(1, $metadata->elements);
        self::assertSame('Charcoal 10 kg', $metadata->elements[0]->text);
        self::assertSame(2400, $metadata->elements[0]->area());
        self::assertSame(['href' => '/admin/product/1'], $metadata->elements[0]->attributes);
    }

    #[TestDox('A sidecar from before the trait stamped its origin still parses')]
    public function testTheCaptureOriginIsOptional(): void
    {
        $stamped = ScreenMetadata::fromJson((string) json_encode([
            'url' => '/', 'title' => 'Home', 'pageWidth' => 390, 'pageHeight' => 900, 'elements' => [],
            'testClass' => 'App\Tests\E2e\AJourneyE2eTest', 'testFile' => 'tests/E2e/AJourneyE2eTest.php',
        ]));
        $bare = ScreenMetadata::fromJson((string) json_encode([
            'url' => '/', 'title' => 'Home', 'pageWidth' => 390, 'pageHeight' => 900, 'elements' => [],
        ]));

        self::assertNotNull($stamped);
        self::assertNotNull($bare);
        self::assertSame('App\Tests\E2e\AJourneyE2eTest', $stamped->testClass);
        self::assertSame('tests/E2e/AJourneyE2eTest.php', $stamped->testFile);
        self::assertNull($bare->testClass);
        self::assertNull($bare->testFile);
    }

    #[TestDox('Malformed JSON and a non-object document both yield null')]
    public function testAnUnusableDocumentYieldsNull(): void
    {
        self::assertNull(ScreenMetadata::fromJson('{not json'));
        self::assertNull(ScreenMetadata::fromJson('"a string"'));
        self::assertNull(ScreenMetadata::fromJson('{"url": "/x", "title": "X", "pageWidth": 100}'));
    }

    #[TestDox('An element row missing a field is skipped, the valid ones survive')]
    public function testInvalidElementRowsAreSkipped(): void
    {
        $metadata = ScreenMetadata::fromJson((string) json_encode([
            'url' => '/',
            'title' => 'Home',
            'pageWidth' => 390,
            'pageHeight' => 900,
            'elements' => [
                ['selector' => 'body > h1', 'tag' => 'h1', 'x' => 0, 'y' => 0, 'width' => 300, 'height' => 40],
                ['selector' => 'body > p', 'tag' => 'p', 'x' => 0, 'y' => 60],
                ['tag' => 'span', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10],
                'not an element',
            ],
        ]));

        self::assertNotNull($metadata);
        self::assertCount(1, $metadata->elements);
        self::assertSame('h1', $metadata->elements[0]->tag);
        self::assertNull($metadata->elements[0]->text);
        self::assertSame([], $metadata->elements[0]->attributes);
    }
}
