import type { MetadataPayload, Sidecar, Viewport } from './types.js';

/**
 * The key order is the same as in the PHP producer, so a tree written by both
 * producers reads the same. The page size is the size requested from the browser.
 * The code does not read it back from the page, because a second measurement can
 * return a value smaller than the image, and the resolver needs these numbers to
 * equal the size of the PNG.
 */
export function buildSidecar(
    payload: MetadataPayload,
    size: Viewport,
    origin: { testClass: string | null; testFile: string | null },
): Sidecar {
    return {
        url: payload.url,
        title: payload.title,
        elements: payload.elements,
        pageWidth: size.width,
        pageHeight: size.height,
        testClass: origin.testClass,
        testFile: origin.testFile,
    };
}
