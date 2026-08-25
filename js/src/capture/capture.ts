import { existsSync, readFileSync } from 'node:fs';
import { mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

import type { Page } from '@playwright/test';

import { groupDirectory, sequencePrefix } from './paths.js';
import { buildSidecar } from './sidecar.js';
import { fileSafeLabel } from './slug.js';
import type { ColorScheme, MetadataPayload, Viewport } from './types.js';

/** Built into dist/capture, and read from resources/ when running from source. */
const SCRIPT_CANDIDATES = ['./metadata.js', '../../../resources/capture/metadata.js'];

let script: string | undefined;

export function metadataScript(): string {
    if (script !== undefined) {
        return script;
    }

    for (const candidate of SCRIPT_CANDIDATES) {
        const path = fileURLToPath(new URL(candidate, import.meta.url));

        if (existsSync(path)) {
            script = readFileSync(path, 'utf8').trimEnd();

            return script;
        }
    }

    throw new Error('Could not find capture/metadata.js. Run `npm run build` in the package root.');
}

export interface CaptureIdentity {
    journey: string;
    scenario: string;
    testClass: string | null;
    testFile: string | null;
}

/**
 * The captures of one scenario. The sequence and the set of used labels reset
 * with the session, so a helper that is called twice captures its screen once,
 * and each scenario starts at 001.
 */
export class CaptureSession {
    private sequence = 0;

    private readonly captured = new Set<string>();

    constructor(
        private readonly page: Page,
        private readonly identity: CaptureIdentity,
        private readonly root: string,
        private readonly viewports: Viewport[],
        private readonly colorSchemes: ColorScheme[],
    ) {}

    async capture(label: string): Promise<void> {
        if (this.captured.has(label)) {
            return;
        }

        const original = this.page.viewportSize();

        if (original === null) {
            throw new Error(
                'qa-capture needs a fixed viewport. Remove `viewport: null` from the Playwright config, or set one with test.use({ viewport }).',
            );
        }

        this.captured.add(label);

        const stage = `${sequencePrefix(++this.sequence)}_${fileSafeLabel(label)}`;

        try {
            for (const viewport of this.viewports) {
                await this.captureViewport(viewport, stage);
            }
        } finally {
            await this.page.emulateMedia({ colorScheme: null });
            await this.page.setViewportSize(original);
        }
    }

    /**
     * The document height is only known after layout at the device width. The
     * viewport is then increased to the measured height and measured a second time,
     * because vh-sized elements increase with it. The viewport height then equals
     * the number written to the sidecar, which is the required invariant.
     */
    private async captureViewport(viewport: Viewport, stage: string): Promise<void> {
        const [first] = this.colorSchemes;

        await this.page.setViewportSize(viewport);

        if (first !== undefined) {
            await this.page.emulateMedia({ colorScheme: first });
        }

        let height = Math.max(viewport.height, await this.contentHeight());

        await this.page.setViewportSize({ width: viewport.width, height });

        const grown = Math.max(height, await this.contentHeight());

        if (grown > height) {
            height = grown;
            await this.page.setViewportSize({ width: viewport.width, height });
        }

        const sidecar = JSON.stringify(
            buildSidecar(await this.metadata(), { width: viewport.width, height }, this.identity),
        );

        for (const mode of this.colorSchemes) {
            const directory = groupDirectory(this.root, mode, viewport);

            await mkdir(directory, { recursive: true });
            await this.page.emulateMedia({ colorScheme: mode });

            // scale 'css' gives one image pixel for each CSS pixel at any
            // deviceScaleFactor of the context, which is the value the sidecar records.
            const image = await this.page.screenshot({ scale: 'css', animations: 'disabled' });
            const base = join(directory, `${this.identity.journey}-${this.identity.scenario}-${stage}`);

            await writeFile(`${base}.png`, image);
            await writeFile(`${base}.json`, sidecar);
        }
    }

    private async contentHeight(): Promise<number> {
        return this.page.evaluate(() =>
            Math.ceil(
                Math.max(
                    document.documentElement.scrollHeight,
                    document.documentElement.offsetHeight,
                    document.body === null ? 0 : document.body.scrollHeight,
                    document.body === null ? 0 : document.body.offsetHeight,
                ),
            ),
        );
    }

    private async metadata(): Promise<MetadataPayload> {
        const raw: unknown = await this.page.evaluate(metadataScript());

        if (typeof raw !== 'string') {
            throw new Error(`The metadata script returned ${typeof raw}, expected a JSON string.`);
        }

        return JSON.parse(raw) as MetadataPayload;
    }
}
