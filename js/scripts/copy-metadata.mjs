// npm `files` cannot reference a path outside the package root, so the build copies
// the shared capture script. Both producers read one source, resources/capture.
import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const source = resolve(here, '../../resources/capture/metadata.js');
const target = resolve(here, '../dist/capture/metadata.js');

mkdirSync(dirname(target), { recursive: true });
copyFileSync(source, target);

console.log(`copied ${source} -> ${target}`);
