import { defineConfig } from '@playwright/test';

/** The unit specs. They need no browser and no server. */
export default defineConfig({
    testDir: './tests/unit',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    reporter: 'list',
});
