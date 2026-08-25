<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use TacticMedia\QaBundle\Tests\ScreenshotFixtures;

/**
 * The review page inside the fixture host: routing, the sidebar the catalog fills,
 * the annotate page, the raw image, and the note round trip through FeedbackStore.
 *
 * Every write carries the token the page rendered and a same-origin header, which
 * is the stateless CSRF contract the actions are declared under; a POST missing
 * either is answered with 403 rather than reaching the action.
 */
final class ScreenshotReviewControllerTest extends WebTestCase
{
    use ScreenshotFixtures;

    /** BrowserKit sends no Origin of its own, and the token is only accepted with one. */
    private const SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    protected function setUp(): void
    {
        $this->seedFixtureTree();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeFixtureTree();
    }

    #[TestDox('The index redirects to the first discovered group, dark before light')]
    public function testTheIndexRedirectsToTheFirstGroup(): void
    {
        $client = self::createClient();
        $client->request('GET', '/_dev/screenshots');

        self::assertResponseRedirects('/_dev/screenshots/dark/1920x1080');
    }

    #[TestDox('An empty tree renders the empty state instead of redirecting')]
    public function testAnEmptyTreeRendersTheEmptyState(): void
    {
        $this->removeFixtureTree();

        $client = self::createClient();
        $client->request('GET', '/_dev/screenshots');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No screenshots yet');
    }

