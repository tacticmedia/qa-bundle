<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\E2e;

use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\Remote\DriverCommand;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverHasInputDevices;
use Facebook\WebDriver\WebDriverKeys;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use TacticMedia\QaBundle\Tests\ScreenshotFixtures;

/**
 * The review page in a real browser: browse a group, note a whole screen, drag an
 * area and note that, edit, withdraw, read the generated prompt, and move through
 * the run from the keyboard. Each step depends on the annotate Stimulus controller
 * and on the round trip through FeedbackStore, so the test needs a real browser.
 */
#[Group('e2e')]
final class ScreenshotReviewE2eTest extends PantherTestCase
{
    use ScreenshotFixtures;
    private const MODE = 'light';
    private const VIEWPORT = '1920x1080';

    /** Directly above MODE/VIEWPORT in the discovered order, so up and down have a fixed target. */
    private const ABOVE_MODE = 'light';
    private const ABOVE_VIEWPORT = '390x844';

    /** A capture narrower than the stage, which the browser then centres in it. */
    private const NARROW_VIEWPORT = '390x844';

    /** Whitespace beside the screenshot, in displayed pixels. */
    private const STAGE_SLACK_SCRIPT = <<<'MEASURE'
        const stage = document.querySelector('[data-qa-annotate-target="stage"]');
        const image = document.querySelector('[data-qa-annotate-target="image"]');

        return stage.getBoundingClientRect().width - image.getBoundingClientRect().width;
        MEASURE;

    /** The largest difference between an edge of the saved marker and its recorded box, in image pixels. */
    private const MARKER_DRIFT_SCRIPT = <<<'MEASURE'
        const image = document.querySelector('[data-qa-annotate-target="image"]');
        const marker = document.querySelector('[data-qa-annotate-target="marker"]:not([hidden])');
        const bounds = image.getBoundingClientRect();
        const drawn = marker.getBoundingClientRect();
        const scale = bounds.width / Number(marker.dataset.imageWidth);

        return Math.max(
            Math.abs((drawn.left - bounds.left) / scale - Number(marker.dataset.rectX)),
            Math.abs((drawn.top - bounds.top) / scale - Number(marker.dataset.rectY)),
            Math.abs(drawn.width / scale - Number(marker.dataset.rectWidth)),
            Math.abs(drawn.height / scale - Number(marker.dataset.rectHeight)),
        );
        MEASURE;

    protected function setUp(): void
    {
        $this->seedFixtureTree();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeFixtureTree();
    }

    public function testReviewerRecordsWhatIsBroken(): void
    {
        $client = self::createPantherClient();

        $this->openTheFixtureScreenFromItsGroup($client);
        $this->noteTheWholeScreen($client);
        $this->noteADraggedArea($client);
        $this->editTheFirstNote($client);
        $this->readTheGeneratedPrompt($client);
        $this->withdrawEveryNote($client);
        $this->theSavedAreaCoversWhatWasDragged($client);
    }

    public function testReviewerWalksTheRunFromTheKeyboard(): void
    {
        $client = self::createPantherClient();

        $order = $this->readTheOrderOfTheGroup($client);

        $this->arrowKeysWalkTheGroupAndWrap($client, $order);
        $this->upAndDownCarryTheScreenBetweenGroups($client);
        $this->escapeCancelsThePendingSelectionFirst($client);
        $this->theNoteBoxKeepsTheNavigationKeysToItself($client);
        $this->commandEnterSavesTheNote($client);
        $this->escapeReturnsToTheCardOnTheGrid($client);
    }

    /**
     * The group grid lists the capture; its card opens the annotate page.
     */
    private function openTheFixtureScreenFromItsGroup(Client $client): void
    {
        $client->request('GET', \sprintf('/_dev/screenshots/%s/%s', self::MODE, self::VIEWPORT));

        $cardSelector = \sprintf('a[href$="%s"]', rawurlencode(self::SCREEN));

        $this->waitOrFail(
            $client,
            fn (): bool => $this->elementExists($client, $cardSelector),
            'The group grid lists the fixture capture.',
        );

        $this->clickBySelector($client, $cardSelector);

        $this->waitOrFail(
            $client,
            fn (): bool => $this->elementExists($client, '[data-qa-annotate-target="image"]'),
            'The card opens the annotate page.',
        );

        self::assertSame(0, $this->noteCount($client), 'A screen that nobody has reviewed has no notes.');
        self::assertStringContainsString('No feedback on this screen yet.', $this->pageText($client));
    }

