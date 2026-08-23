import { expect, test } from '@playwright/test';
import { mkdtemp, mkdir, readdir, stat, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import { clearTree } from '../../src/capture/clear.js';

test('empties the tree but keeps the root, which is normally a bind-mount target', async () => {
    const root = await mkdtemp(join(tmpdir(), 'qa-capture-'));

    await mkdir(join(root, 'light/1920x1080/landscape'), { recursive: true });
    await writeFile(join(root, 'light/1920x1080/landscape/A-b-001_C.png'), 'png');

    await clearTree(root);

    expect((await stat(root)).isDirectory()).toBe(true);
    expect(await readdir(root)).toEqual([]);
});

test('creates the root when it does not exist yet', async () => {
    const root = join(await mkdtemp(join(tmpdir(), 'qa-capture-')), 'nested');

    await clearTree(root);

    expect((await stat(root)).isDirectory()).toBe(true);
});
