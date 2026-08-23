import { basename } from 'node:path';

const SCENARIO_CAP = 80;

/**
 * Mirrors JourneyScreenshots::fileSafe(): a run of characters outside the label
 * grammar collapses to one hyphen. Replaced, never dropped, so distinct labels
 * stay distinct.
 */
export function fileSafeLabel(label: string): string {
    return label.replace(/[^A-Za-z0-9 _-]+/g, '-');
}

/**
 * The spec file, PascalCased with its directories included. Two specs of the same
 * name in different directories would otherwise slug identically and overwrite
 * each other.
 */
export function journeySlug(specPath: string): string {
    const withoutExtension = specPath.replace(/\.(spec|test)\.[cm]?[jt]sx?$/i, '').replace(/\.[cm]?[jt]sx?$/i, '');
    const slug = words(withoutExtension).map(capitalize).join('');

    return slug === '' ? 'Journey' : slug;
}

/**
 * The describe titles and the test title, camelCased into one token.
 */
export function scenarioSlug(parts: string[]): string {
    const tokens = parts.flatMap(words);

    if (tokens.length === 0) {
        return 'scenario';
    }

    const first = tokens[0] as string;
    const slug = first.toLowerCase() + tokens.slice(1).map(capitalize).join('');

    return slug.slice(0, SCENARIO_CAP);
}

/**
 * Playwright's titlePath starts with the project name and the spec file. What
 * identifies the scenario is everything after those.
 */
export function scenarioParts(titlePath: string[], specFile: string): string[] {
    const file = basename(specFile);
    const index = titlePath.findIndex((part) => basename(part) === file);

    return index === -1 ? titlePath.filter((part) => part !== '') : titlePath.slice(index + 1);
}

function words(value: string): string[] {
    return value
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .split(/[^A-Za-z0-9]+/)
        .filter((part) => part !== '');
}

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1).toLowerCase();
}
