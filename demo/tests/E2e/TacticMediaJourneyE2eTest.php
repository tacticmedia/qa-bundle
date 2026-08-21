<?php

declare(strict_types=1);

namespace App\Tests\E2e;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Panther\PantherTestCase;
use TacticMedia\QaBundle\Test\JourneyScreenshots;

#[Group('e2e')]
final class TacticMediaJourneyE2eTest extends PantherTestCase
{
    use JourneyScreenshots;

    #[TestDox('A visitor reads every page of the company site')]
    public function testVisitorReadsTheCompanySite(): void
    {
        $client = self::createPantherClient(['external_base_uri' => 'https://tacticmedia.com.au']);

        $client->request('GET', '/');
        $client->waitFor('main');
        $this->captureFullPageScreenshot($client, 'Home');

        $client->clickLink('Software Development');
        $client->waitFor('#software-development-heading');
        $this->captureFullPageScreenshot($client, 'Software development');

        $client->clickLink('Qualified Software Escrow');
        $client->waitFor('#qualified-software-escrow-heading');
        $this->captureFullPageScreenshot($client, 'Software escrow');

        $client->clickLink('Vibe Code Cleanup');
        $client->waitFor('#vibe-code-cleanup-heading');
        $this->captureFullPageScreenshot($client, 'Vibe code cleanup');

        $client->clickLink("Let's Talk");
        $client->waitFor('#contact-heading');
        $this->captureFullPageScreenshot($client, 'Contact');

        $client->clickLink("Accessibility");
        $client->waitFor('#accessibility-heading');
        $this->captureFullPageScreenshot($client, 'Accessibility');
    }
}
