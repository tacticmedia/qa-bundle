import { expect } from '@playwright/test';
import { Ajv2020 } from 'ajv/dist/2020.js';
import { readFile, readdir } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { test } from '../../src/playwright/index.js';

const BASENAME = /^(?<journey>\w+)-(?<scenario>\w+)-(?<sequence>\d{3})_(?<label>[A-Za-z0-9 _-]+)$/;
const MODE = /^[a-z][a-z0-9-]*$/;
const VIEWPORT = /^\d+x\d+$/;

const root = process.env.QA_SCREENSHOTS_DIR as string;
const desktop = () => join(root, 'light', '1920x1080', 'landscape');

const TALL_PAGE = `
    <!doctype html>
    <html><head><title>Tall Fixture</title></head>
    <body style="margin:0">
        <main style="height:3200px;background:linear-gradient(#eee,#333)">
            <h1 id="tall-heading">A page taller than every viewport</h1>
            <a href="/_dev/screenshots">Back to the review</a>
        </main>
    </body></html>
`;

/** Bytes 16..24 of a PNG are the IHDR width and height. */
function pngSize(image: Buffer): { width: number; height: number } {
    return { width: image.readUInt32BE(16), height: image.readUInt32BE(20) };
}

test('captures every viewport and mode into a tree the reader can parse', async ({ page, qaScreenshots }) => {
    await page.goto('/_dev/screenshots');
    await qaScreenshots.capture('Review empty');

    await page.setContent(TALL_PAGE);
    await qaScreenshots.capture('Tall page');

    // A label already captured in this scenario is skipped.
    await qaScreenshots.capture('Tall page');

    const groups = await discoverGroups(root);

    expect(groups).toHaveLength(10);

    const shot = await readdir(desktop());

    expect(shot.filter((name) => name.endsWith('_Review empty.png'))).toHaveLength(1);
    expect(shot.filter((name) => name.endsWith('_Tall page.png'))).toHaveLength(1);
    expect(shot).toContain('Capture-capturesEveryViewportAndModeIntoATreeTheReaderCanParse-001_Review empty.png');
    expect(shot).toContain('Capture-capturesEveryViewportAndModeIntoATreeTheReaderCanParse-002_Tall page.png');

    const validate = new Ajv2020({ strict: false }).compile(
        JSON.parse(
            await readFile(resolve(dirname(fileURLToPath(import.meta.url)), '../../../docs/sidecar.schema.json'), 'utf8'),
        ) as object,
    );

    for (const group of groups) {
        for (const file of (await readdir(group)).filter((name) => name.endsWith('.png'))) {
            const base = file.slice(0, -4);

            expect(base).toMatch(BASENAME);

            const image = await readFile(join(group, `${base}.png`));
            const sidecar = JSON.parse(await readFile(join(group, `${base}.json`), 'utf8')) as {
                pageWidth: number;
                pageHeight: number;
            };

            expect(validate(sidecar)).toBe(true);
            expect(pngSize(image)).toEqual({ width: sidecar.pageWidth, height: sidecar.pageHeight });
        }
    }
});

test('a page taller than the viewport grows the image, and the sidecar records the same size', async ({ page, qaScreenshots }) => {
    await page.setContent(TALL_PAGE);
    await qaScreenshots.capture('Tall only');

    const file = (await readdir(desktop())).find((name) => name.endsWith('_Tall only.json')) as string;
    const sidecar = JSON.parse(await readFile(join(desktop(), file), 'utf8')) as { pageHeight: number; elements: unknown[] };

    expect(sidecar.pageHeight).toBeGreaterThan(1080);
    expect(sidecar.elements.length).toBeGreaterThan(0);
});

async function discoverGroups(tree: string): Promise<string[]> {
    const groups: string[] = [];

    for (const mode of await readdir(tree)) {
        expect(mode).toMatch(MODE);

        for (const viewport of await readdir(join(tree, mode))) {
            expect(viewport).toMatch(VIEWPORT);

            for (const orientation of await readdir(join(tree, mode, viewport))) {
                expect(['portrait', 'landscape']).toContain(orientation);
                groups.push(join(tree, mode, viewport, orientation));
            }
        }
    }

    return groups;
}
