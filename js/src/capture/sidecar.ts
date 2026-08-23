import type { MetadataPayload, Sidecar, Viewport } from './types.js';

/**
 * Key order matches the PHP producer, so one tree written by both reads
 * identically. The page size is the size asked of the browser, never re-read
 * from the page: a re-measure can come back short of the image, and the
 * resolver needs these numbers to be the PNG's exactly.
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
