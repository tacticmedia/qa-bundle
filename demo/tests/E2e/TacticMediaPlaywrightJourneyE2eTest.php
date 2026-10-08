<?php

declare(strict_types=1);

namespace App\Tests\E2e;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Playwright\Testing\PlaywrightTestCase;
use TacticMedia\QaBundle\Test\PlaywrightJourneyScreenshots;

/**
 * The playwright-php version of TacticMediaJourneyE2eTest, with the same stages
 * and labels.
 */
#[Group('e2e')]
final class TacticMediaPlaywrightJourneyE2eTest extends PlaywrightTestCase
{
    use PlaywrightJourneyScreenshots;

    #[TestDox('A visitor reads every page of the company site')]
    public function testVisitorReadsTheCompanySite(): void
    {
        $page = $this->page;

        $page->goto('https://tacticmedia.com.au/');
        $page->waitForSelector('main');
        $this->captureFullPageScreenshot($page, 'Home');

        $page->getByRole('link', ['name' => 'Software Development'])->first()->click();
        $page->waitForSelector('#software-development-heading');
        $this->captureFullPageScreenshot($page, 'Software development');

        $page->getByRole('link', ['name' => 'Qualified Software Escrow'])->first()->click();
        $page->waitForSelector('#qualified-software-escrow-heading');
        $this->captureFullPageScreenshot($page, 'Software escrow');

        $page->getByRole('link', ['name' => 'Vibe Code Cleanup'])->first()->click();
        $page->waitForSelector('#vibe-code-cleanup-heading');
        $this->captureFullPageScreenshot($page, 'Vibe code cleanup');

        // Exact: playwright-php writes a substring name as /Let's Talk/i, and the
        // apostrophe breaks the selector once first() appends ">> nth=0". click()
        // then reports a timeout and not the parse error.
        $page->getByRole('link', ['name' => "Let's Talk", 'exact' => true])->first()->click();
        $page->waitForSelector('#contact-heading');
        $this->captureFullPageScreenshot($page, 'Contact');

        $page->getByRole('link', ['name' => 'Accessibility'])->first()->click();
        $page->waitForSelector('#accessibility-heading');
        $this->captureFullPageScreenshot($page, 'Accessibility');
    }
}
