import { mkdir, readdir, rm } from 'node:fs/promises';
import { join } from 'node:path';

/**
 * Empties the tree and keeps the root directory, which is usually a bind-mount
 * target and must not be replaced.
 */
export async function clearTree(root: string): Promise<void> {
    await mkdir(root, { recursive: true });

    const entries = await readdir(root);

    await Promise.all(entries.map((entry) => rm(join(root, entry), { recursive: true, force: true })));
}
