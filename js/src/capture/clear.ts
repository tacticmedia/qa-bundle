import { mkdir, readdir, rm } from 'node:fs/promises';
import { join } from 'node:path';

/**
 * Empties the tree and keeps the root itself, which is normally a bind-mount
 * target that must not be replaced.
 */
export async function clearTree(root: string): Promise<void> {
    await mkdir(root, { recursive: true });

    const entries = await readdir(root);

    await Promise.all(entries.map((entry) => rm(join(root, entry), { recursive: true, force: true })));
}
