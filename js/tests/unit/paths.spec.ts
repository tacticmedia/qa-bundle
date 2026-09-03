import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';

import { groupDirectory, orientationOf, resolveRoot, sequencePrefix } from '../../src/capture/paths.js';

test.describe('resolveRoot', () => {
    test.afterEach(() => {
        delete process.env.QA_SCREENSHOTS_DIR;
    });

    test('the environment has precedence over the option, so setup and capture use the same root', () => {
        process.env.QA_SCREENSHOTS_DIR = '/tmp/from-env';

        expect(resolveRoot('/tmp/from-option')).toBe('/tmp/from-env');
    });

    test('falls back to the option, then to var/screenshots under the working directory', () => {
        expect(resolveRoot('/tmp/from-option')).toBe('/tmp/from-option');
        expect(resolveRoot()).toBe(resolve(process.cwd(), 'var/screenshots'));
    });
});

test('a square viewport is portrait, matching the reader', () => {
    expect(orientationOf({ width: 800, height: 800 })).toBe('portrait');
    expect(orientationOf({ width: 1024, height: 768 })).toBe('landscape');
});

test('the group directory names the requested viewport, not the image', () => {
    expect(groupDirectory('/root', 'dark', { width: 390, height: 844 })).toBe('/root/dark/390x844/portrait');
});

test('the sequence is zero-padded to three digits', () => {
    expect(sequencePrefix(1)).toBe('001');
    expect(sequencePrefix(42)).toBe('042');
});