    /**
     * A note written without a drag applies to the whole screen.
     */
    private function noteTheWholeScreen(Client $client): void
    {
        $this->fillNote($client, 'The dashboard cards are misaligned.');
        $this->clickBySelector($client, 'form[action$="/feedback"] button[type="submit"]');

        $this->waitOrFail(
            $client,
            fn (): bool => 1 === $this->noteCount($client),
            'The saved note appears under "Notes on this screen".',
        );

        $notes = $this->pageText($client);
        self::assertStringContainsString('The dashboard cards are misaligned.', $notes);
        self::assertStringContainsString('Whole screen', $notes);
        self::assertStringNotContainsString('No feedback on this screen yet.', $notes);
    }

    /**
     * Dragging over the screenshot fills the note form with the selected area in
     * natural image pixels, and the saved note renders a marker over the area.
     */
    private function noteADraggedArea(Client $client): void
    {
        $this->dragAcrossTheImage($client);

        foreach (['x', 'y', 'width', 'height', 'imageWidth', 'imageHeight'] as $field) {
            self::assertMatchesRegularExpression(
                '/^\d+$/',
                $this->fieldValue($client, $field),
                \sprintf('The drag records a whole pixel value for %s.', $field),
            );
        }

        self::assertSame('1920', $this->fieldValue($client, 'imageWidth'), 'Coordinates are scaled to the natural image size.');
        self::assertGreaterThan(0, (int) $this->fieldValue($client, 'width'));
        self::assertMatchesRegularExpression(
            '/\d+ × \d+ px at \d+, \d+/u',
            (string) $client->executeScript('return document.querySelector(\'[data-qa-annotate-target="summary"]\').textContent.trim()'),
            'The form reports the selected area back to the reviewer.',
        );

        $this->fillNote($client, 'This heading overlaps the badge beside it.');
        $this->clickBySelector($client, 'form[action$="/feedback"] button[type="submit"]');

        $this->waitOrFail(
            $client,
            fn (): bool => 2 === $this->noteCount($client),
            'The area note is added to the list.',
        );

        self::assertStringContainsString('This heading overlaps the badge beside it.', $this->pageText($client));
        self::assertSame(
            1,
            $this->countElements($client, '[data-qa-annotate-target="marker"]:not([hidden])'),
            'The area note is rendered over the screenshot.',
        );
    }

    /**
     * Editing replaces the wording and changes nothing else.
     */
    private function editTheFirstNote(Client $client): void
    {
        $this->clickBySelector($client, 'details summary');
        $this->setFieldValue($client, 'form[action*="/update"] textarea', 'Actually the whole header row is misaligned.');
        $this->clickBySelector($client, 'form[action*="/update"] button[type="submit"]');

        $this->waitOrFail(
            $client,
            fn (): bool => str_contains($this->pageText($client), 'Actually the whole header row is misaligned.'),
            'The edited wording replaces the original.',
        );

        self::assertSame(2, $this->noteCount($client), 'Editing does not add or remove a note.');
        self::assertStringNotContainsString('The dashboard cards are misaligned.', $this->pageText($client));
    }

    /**
     * Drags a box across the middle of the screenshot.
     *
     * Panther's mouse wrapper rejects the null coordinates that clickAndHold() and
     * release() pass to keep the current position, so the drag runs on the
     * underlying driver. W3C pointer offsets are measured from the centre.
     */
    private function dragAcrossTheImage(Client $client): void
    {
        // Turbo renders a cached preview before the real response, so the element
        // handle that a drag holds can be replaced during the drag. The wait for the
        // final document and one retry cover both parts of that swap.
        $this->waitOrFail(
            $client,
            fn (): bool => (bool) $this->quietly(fn (): mixed => $client->executeScript(
                'const image = document.querySelector(\'[data-qa-annotate-target="image"]\');'
                .'return !document.documentElement.hasAttribute("data-turbo-preview")'
                .' && !!image && image.complete && 0 < image.naturalWidth;',
            )),
            'The screenshot is laid out, so a drag over it covers known pixels.',
        );

        $this->scrollIntoView($client, '[data-qa-annotate-target="image"]');

        try {
            $this->drag($client);
        } catch (StaleElementReferenceException) {
            $this->drag($client);
        }

        $this->waitOrFail(
            $client,
            fn (): bool => '' !== $this->fieldValue($client, 'width'),
            'The drag writes the selected area into the note form.',
        );
    }

