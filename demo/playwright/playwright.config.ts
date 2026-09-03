import { defineConfig, devices } from '@playwright/test';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

// An absolute path: library code cannot assume the working directory, and the
// review of the demo reads this tree whether `php -S` or compose serves it.
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