    #[TestDox('An empty page names the files it could not read, so a foreign producer sees why')]
    public function testTheEmptyStateReportsWhatItIgnored(): void
    {
        $this->removeFixtureTree();

        $directory = $this->fixtureScreenshotRoot().'/light/1920x1080/landscape';
        mkdir($directory, 0o777, true);
        file_put_contents($directory.'/screenshot 1.png', 'png');

        $client = self::createClient();
        $client->request('GET', '/_dev/screenshots');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '1 entry was ignored');
        self::assertSelectorTextContains('body', 'light/1920x1080/landscape/screenshot 1.png');
        self::assertSelectorTextContains('body', 'name does not match the capture grammar');
    }

    #[TestDox('A group page names the files in its own directory that the reader skipped')]
    public function testTheGroupPageReportsWhatItIgnored(): void
    {
        file_put_contents(
            \sprintf('%s/light/1920x1080/landscape/%s', $this->fixtureScreenshotRoot(), 'FixtureJourneyE2eTest-testJourney-009_Stray.json'),
            '{}',
        );

        $client = self::createClient();
        $client->request('GET', '/_dev/screenshots/light/1920x1080');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '1 entry was ignored');
        self::assertSelectorTextContains('body', 'sidecar with no capture beside it');
    }

    #[TestDox('A long ignored list is capped, and the page says how much it is not showing')]
    public function testTheIgnoredListIsCappedOnThePage(): void
    {
        $directory = $this->fixtureScreenshotRoot().'/light/1920x1080/landscape';

        for ($i = 0; $i < 25; ++$i) {
            file_put_contents(\sprintf('%s/wrong %02d.png', $directory, $i), 'png');
        }

        $client = self::createClient();
        $client->request('GET', '/_dev/screenshots/light/1920x1080');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '25 entries were ignored');
        self::assertSelectorTextContains('body', 'and 5 more');
    }

    #[TestDox('An unreadable sidecar is called out on the page where it would have resolved elements')]
    public function testTheAnnotatePageWarnsAboutAnUnreadableSidecar(): void
    {
        file_put_contents(
            \sprintf('%s/light/1920x1080/landscape/%s.json', $this->fixtureScreenshotRoot(), self::SCREEN),
            'not json',
        );

        $client = self::createClient();
        $client->request('GET', '/_dev/screenshots/light/1920x1080/'.self::SCREEN);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'no readable sidecar');
    }

    #[TestDox('The group grid lists every capture, sectioned by test class')]
    public function testTheGroupGridListsTheCaptures(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/_dev/screenshots/light/1920x1080');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'FixtureJourneyE2eTest');
        self::assertCount(2, $crawler->filter('a[id^="screen-"]'));
        self::assertSelectorTextContains('body', 'Product List');
        self::assertSelectorTextContains('body', 'Product Detail');
    }

    #[TestDox('The sidebar lists the discovered groups in mode then viewport order')]
    public function testTheSidebarListsEveryGroup(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/_dev/screenshots/light/1920x1080');

        $labels = $crawler->filter('nav a span.truncate')->each(static fn ($node): string => trim($node->text()));

        self::assertSame(['Dark · 1920×1080', 'Light · 390×844', 'Light · 1920×1080'], $labels);
    }

    #[TestDox('A group with no captures is a 404')]
    public function testAnUnknownGroupIsNotFound(): void
    {
        $client = self::createClient();
        $client->request('GET', '/_dev/screenshots/light/800x600');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[TestDox('The annotate page carries the capture, its neighbours and the note form')]
    public function testTheAnnotatePageRenders(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', $this->annotatePath(self::SCREEN));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Product List');
        self::assertSelectorTextContains('body', 'testJourney');
        self::assertSelectorTextContains('body', 'landscape');
        self::assertCount(1, $crawler->filter('[data-qa-annotate-target="image"]'));
        self::assertCount(1, $crawler->filter('form[action="/_dev/screenshots/feedback"]'));
        self::assertSelectorTextContains('body', 'Notes on this screen (0)');
    }

    #[TestDox('A capture the tree does not hold is a 404')]
    public function testAnUnknownCaptureIsNotFound(): void
    {
        $client = self::createClient();
        $client->request('GET', $this->annotatePath('FixtureJourneyE2eTest-testJourney-404_Gone'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[TestDox('The raw action serves the PNG and answers a conditional request')]
    public function testTheRawActionServesTheImage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/_dev/screenshots/raw/light/1920x1080/'.rawurlencode(self::SCREEN).'.png');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');

        $etag = $client->getResponse()->getEtag();

        self::assertNotNull($etag);

        $client->request('GET', '/_dev/screenshots/raw/light/1920x1080/'.rawurlencode(self::SCREEN).'.png', server: ['HTTP_IF_NONE_MATCH' => $etag]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_MODIFIED);
    }

    #[TestDox('A traversing name never reaches the filesystem')]
    public function testTraversalIsRejected(): void
    {
        $client = self::createClient();
        $client->request('GET', '/_dev/screenshots/raw/light/1920x1080/'.rawurlencode('../../../../composer.json').'.png');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[TestDox('A whole-screen note is stored and drawn back on the annotate page')]
    public function testAWholeScreenNoteRoundTrips(): void
    {
        $client = self::createClient();

        $this->postNote($client, ['note' => 'The cards are misaligned.']);

        self::assertResponseRedirects($this->annotatePath(self::SCREEN));

        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Notes on this screen (1)');
        self::assertSelectorTextContains('body', 'The cards are misaligned.');
        self::assertSelectorTextContains('body', 'Whole screen');
    }

    #[TestDox('An area note keeps its rectangle and renders a marker')]
    public function testAnAreaNoteKeepsItsRectangle(): void
    {
        $client = self::createClient();

        $crawler = $this->postNote($client, [
            'note' => 'The heading overlaps the badge.',
            'x' => '840', 'y' => '460', 'width' => '240', 'height' => '160',
            'image_width' => '1920', 'image_height' => '1080',
        ]);
        $crawler = $client->followRedirect();

        $marker = $crawler->filter('[data-qa-annotate-target="marker"]');

        self::assertCount(1, $marker);
        self::assertSame('840', $marker->attr('data-rect-x'));
        self::assertSame('240', $marker->attr('data-rect-width'));
        self::assertSame('1920', $marker->attr('data-image-width'));
    }

    #[TestDox('A rectangle wider than the image it was drawn on is clamped to it')]
    public function testAnOversizedRectangleIsClamped(): void
    {
        $client = self::createClient();

        $this->postNote($client, [
            'note' => 'Clamp me.',
            'x' => '-40', 'y' => '5000', 'width' => '9000', 'height' => '9000',
            'image_width' => '1920', 'image_height' => '1080',
        ]);
        $crawler = $client->followRedirect();

        $marker = $crawler->filter('[data-qa-annotate-target="marker"]');

        self::assertSame('0', $marker->attr('data-rect-x'));
        self::assertSame('1080', $marker->attr('data-rect-y'));
        self::assertSame('1920', $marker->attr('data-rect-width'));
    }

    #[TestDox('An empty note re-renders the form with 422 and keeps the pending area')]
    public function testAnEmptyNoteIsRejected(): void
    {
        $client = self::createClient();

        $crawler = $this->postNote($client, [
            'note' => '   ',
            'x' => '840', 'y' => '460', 'width' => '240', 'height' => '160',
            'image_width' => '1920', 'image_height' => '1080',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'Write a note before saving.');
        self::assertSame('840', $crawler->filter('[data-qa-annotate-target="x"]')->attr('value'));
    }

    #[TestDox('A note on a capture that is not there is a 404')]
    public function testANoteOnAnUnknownCaptureIsRejected(): void
    {
        $client = self::createClient();
        $this->post($client, '/_dev/screenshots/feedback', [
            'mode' => 'light', 'viewport' => '1920x1080', 'name' => 'Nope-nope-001_Nope', 'note' => 'x',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Neither proof of origin nor a token the page issued. A same-origin request
     * carrying any long-enough value is accepted by design wherever the id is
     * stateless, so corrupting the token alone would not test anything.
     */
    #[TestDox('A write proving neither same origin nor an issued token is rejected, and stores nothing')]
    public function testAnUnprovenWriteIsRejected(): void
    {
        $client = self::createClient();
        $client->request('POST', '/_dev/screenshots/feedback', [
            '_token' => 'not-a-token-this-page-issued',
            'mode' => 'light',
            'viewport' => '1920x1080',
            'name' => self::SCREEN,
            'note' => 'Never written.',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request('GET', $this->annotatePath(self::SCREEN));

        self::assertSelectorTextNotContains('body', 'Never written.');
    }

    #[TestDox('Editing replaces the wording; withdrawing empties the screen')]
    public function testANoteCanBeEditedAndWithdrawn(): void
    {
        $client = self::createClient();

        $this->postNote($client, ['note' => 'First take.']);
        $crawler = $client->followRedirect();

        $id = $this->noteId($crawler->filter('form[action$="/delete"]')->attr('action'));

        $this->post($client, '/_dev/screenshots/feedback/'.$id.'/update', ['note' => 'Second take.']);
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Second take.');
        self::assertSelectorTextNotContains('body', 'First take.');

        $this->post($client, '/_dev/screenshots/feedback/'.$id.'/delete');
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Notes on this screen (0)');
    }

    #[TestDox('The prompt names the test file, the page, the crop and the resolved element')]
    public function testThePromptCarriesTheCodeContext(): void
    {
        $client = self::createClient();

        $this->postNote($client, [
            'note' => 'The link wraps onto a second line.',
            'x' => '840', 'y' => '460', 'width' => '240', 'height' => '160',
            'image_width' => '1920', 'image_height' => '1080',
        ]);

        $client->request('GET', '/_dev/screenshots/prompt.txt');
        $prompt = (string) $client->getResponse()->getContent();

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertStringContainsString('JOURNEY:  FixtureJourneyE2eTest', $prompt);
        self::assertStringContainsString('FILE:     tests/E2e/FixtureJourneyE2eTest.php', $prompt);
        self::assertStringContainsString('SCENARIO: testJourney', $prompt);
        self::assertStringContainsString('Screenshot: var/screenshots/light/1920x1080/landscape/'.self::SCREEN.'.png', $prompt);
        self::assertStringContainsString('Page: '.self::PAGE_URL.' ("'.self::PAGE_TITLE.'")', $prompt);
        self::assertStringContainsString('selector: '.self::FIXTURE_SELECTOR, $prompt);
        self::assertStringContainsString('classes: text-red-600', $prompt);
        self::assertStringContainsString('The link wraps onto a second line.', $prompt);
        self::assertMatchesRegularExpression('#Cropped view \(selection outlined in red\): var/review/crops/[0-9a-f-]{36}\.png#', $prompt);
    }

    #[TestDox('A note whose capture is gone is kept and flagged as stale')]
    public function testAStaleNoteSurvivesTheCaptureItDescribes(): void
    {
        $client = self::createClient();

        $this->postNote($client, ['note' => 'Still worth fixing.']);

        unlink(\sprintf('%s/light/1920x1080/landscape/%s.png', $this->fixtureScreenshotRoot(), self::SCREEN));

        $client->request('GET', '/_dev/screenshots/prompt.txt');
        $prompt = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Still worth fixing.', $prompt);
        self::assertStringContainsString('this file is not in the current run', $prompt);
    }

    #[TestDox('Clear all empties the review')]
    public function testClearingDropsEveryNote(): void
    {
        $client = self::createClient();

        $this->postNote($client, ['note' => 'One.']);
        $this->post($client, '/_dev/screenshots/feedback/clear');

        self::assertResponseRedirects('/_dev/screenshots');

        $client->request('GET', '/_dev/screenshots/prompt');

        self::assertSelectorTextContains('body', 'Nothing to report yet');
    }

    /**
     * @param array<string, string> $fields
     */
    private function postNote(KernelBrowser $client, array $fields): Crawler
    {
        return $this->post($client, '/_dev/screenshots/feedback', [
            'mode' => 'light',
            'viewport' => '1920x1080',
            'name' => self::SCREEN,
            ...$fields,
        ]);
    }

    /**
     * @param array<string, string> $fields
     */
    private function post(KernelBrowser $client, string $path, array $fields = []): Crawler
    {
        return $client->request('POST', $path, ['_token' => $this->token($client), ...$fields], server: self::SAME_ORIGIN);
    }

    /**
     * The value the page itself renders, so a change of token strategy shows up
     * here rather than in a hand-written constant.
     */
    private function token(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', $this->annotatePath(self::SCREEN));

        return (string) $crawler->filter('form[action="/_dev/screenshots/feedback"] input[name="_token"]')->attr('value');
    }

    private function annotatePath(string $name): string
    {
        return '/_dev/screenshots/light/1920x1080/'.rawurlencode($name);
    }

    private function noteId(?string $action): string
    {
        preg_match('#/feedback/([0-9a-f-]{36})/delete$#', (string) $action, $matches);

        self::assertArrayHasKey(1, $matches, 'The withdraw form names the note it removes.');

        return $matches[1];
    }
}
