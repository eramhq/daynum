#!/usr/bin/env node
/**
 * Node-side oracle generator for Daynum conformance fixtures.
 *
 * Produces the same JSONL schema as tools/generate-fixtures-php.php using
 * Node's `Intl.DateTimeFormat`, which ships its own ICU. Running both
 * generators and diffing their output catches bugs that live in either ICU
 * version — disagreement on any row is a bug in one of them, and we want to
 * know about it before the fixture makes it into the commit.
 *
 * Usage:
 *   node tools/generate-fixtures-node.mjs
 *
 * Writes:
 *   tests/fixtures/gregorian.node.jsonl.gz
 *   tests/fixtures/jalali.node.jsonl.gz
 *
 * tools/verify-oracles-agree.php then diffs the `.jsonl.gz` files from the two
 * generators byte-for-byte (ignoring the header metadata line).
 */

import { createWriteStream } from 'node:fs';
import { createGzip } from 'node:zlib';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const FIXTURE_DIR = path.resolve(__dirname, '../tests/fixtures');
const START_YEAR = 1700;
const END_YEAR = 2300;

await mkdir(FIXTURE_DIR, { recursive: true });

const icuVersion = process.versions.icu || 'unknown';
const header = {
  generator: 'generate-fixtures-node.mjs',
  nodeVersion: process.version,
  icuVersion,
  generated: new Date().toISOString(),
  range: `${START_YEAR}-01-01..${END_YEAR}-12-31`,
};

const gregFmt = new Intl.DateTimeFormat('en-US-u-ca-gregory', {
  timeZone: 'UTC',
  year: 'numeric', month: 'numeric', day: 'numeric',
  weekday: 'short',
});
const persFmt = new Intl.DateTimeFormat('en-US-u-ca-persian', {
  timeZone: 'UTC',
  year: 'numeric', month: 'numeric', day: 'numeric',
});

const gregStream = createGzip();
const jalStream = createGzip();
gregStream.pipe(createWriteStream(path.join(FIXTURE_DIR, 'gregorian.node.jsonl.gz')));
jalStream.pipe(createWriteStream(path.join(FIXTURE_DIR, 'jalali.node.jsonl.gz')));

gregStream.write(JSON.stringify({ meta: { ...header, calendar: 'gregorian' } }) + '\n');
jalStream.write(JSON.stringify({ meta: { ...header, calendar: 'persian' } }) + '\n');

const DOW_INDEX = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

// Local Gregorian↔JDN (Fliegel–Van Flandern) so we can bootstrap without
// importing Daynum.
function gregorianToJdn(y, m, d) {
  const a = Math.floor((14 - m) / 12);
  const yy = y + 4800 - a;
  const mm = m + 12 * a - 3;
  return d
    + Math.floor((153 * mm + 2) / 5)
    + 365 * yy
    + Math.floor(yy / 4)
    - Math.floor(yy / 100)
    + Math.floor(yy / 400)
    - 32045;
}

const startJdn = gregorianToJdn(START_YEAR, 1, 1);
const endJdn = gregorianToJdn(END_YEAR, 12, 31);

let count = 0;
for (let jdn = startJdn; jdn <= endJdn; jdn++) {
  // JDN 2440588 = 1970-01-01 00:00:00 UTC.
  const date = new Date((jdn - 2440588) * 86400 * 1000);

  const gParts = Object.fromEntries(gregFmt.formatToParts(date).map((p) => [p.type, p.value]));
  const jParts = Object.fromEntries(persFmt.formatToParts(date).map((p) => [p.type, p.value]));

  const gy = Number(gParts.year);
  const gm = Number(gParts.month);
  const gd = Number(gParts.day);
  const jy = Number(jParts.year);
  const jm = Number(jParts.month);
  const jd = Number(jParts.day);
  const dow = DOW_INDEX[gParts.weekday] ?? -1;

  gregStream.write(JSON.stringify({ jdn, g: [gy, gm, gd], dow }) + '\n');
  jalStream.write(JSON.stringify({ jdn, g: [gy, gm, gd], j: [jy, jm, jd], dow }) + '\n');

  count++;
  if (count % 50000 === 0) {
    process.stderr.write(`  ${count} rows written (${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')})…\n`);
  }
}

gregStream.end();
jalStream.end();

await new Promise((resolve) => gregStream.on('finish', resolve));
await new Promise((resolve) => jalStream.on('finish', resolve));

process.stderr.write(`Done. ${count} rows per calendar. ICU ${icuVersion}.\n`);
