import { defineConfig } from '@playwright/test';

/** The domain specs: no browser, no server, nothing to install. */
export default defineConfig({
    testDir: './tests/unit',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    reporter: 'list',
});
