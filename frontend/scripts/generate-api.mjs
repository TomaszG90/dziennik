import { readFile, writeFile } from 'node:fs/promises';
import { stringify } from 'yaml';

const jsonPath = new URL('../../shared/openapi.json', import.meta.url);
const yamlPath = new URL('../../shared/openapi.yaml', import.meta.url);
const spec = JSON.parse(await readFile(jsonPath, 'utf8'));

await writeFile(yamlPath, stringify(spec, { lineWidth: 0 }), 'utf8');
