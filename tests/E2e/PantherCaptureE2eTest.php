<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\E2e;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Panther\PantherTestCase;
use TacticMedia\QaBundle\Test\JourneyScreenshots;

/**
 * JourneyScreenshots in Chrome through Panther and the chromedriver CDP endpoint.
 */
#[Group('e2e')]
final class PantherCaptureE2eTest extends PantherTestCase
{
    use CapturedTree;
    use JourneyScreenshots;
    private const PAGE_STATE = 'return [innerWidth, innerHeight, matchMedia("(prefers-color-scheme: dark)").matches]';

    public static function setUpBeforeClass(): void
    {
        self::useATemporaryRootWhenNoneIsSet();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        self::removeTheTemporaryRoot();
    }

    #[TestDox('Each label is captured once, at every viewport and colour scheme, with a PNG of the size in its sidecar')]
    public function testTheJourneyWritesTheTree(): void
    {
        $client = self::createPantherClient();

        $client->request('GET', '/_dev/screenshots');
        $this->captureFullPageScreenshot($client, 'Review empty');

        $client->request('GET', '/tall.html');
        $this->captureFullPageScreenshot($client, 'Tall page');
        $this->captureFullPageScreenshot($client, 'Tall page');

        $this->assertTheJourneyWroteTheTree('PantherCaptureE2eTest-testTheJourneyWritesTheTree');
    }

    #[TestDox('After a capture the journey has its viewport and the browser colour scheme again')]
    public function testTheCaptureRestoresThePage(): void
    {
        $client = self::createPantherClient();

        $client->request('GET', '/tall.html');
        $before = $client->executeScript(self::PAGE_STATE);

        $this->captureFullPageScreenshot($client, 'Tall page');

        self::assertIsArray($before);
        self::assertFalse($before[2], 'The browser starts in light mode, so a scheme that is not reset shows.');
        self::assertSame($before, $client->executeScript(self::PAGE_STATE));
    }
}
