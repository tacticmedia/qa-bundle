<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use TacticMedia\QaBundle\Tests\ScreenshotFixtures;

/**
 * The review page in a host that builds its JavaScript with a bundler, with no override of the
 * layout. The page loads its own module and no file of the host build.
 */
abstract class BundlerHostTestCase extends WebTestCase
{
    use ScreenshotFixtures;

    protected function setUp(): void
    {
        $this->seedFixtureTree();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeFixtureTree();
    }

    #[TestDox('The default layout renders the group page and loads the module of the bundle')]
    public function testTheDefaultLayoutRendersTheGroupPage(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/_dev/screenshots/light/1920x1080');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'FixtureJourneyE2eTest');
        self::assertCount(2, $crawler->filter('a[id^="screen-"]'));
        self::assertCount(1, $crawler->filter('script[type="module"][src="/_dev/screenshots/assets/review.js"]'));
    }

    #[TestDox('The annotate page contains the targets that the qa controllers bind to')]
    public function testTheAnnotatePageCarriesTheStimulusHooks(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/_dev/screenshots/light/1920x1080/'.rawurlencode(self::SCREEN));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-qa-annotate-target="image"]'));
        self::assertCount(1, $crawler->filter('form[action="/_dev/screenshots/feedback"]'));
    }
}
