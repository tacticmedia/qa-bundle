import { expect, test } from '@playwright/test';
import { Ajv2020 } from 'ajv/dist/2020.js';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { buildSidecar } from '../../src/capture/sidecar.js';
import type { MetadataPayload } from '../../src/capture/types.js';

const schema: object = JSON.parse(
    readFileSync(resolve(dirname(fileURLToPath(import.meta.url)), '../../../docs/sidecar.schema.json'), 'utf8'),
) as object;

const payload: MetadataPayload = {
    url: 'https://localhost/fixture',
    title: 'Fixture Page',
    elements: [
        {
            selector: 'body > main > a',
            tag: 'a',
            x: 860,
            y: 480,
            width: 200,
            height: 120,
            text: 'Fixture Link',
            classes: 'text-red-600',
            attributes: { href: '/fixture' },
        },
    ],
};

test('what the capture writes satisfies the published schema', () => {
    const validate = new Ajv2020({ strict: false }).compile(schema);
    const sidecar = buildSidecar(payload, { width: 1920, height: 4200 }, {
        testClass: 'FixtureJourney',
        testFile: 'tests/integration/fixture.spec.ts',
    });

    expect(validate(sidecar)).toBe(true);
});

test('key order matches the PHP producer, so one tree written by both reads the same', () => {
    const sidecar = buildSidecar(payload, { width: 1920, height: 1080 }, { testClass: null, testFile: null });

    expect(Object.keys(sidecar)).toEqual([
        'url',
        'title',
        'elements',
        'pageWidth',
        'pageHeight',
        'testClass',
        'testFile',
    ]);
});

test('the page size is the size asked of the browser, never re-read from the page', () => {
    const sidecar = buildSidecar(payload, { width: 390, height: 9999 }, { testClass: null, testFile: null });

    expect(sidecar.pageWidth).toBe(390);
    expect(sidecar.pageHeight).toBe(9999);
});
