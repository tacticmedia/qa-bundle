import type { FullConfig } from '@playwright/test';

import { clearTree } from '../capture/clear.js';
import { resolveRoot } from '../capture/paths.js';
import type { QaScreenshotsOptions } from '../capture/types.js';

/**
 * The tree holds one run, so it is emptied before any worker starts - the only
 * point that is safe with parallel workers. A root set with test.use() inside a
 * spec is invisible here: set it in the config or in QA_SCREENSHOTS_DIR.
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
