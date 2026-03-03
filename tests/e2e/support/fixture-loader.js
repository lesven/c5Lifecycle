import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const fixturesRoot = resolve(process.cwd(), 'tests/fixtures/e2e');

export function loadFixture(name) {
  const filePath = resolve(fixturesRoot, `${name}.json`);
  const raw = readFileSync(filePath, 'utf-8');
  return JSON.parse(raw);
}
