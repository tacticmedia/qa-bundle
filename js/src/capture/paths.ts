import { resolve } from 'node:path';

import type { Viewport } from './types.js';

/**
 * The environment variable has precedence in each location, so the global setup
 * and the capture fixture always use the same tree.
 */
export function resolveRoot(option?: string): string {
    const configured = process.env.QA_SCREENSHOTS_DIR;

    if (configured !== undefined && configured !== '') {
        return resolve(configured);
    }

    if (option !== undefined && option !== '') {
        return resolve(option);
    }

    return resolve(process.cwd(), 'var/screenshots');
}

/** A square is portrait, matching the reader's closed set. */
export function orientationOf(viewport: Viewport): 'portrait' | 'landscape' {
    return viewport.height >= viewport.width ? 'portrait' : 'landscape';
}

export function groupDirectory(root: string, mode: string, viewport: Viewport): string {
    return resolve(root, mode, `${viewport.width}x${viewport.height}`, orientationOf(viewport));
}

export function sequencePrefix(sequence: number): string {
    return String(sequence).padStart(3, '0');
}
