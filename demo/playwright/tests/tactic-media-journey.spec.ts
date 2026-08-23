import { test } from '@tacticmedia/qa-capture/playwright';

/**
 * The Playwright twin of tests/E2e/TacticMediaJourneyE2eTest.php, stage for stage
 * and label for label. Both write the same tree, so switching producers yields
 * the same six screens in the review.
 */
test('a visitor reads every page of the company site', async ({ page, qaScreenshots }) => {
    await page.goto('/');
    await page.waitForSelector('main');
    await qaScreenshots.capture('Home');

    await page.getByRole('link', { name: 'Software Development' }).first().click();
    await page.waitForSelector('#software-development-heading');
    await qaScreenshots.capture('Software development');

    await page.getByRole('link', { name: 'Qualified Software Escrow' }).first().click();
    await page.waitForSelector('#qualified-software-escrow-heading');
    await qaScreenshots.capture('Software escrow');

    await page.getByRole('link', { name: 'Vibe Code Cleanup' }).first().click();
    await page.waitForSelector('#vibe-code-cleanup-heading');
    await qaScreenshots.capture('Vibe code cleanup');

    await page.getByRole('link', { name: "Let's Talk" }).first().click();
    await page.waitForSelector('#contact-heading');
    await qaScreenshots.capture('Contact');

    await page.getByRole('link', { name: 'Accessibility' }).first().click();
    await page.waitForSelector('#accessibility-heading');
    await qaScreenshots.capture('Accessibility');
});
