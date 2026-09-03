import { defineConfig, devices } from '@playwright/test';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

/**
 * Captures against the bundle's own fixture host, then reads the tree back. The
 * root is an environment variable, so the PHP contract test can read the same
 * directory.
 */
const root = process.env.QA_SCREENSHOTS_DIR ?? resolve(here, 'test-results/screenshots');

process.env.QA_SCREENSHOTS_DIR = root;

export default defineConfig({
    testDir: './tests/integration',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    reporter: 'list',
    globalSetup: './src/playwright/global-setup.ts',
    use: {
        baseURL: 'http://127.0.0.1:8123',
        ...devices['Desktop Chrome'],
    },
    webServer: {
        command: 'php -S 127.0.0.1:8123 -t ../tests/Fixtures/app/public ../tests/Fixtures/app/public/index.php',
        url: 'http://127.0.0.1:8123/_dev/screenshots',
        reuseExistingServer: !process.env.CI,
        stdout: 'ignore',
        stderr: 'pipe',
    },
});