    private function drag(Client $client): void
    {
        $driver = $this->inputDevices($client);
        $image = $driver->findElement(WebDriverBy::cssSelector('[data-qa-annotate-target="image"]'));

        (new WebDriverActions($driver))
            ->moveToElement($image, -60, -40)
            ->clickAndHold()
            ->moveToElement($image, 60, 40)
            ->release()
            ->perform();
    }

    /**
     * The prompt contains enough for an agent to find the code that the note refers to.
     */
    private function readTheGeneratedPrompt(Client $client): void
    {
        $this->clickBySelector($client, 'a[href$="/_dev/screenshots/prompt"]');

        $this->waitOrFail(
            $client,
            fn (): bool => str_contains($client->getCurrentURL(), '/screenshots/prompt'),
            'The prompt page opens.',
        );

        $prompt = (string) $client->executeScript('return document.querySelector("pre").textContent');

        self::assertStringContainsString('FixtureJourneyE2eTest', $prompt, 'The prompt names the test case.');
        self::assertStringContainsString('tests/E2e/FixtureJourneyE2eTest.php', $prompt, 'The prompt names the test file.');
        self::assertStringContainsString('Actually the whole header row is misaligned.', $prompt);
        self::assertStringContainsString('This heading overlaps the badge beside it.', $prompt);
        self::assertStringContainsString('Area: whole screen', $prompt);
        self::assertMatchesRegularExpression('/Area: x=\d+, y=\d+, width=\d+, height=\d+/', $prompt);
        self::assertStringContainsString(
            \sprintf('var/screenshots/%s/%s/landscape/%s.png', self::MODE, self::VIEWPORT, self::SCREEN),
            $prompt,
            'The prompt names the file that the reviewer opened.',
        );
        self::assertStringContainsString(
            \sprintf('Page: %s ("%s")', self::PAGE_URL, self::PAGE_TITLE),
            $prompt,
            'The prompt names the page the capture was taken on.',
        );
        self::assertStringContainsString('selector: '.self::FIXTURE_SELECTOR, $prompt);

        preg_match('#Cropped view \(selection outlined in red\): (var/review/crops/[0-9a-f-]{36}\.png)#', $prompt, $matches);

        self::assertArrayHasKey(1, $matches, 'The prompt names a crop of the selection.');
        self::assertFileExists(\dirname(__DIR__).'/Fixtures/app/'.$matches[1]);
    }

    /**
     * Withdrawing empties the screen again.
     */
    private function withdrawEveryNote(Client $client): void
    {
        $client->request('GET', $this->annotatePath(self::VIEWPORT, self::SCREEN));

        $this->waitOrFail(
            $client,
            fn (): bool => 2 === $this->noteCount($client),
            'Both notes are present after the navigation.',
        );

        foreach ([1, 0] as $remaining) {
            $this->clickBySelector($client, 'form[action*="/delete"] button[type="submit"]');
            $this->waitOrFail(
                $client,
                fn (): bool => $remaining === $this->noteCount($client),
                \sprintf('Withdrawing leaves %d note(s).', $remaining),
            );
        }

        self::assertStringContainsString(
            'No feedback on this screen yet.',
            $this->pageText($client),
            'The screen is in its empty state again.',
        );
        self::assertSame(
            0,
            $this->countElements($client, '[data-qa-annotate-target="marker"]:not([hidden])'),
            'The withdrawn area is no longer rendered over the screenshot.',
        );
    }

    /**
     * A screenshot narrower than the stage is centred in it, and a wider one is
     * scaled down, so an overlay measured against the stage is not at the pixels it
     * describes. The saved marker must cover exactly the dragged area at each width
     * the page renders the screenshot at.
     */
    private function theSavedAreaCoversWhatWasDragged(Client $client): void
    {
        $client->request('GET', $this->annotatePath(self::NARROW_VIEWPORT, self::SCREEN));

        $this->waitOrFail(
            $client,
            fn (): bool => $this->elementExists($client, '[data-qa-annotate-target="image"]'),
            'The narrow capture opens.',
        );

        $this->dragAcrossTheImage($client);
        $this->fillNote($client, 'The marker belongs on the image, not on the stage.');
        $this->clickBySelector($client, 'form[action$="/feedback"] button[type="submit"]');

        $this->waitOrFail(
            $client,
            fn (): bool => 1 === $this->countElements($client, '[data-qa-annotate-target="marker"]:not([hidden])'),
            'The saved area is rendered over the screenshot.',
        );

        self::assertGreaterThan(
            0,
            (float) $client->executeScript(self::STAGE_SLACK_SCRIPT),
            'The stage is wider than this capture, which is the condition that the marker geometry must handle.',
        );
        self::assertLessThanOrEqual(
            1.0,
            (float) $client->executeScript(self::MARKER_DRIFT_SCRIPT),
            'The marker is at the image pixels it records.',
        );
    }

