import type { FullConfig } from '@playwright/test';

import { clearTree } from '../capture/clear.js';
import { resolveRoot } from '../capture/paths.js';
import type { QaScreenshotsOptions } from '../capture/types.js';

/**
 * The tree holds one run, so this setup empties it before the first worker
 * starts, which is the only safe point when workers run in parallel. This setup
 * does not read a root that test.use() sets in a spec: set the root in the
 * Playwright configuration or in QA_SCREENSHOTS_DIR.
 */
export default async function globalSetup(config: FullConfig): Promise<void> {
    const roots = new Set<string>();

    for (const project of config.projects) {
        const options = (project.use as { qaScreenshotsOptions?: QaScreenshotsOptions }).qaScreenshotsOptions;

        roots.add(resolveRoot(options?.root));
    }

    if (roots.size === 0) {
        roots.add(resolveRoot());
    }

    for (const root of roots) {
        await clearTree(root);
    }
}
