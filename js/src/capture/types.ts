export interface Viewport {
    width: number;
    height: number;
}

/** What emulateMedia accepts and the mode directory grammar allows. */
export type ColorScheme = 'light' | 'dark' | 'no-preference';

export interface QaScreenshotsOptions {
    /**
     * Screenshot tree root. QA_SCREENSHOTS_DIR has precedence over it, so the
     * global setup and the capture always use the same root.
     */
    root?: string;
    viewports?: Viewport[];
    colorSchemes?: ColorScheme[];
}

export interface MetadataElement {
    selector: string;
    tag: string;
    x: number;
    y: number;
    width: number;
    height: number;
    text: string | null;
    classes: string | null;
    attributes: Record<string, string>;
}

/** What resources/capture/metadata.js returns. */
export interface MetadataPayload {
    url: string;
    title: string;
    elements: MetadataElement[];
}

export interface Sidecar extends MetadataPayload {
    pageWidth: number;
    pageHeight: number;
    testClass: string | null;
    testFile: string | null;
}

/** iPhone portrait, iPad portrait and landscape, common desktop, high-end laptop. */
export const DEFAULT_VIEWPORTS: Viewport[] = [
    { width: 390, height: 844 },
    { width: 768, height: 1024 },
    { width: 1024, height: 768 },
    { width: 1920, height: 1080 },
    { width: 1728, height: 1117 },
];

export const DEFAULT_COLOR_SCHEMES: ColorScheme[] = ['light', 'dark'];