    /**
     * The card anchors of the grid give the order of the group, read from the page.
     *
     * @return list<string> annotate paths, in the order the grid lists them
     */
    private function readTheOrderOfTheGroup(Client $client): array
    {
        $client->request('GET', \sprintf('/_dev/screenshots/%s/%s', self::MODE, self::VIEWPORT));

        $this->waitOrFail(
            $client,
            fn (): bool => $this->elementExists($client, \sprintf('a[href$="%s"]', rawurlencode(self::SCREEN))),
            'The group grid lists the fixture captures.',
        );

        $hrefs = $client->executeScript(
            'return [...document.querySelectorAll("a[id^=\'screen-\']")].map(card => card.getAttribute("href"))',
        );

        self::assertIsArray($hrefs, 'Every card has the anchor that Escape returns to.');
        self::assertGreaterThan(1, \count($hrefs), 'The group contains more than one capture to step between.');

        return array_values(array_map(strval(...), $hrefs));
    }

    /**
     * Left and right move through the group and wrap at both ends, so the header
     * buttons that they mirror are never disabled.
     *
     * @param list<string> $order
     */
    private function arrowKeysWalkTheGroupAndWrap(Client $client, array $order): void
    {
        $first = $order[0];
        $last = $order[\count($order) - 1];

        $this->clickBySelector($client, \sprintf('a[href="%s"]', $first));
        $this->waitForPath($client, $first, 'The first card opens its annotate page.');

        $this->pressKey($client, WebDriverKeys::ARROW_RIGHT);
        $this->waitForPath($client, $order[1], 'Right steps to the next capture in the group.');

        $this->pressKey($client, WebDriverKeys::ARROW_LEFT);
        $this->waitForPath($client, $first, 'Left steps back.');

        $this->pressKey($client, WebDriverKeys::ARROW_LEFT);
        $this->waitForPath($client, $last, 'Left wraps from the first capture to the last.');

        $this->pressKey($client, WebDriverKeys::ARROW_RIGHT);
        $this->waitForPath($client, $first, 'Right wraps from the last capture back to the first.');
    }

    /**
     * Up and down keep the screen and change the group, in sidebar order.
     */
    private function upAndDownCarryTheScreenBetweenGroups(Client $client): void
    {
        $here = $this->annotatePath(self::VIEWPORT, self::SCREEN);
        $above = \sprintf('/_dev/screenshots/%s/%s/%s', self::ABOVE_MODE, self::ABOVE_VIEWPORT, rawurlencode(self::SCREEN));

        $client->request('GET', $here);
        $this->waitForPath($client, $here, 'The fixture screen is open.');

        $this->pressKey($client, WebDriverKeys::ARROW_UP);
        $this->waitForPath($client, $above, 'Up opens the same screen in the group above.');

        $this->pressKey($client, WebDriverKeys::ARROW_DOWN);
        $this->waitForPath($client, $here, 'Down opens the same screen in the group below.');
    }

    /**
     * Escape cancels a pending selection first, although the drag ends with the
     * focus in the note box.
     */
    private function escapeCancelsThePendingSelectionFirst(Client $client): void
    {
        $url = $client->getCurrentURL();

        $this->dragAcrossTheImage($client);
        $this->pressKeyWhereFocusIs($client, WebDriverKeys::ESCAPE);

        self::assertSame('', $this->fieldValue($client, 'width'), 'Escape cancels the selection.');
        self::assertSame($url, $client->getCurrentURL(), 'Cancelling a selection does not leave the page.');
    }

