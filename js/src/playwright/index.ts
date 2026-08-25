import { relative } from 'node:path';

import { test as base } from '@playwright/test';

import { CaptureSession } from '../capture/capture.js';
import { resolveRoot } from '../capture/paths.js';
import { journeySlug, scenarioParts, scenarioSlug } from '../capture/slug.js';
import { DEFAULT_COLOR_SCHEMES, DEFAULT_VIEWPORTS } from '../capture/types.js';
import type { QaScreenshotsOptions } from '../capture/types.js';

export type { ColorScheme, QaScreenshotsOptions, Viewport } from '../capture/types.js';

export interface QaScreenshots {
    /**
     * Captures the settled screen at each configured viewport and colour scheme.
     * The fixture skips a label already captured in this scenario.
     */
    capture(label: string): Promise<void>;
}

/**
 * The Playwright `test` with a `qaScreenshots` fixture. To replace the defaults,
 * set `use: { qaScreenshotsOptions: { ... } }` in the Playwright configuration.
 */
export const test = base.extend<{
    qaScreenshotsOptions: QaScreenshotsOptions;
    qaScreenshots: QaScreenshots;
}>({
    qaScreenshotsOptions: [{}, { option: true }],

    qaScreenshots: async ({ page, qaScreenshotsOptions }, use, testInfo) => {
        const journey = journeySlug(relative(testInfo.project.testDir, testInfo.file));
        const scenario = scenarioSlug(scenarioParts(testInfo.titlePath, testInfo.file));

        await use(
            new CaptureSession(
                page,
                {
                    journey,
                    scenario,
                    testClass: journey,
                    testFile: relative(process.cwd(), testInfo.file),
                },
                resolveRoot(qaScreenshotsOptions.root),
                qaScreenshotsOptions.viewports ?? DEFAULT_VIEWPORTS,
                qaScreenshotsOptions.colorSchemes ?? DEFAULT_COLOR_SCHEMES,
            ),
        );
    },
});

export { expect } from '@playwright/test';
