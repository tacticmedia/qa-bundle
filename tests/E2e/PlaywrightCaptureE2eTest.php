<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\E2e;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Playwright\Browser\BrowserContextInterface;
use Playwright\Browser\BrowserInterface;
use Playwright\Page\PageInterface;
use Playwright\PlaywrightClient;
use Playwright\PlaywrightFactory;
use Symfony\Component\Panther\ProcessManager\WebServerManager;
use TacticMedia\QaBundle\Test\PlaywrightJourneyScreenshots;

/**
 * PlaywrightJourneyScreenshots in Chromium through playwright-php. Run
 * `vendor/bin/playwright-install chromium` first. playwright-php starts no server
 * for the application, so the test starts the PHP built-in server with Panther's
 * WebServerManager, which is already a dev dependency.
 */
#[Group('e2e')]
#[Group('playwright')]
final class PlaywrightCaptureE2eTest extends TestCase
{
    use CapturedTree;
    use PlaywrightJourneyScreenshots;
    private const BASE_URL = 'http://127.0.0.1:9081';
    private const PAGE_STATE = '[innerWidth, innerHeight, matchMedia("(prefers-color-scheme: dark)").matches]';

    private static ?WebServerManager $server = null;

    private static ?PlaywrightClient $playwright = null;

    private static ?BrowserInterface $browser = null;

    private ?BrowserContextInterface $context = null;

    public static function setUpBeforeClass(): void
    {
        self::useATemporaryRootWhenNoneIsSet();

        self::$server = new WebServerManager(\dirname(__DIR__).'/Fixtures/app/public', '127.0.0.1', 9081);
        self::$server->start();

        self::$playwright = PlaywrightFactory::create();
        self::$browser = self::$playwright->chromium()->launch();
    }

    public static function tearDownAfterClass(): void
    {
        self::$browser?->close();
        self::$playwright?->close();
        self::$server?->quit();
        self::$browser = self::$playwright = self::$server = null;

        self::removeTheTemporaryRoot();
    }

    protected function tearDown(): void
    {
        $this->context?->close();
        $this->context = null;
    }

    #[TestDox('Each label is captured once, at every viewport and colour scheme, with a PNG of the size in its sidecar')]
    public function testTheJourneyWritesTheTree(): void
    {
        $page = $this->page();

        $page->goto(self::BASE_URL.'/_dev/screenshots');
        $this->captureFullPageScreenshot($page, 'Review empty');

        $page->goto(self::BASE_URL.'/tall.html');
        $this->captureFullPageScreenshot($page, 'Tall page');
        $this->captureFullPageScreenshot($page, 'Tall page');

        $this->assertTheJourneyWroteTheTree('PlaywrightCaptureE2eTest-testTheJourneyWritesTheTree');
    }

    #[TestDox('After a capture the journey has its viewport and the browser colour scheme again')]
    public function testTheCaptureRestoresThePage(): void
    {
        $page = $this->page();

        $page->goto(self::BASE_URL.'/tall.html');
        $before = $page->evaluate(self::PAGE_STATE);

        $this->captureFullPageScreenshot($page, 'Tall page');

        self::assertSame([1280, 720, false], $before);
        self::assertSame($before, $page->evaluate(self::PAGE_STATE));
    }

    private function page(): PageInterface
    {
        self::assertNotNull(self::$browser);

        $this->context = self::$browser->newContext(['viewport' => ['width' => 1280, 'height' => 720]]);

        return $this->context->newPage();
    }
}