    /**
     * Arrow keys move the caret while a note is being written, and Escape removes
     * the focus from the field before it leaves the page.
     */
    private function theNoteBoxKeepsTheNavigationKeysToItself(Client $client): void
    {
        $url = $client->getCurrentURL();

        $this->scrollIntoView($client, '[data-qa-annotate-target="note"]');
        $this->focusTheNoteBox($client);
        $this->pressKeyWhereFocusIs($client, 'ab');
        $this->pressKeyWhereFocusIs($client, WebDriverKeys::ARROW_LEFT);
        $this->pressKeyWhereFocusIs($client, 'X');

        self::assertSame('aXb', $this->fieldValue($client, 'note'), 'An arrow key inside the note box moves the caret.');
        self::assertSame($url, $client->getCurrentURL(), 'Writing a note never navigates.');

        $this->pressKeyWhereFocusIs($client, WebDriverKeys::ESCAPE);

        self::assertSame($url, $client->getCurrentURL(), 'Escape removes the focus from the note box before it leaves the page.');
        self::assertSame('BODY', $this->activeElementTag($client), 'Escape removes the focus from the note box.');
        self::assertSame('aXb', $this->fieldValue($client, 'note'), 'Escape keeps the typed text.');
    }

    /**
     * A save from the keyboard uses requestSubmit(), which fires the submit event
     * that the double-submit listener of a host needs. A bypass would cause the
     * host to reject the POST with no message, which appears here as a note that
     * is not saved.
     */
    private function commandEnterSavesTheNote(Client $client): void
    {
        $this->setFieldValue($client, '[data-qa-annotate-target="note"]', 'Saved without touching the mouse.');
        $this->focusTheNoteBox($client);

        $driver = $this->inputDevices($client);

        (new WebDriverActions($driver))
            ->keyDown(null, WebDriverKeys::META)
            ->sendKeys(null, WebDriverKeys::ENTER)
            ->keyUp(null, WebDriverKeys::META)
            ->perform();

        $this->waitOrFail(
            $client,
            fn (): bool => 1 === $this->noteCount($client),
            'Cmd+Enter saves the note being written.',
        );

        self::assertStringContainsString('Saved without touching the mouse.', $this->pageText($client));
    }

    /**
     * When nothing remains to cancel, Escape returns to the grid at the card that
     * was reviewed and highlights it.
     */
    private function escapeReturnsToTheCardOnTheGrid(Client $client): void
    {
        $anchor = 'screen-'.str_replace(' ', '-', self::SCREEN);

        $this->pressKey($client, WebDriverKeys::ESCAPE);

        $this->waitForPath(
            $client,
            \sprintf('/_dev/screenshots/%s/%s#%s', self::MODE, self::VIEWPORT, $anchor),
            'Escape returns to the grid at the card that was reviewed.',
        );

        $this->waitOrFail(
            $client,
            fn (): bool => $this->elementExists($client, \sprintf('[id="%s"][data-anchored]', $anchor)),
            'The card that Escape returned to is highlighted.',
        );
    }

    private function annotatePath(string $viewport, string $name): string
    {
        return \sprintf('/_dev/screenshots/%s/%s/%s', self::MODE, $viewport, rawurlencode($name));
    }

    private function waitForPath(Client $client, string $path, string $expectation): void
    {
        $this->waitOrFail(
            $client,
            fn (): bool => str_ends_with($client->getCurrentURL(), $path),
            $expectation,
        );
    }

    /**
     * With no focused element the keydown goes to the body and reaches the window
     * bindings, which is the state between notes.
     */
    private function pressKey(Client $client, string $keys): void
    {
        $client->executeScript('document.activeElement?.blur()');

        $this->pressKeyWhereFocusIs($client, $keys);
    }

