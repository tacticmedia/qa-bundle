import { expect, test } from '@playwright/test';

import { fileSafeLabel, journeySlug, scenarioParts, scenarioSlug } from '../../src/capture/slug.js';

test.describe('journeySlug', () => {
    test('PascalCases the spec path, directories included', () => {
        expect(journeySlug('admin/product-list.spec.ts')).toBe('AdminProductList');
        expect(journeySlug('checkout.spec.ts')).toBe('Checkout');
        expect(journeySlug('a/b/c.test.mts')).toBe('ABC');
    });

    test('splits camelCase so the slug reads as words', () => {
        expect(journeySlug('visitorReadsTheSite.spec.ts')).toBe('VisitorReadsTheSite');
    });

    test('always yields a basename-legal token', () => {
        expect(journeySlug('!!!.spec.ts')).toBe('Journey');
        expect(journeySlug('weird name (2).spec.ts')).toMatch(/^\w+$/);
    });
});

test.describe('scenarioSlug', () => {
    test('camelCases the describe titles and the test title into one token', () => {
        expect(scenarioSlug(['a visitor', 'reads the company site'])).toBe('aVisitorReadsTheCompanySite');
    });

    test('caps its length and stays basename-legal', () => {
        const slug = scenarioSlug([('very long title ').repeat(20)]);

        expect(slug.length).toBeLessThanOrEqual(80);
        expect(slug).toMatch(/^\w+$/);
    });

    test('never returns an empty token', () => {
        expect(scenarioSlug([])).toBe('scenario');
        expect(scenarioSlug(['---'])).toBe('scenario');
    });
});

test.describe('scenarioParts', () => {
    test('drops the project name and the spec file that lead the title path', () => {
        const parts = scenarioParts(['chromium', 'tests/e2e/home.spec.ts', 'a group', 'a test'], '/abs/tests/e2e/home.spec.ts');

        expect(parts).toEqual(['a group', 'a test']);
    });

    test('keeps everything when the file is not in the path', () => {
        expect(scenarioParts(['', 'a test'], '/abs/home.spec.ts')).toEqual(['a test']);
    });
});

test.describe('fileSafeLabel', () => {
    test('collapses a run of illegal characters into one hyphen, keeping spaces', () => {
        expect(fileSafeLabel('Close Account - Begin')).toBe('Close Account - Begin');
        expect(fileSafeLabel('Order #12/34')).toBe('Order -12-34');
        expect(fileSafeLabel('a/b')).toBe('a-b');
    });

    test('produces only characters the basename grammar allows', () => {
        expect(fileSafeLabel('Ünicode ✨ label')).toMatch(/^[A-Za-z0-9 _-]+$/);
    });
});
