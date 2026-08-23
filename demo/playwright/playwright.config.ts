import { defineConfig, devices } from '@playwright/test';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

// Absolute: library code cannot assume the working directory, and the demo's own
// review reads this exact tree whether it is served by `php -S` or by compose.
process.env.QA_SCREENSHOTS_DIR ??= resolve(here, '../var/screenshots');

export default defineConfig({
    testDir: './tests',
    workers: 1,
    reporter: 'list',
    globalSetup: createRequire(import.meta.url).resolve('@tacticmedia/qa-capture/playwright/global-setup'),
    use: {
        baseURL: 'https://tacticmedia.com.au',
        ...devices['Desktop Chrome'],
    },
});