    /**
     * A W3C key action rather than sendKeys: sendKeys resolves the active element
     * and types into that, and the handle goes stale across a Turbo swap. A key
     * source carries no element, so it always reaches the document and bubbles to
     * the window bindings. WebDriverActions::keyDown()/keyUp() accept modifier keys
     * only, so the command is issued directly.
     */
    private function pressKeyWhereFocusIs(Client $client, string $keys): void
    {
        $sequence = [];

        foreach (preg_split('//u', $keys, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $key) {
            $sequence[] = ['type' => 'keyDown', 'value' => $key];
            $sequence[] = ['type' => 'keyUp', 'value' => $key];
        }

        $this->driver($client)->execute(DriverCommand::ACTIONS, [
            'actions' => [['type' => 'key', 'id' => 'keyboard', 'actions' => $sequence]],
        ]);
    }

    private function focusTheNoteBox(Client $client): void
    {
        $driver = $this->inputDevices($client);

        (new WebDriverActions($driver))
            ->click($driver->findElement(WebDriverBy::cssSelector('[data-qa-annotate-target="note"]')))
            ->perform();
    }

    private function inputDevices(Client $client): WebDriver&WebDriverHasInputDevices
    {
        return $this->driver($client);
    }

    private function driver(Client $client): RemoteWebDriver
    {
        $driver = $client->getWebDriver();

        self::assertInstanceOf(RemoteWebDriver::class, $driver);

        return $driver;
    }

    /**
     * @phpstan-impure focus moves as the reviewer types
     */
    private function activeElementTag(Client $client): string
    {
        return (string) $this->quietly(fn (): mixed => $client->executeScript('return document.activeElement.tagName'));
    }

    private function fillNote(Client $client, string $note): void
    {
        $this->setFieldValue($client, '[data-qa-annotate-target="note"]', $note);
    }

    /**
     * @phpstan-impure the DOM changes as Turbo swaps the page
     */
    private function noteCount(Client $client): ?int
    {
        $heading = $this->quietly(fn (): mixed => $client->executeScript(
            'return [...document.querySelectorAll("h2")].map(h => h.textContent).find(t => t.includes("Notes on this screen")) ?? ""',
        ));

        if (!\is_string($heading) || 1 !== preg_match('/\((\d+)\)/', $heading, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @phpstan-impure the field is written by the annotate controller
     */
    private function fieldValue(Client $client, string $target): string
    {
        return (string) $this->quietly(fn (): mixed => $client->executeScript(\sprintf(
            'return document.querySelector(\'[data-qa-annotate-target="%s"]\')?.value ?? ""',
            $target,
        )));
    }

    /**
     * @phpstan-impure the DOM changes as Turbo swaps the page
     */
    private function pageText(Client $client): string
    {
        return (string) $this->quietly(fn (): mixed => $client->executeScript('return document.body.innerText'));
    }

    /**
     * @phpstan-impure the DOM changes as Turbo swaps the page
     */
    private function elementExists(Client $client, string $selector): bool
    {
        return (bool) $this->quietly(fn (): mixed => $client->executeScript(\sprintf(
            'return !!document.querySelector(%s)',
            json_encode($selector, \JSON_THROW_ON_ERROR),
        )));
    }

    /**
     * @phpstan-impure the DOM changes as Turbo swaps the page
     */
    private function countElements(Client $client, string $selector): ?int
    {
        $count = $this->quietly(fn (): mixed => $client->executeScript(\sprintf(
            'return document.querySelectorAll(%s).length',
            json_encode($selector, \JSON_THROW_ON_ERROR),
        )));

        return null === $count ? null : (int) $count;
    }

    private function clickBySelector(Client $client, string $selector): void
    {
        $clicked = (bool) $client->executeScript(\sprintf(
            'const el = document.querySelector(%s); if (!el) { return false; } el.click(); return true;',
            json_encode($selector, \JSON_THROW_ON_ERROR),
        ));

        self::assertTrue($clicked, \sprintf('Expected a clickable element matching "%s".', $selector));
    }

    private function setFieldValue(Client $client, string $selector, string $value): void
    {
        $client->executeScript(\sprintf(
            'const field = document.querySelector(%s); field.value = %s;'
            .'field.dispatchEvent(new Event("input", {bubbles: true}));'
            .'field.dispatchEvent(new Event("change", {bubbles: true}));',
            json_encode($selector, \JSON_THROW_ON_ERROR),
            json_encode($value, \JSON_THROW_ON_ERROR),
        ));
    }

    private function scrollIntoView(Client $client, string $selector): void
    {
        $client->executeScript(\sprintf(
            'document.querySelector(%s)?.scrollIntoView({block: "center"})',
            json_encode($selector, \JSON_THROW_ON_ERROR),
        ));
    }

    private function waitOrFail(Client $client, callable $condition, string $expectation, int $seconds = 10): void
    {
        try {
            $client->wait($seconds)->until($condition);
        } catch (\Exception $exception) {
            self::fail(\sprintf(
                "%s (still on %s: %s)\nPage: %s",
                $expectation,
                $client->getCurrentURL(),
                $exception->getMessage(),
                substr($this->pageText($client), 0, 800),
            ));
        }
    }

    private function quietly(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable) {
            return null;
        }
    }
}
