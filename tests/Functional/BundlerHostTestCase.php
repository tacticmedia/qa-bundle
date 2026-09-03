<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bridge\Twig\Extension\ImportMapExtension;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use TacticMedia\QaBundle\Tests\ScreenshotFixtures;
use Twig\Environment;

/**
 * The review page in a host that builds its JavaScript with a bundler instead of AssetMapper,
 * and therefore overrides the layout as docs/frontend.md documents. The test covers that
 * contract: the `@!` parent reference, the block name, and that the bundle renders with no
 * importmap() function in the environment. It does not cover the tag that the host's bundle
 * emits.
 */
abstract class BundlerHostTestCase extends WebTestCase
{
    use ScreenshotFixtures;

    /** The only JavaScript URL either host's entrypoints.json names. */
    private const BUILT_ENTRY = '/build/app.js';

    protected function setUp(): void
    {
        $this->seedFixtureTree();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeFixtureTree();
    }

    #[TestDox('The host has no importmap extension, so the default layout of the bundle cannot render')]
    public function testTheHostHasNoImportMapExtension(): void
    {
        self::createClient();

        $twig = self::getContainer()->get('twig');

        self::assertInstanceOf(Environment::class, $twig);
        self::assertFalse($twig->hasExtension(ImportMapExtension::class));
    }

    #[TestDox('The documented override renders the group page and the host built entry')]
    public function testTheDocumentedOverrideRendersTheGroupPage(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/_dev/screenshots/light/1920x1080');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'FixtureJourneyE2eTest');
        self::assertCount(2, $crawler->filter('a[id^="screen-"]'));
        self::assertStringContainsString(self::BUILT_ENTRY, (string) $client->getResponse()->getContent());
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
