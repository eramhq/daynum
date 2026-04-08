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
 *   tests/fixtures/hijri-civil.node.jsonl.gz
 *   tests/fixtures/hijri-umalqura.node.jsonl.gz
 *
 * The UAQ file is filtered to the same native year range the PHP generator
 * uses; the range is read from the PHP-side generated file's header so the
 * two sides can agree without hardcoding numbers.
 *
 * tools/verify-oracles-agree.php then diffs the `.jsonl.gz` files from the two
 * generators byte-for-byte (ignoring the header metadata line).
 */

import { createWriteStream, readFileSync, existsSync } from 'node:fs';
import { createGzip, gunzipSync } from 'node:zlib';
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
const hcFmt = new Intl.DateTimeFormat('en-US-u-ca-islamic-civil', {
  timeZone: 'UTC',
  year: 'numeric', month: 'numeric', day: 'numeric',
});
const uaqFmt = new Intl.DateTimeFormat('en-US-u-ca-islamic-umalqura', {
  timeZone: 'UTC',
  year: 'numeric', month: 'numeric', day: 'numeric',
});

// Read the UAQ year range from the PHP-side fixture header so both sides
// agree on what "native range" means. If the PHP fixture hasn't been built
// yet, fall back to letting the filter through nothing (an empty UAQ fixture
// is better than a fixture containing silent-fallback rows).
let uaqMinYear = null;
let uaqMaxYear = null;
const phpFixturePath = path.join(FIXTURE_DIR, 'hijri-umalqura.jsonl.gz');
if (existsSync(phpFixturePath)) {
  const raw = gunzipSync(readFileSync(phpFixturePath)).toString('utf8');
  const firstLineEnd = raw.indexOf('\n');
  const headerLine = firstLineEnd >= 0 ? raw.slice(0, firstLineEnd) : raw;
  try {
    const parsed = JSON.parse(headerLine);
    uaqMinYear = parsed?.meta?.uaqMinYear ?? null;
    uaqMaxYear = parsed?.meta?.uaqMaxYear ?? null;
  } catch {
    // malformed header — leave as null, UAQ output will be empty
  }
}

const gregStream = createGzip();
const jalStream = createGzip();
const hcStream = createGzip();
const uaqStream = createGzip();
gregStream.pipe(createWriteStream(path.join(FIXTURE_DIR, 'gregorian.node.jsonl.gz')));
jalStream.pipe(createWriteStream(path.join(FIXTURE_DIR, 'jalali.node.jsonl.gz')));
hcStream.pipe(createWriteStream(path.join(FIXTURE_DIR, 'hijri-civil.node.jsonl.gz')));
uaqStream.pipe(createWriteStream(path.join(FIXTURE_DIR, 'hijri-umalqura.node.jsonl.gz')));

gregStream.write(JSON.stringify({ meta: { ...header, calendar: 'gregorian' } }) + '\n');
jalStream.write(JSON.stringify({ meta: { ...header, calendar: 'persian' } }) + '\n');
hcStream.write(JSON.stringify({ meta: { ...header, calendar: 'islamic-civil' } }) + '\n');
uaqStream.write(JSON.stringify({
  meta: { ...header, calendar: 'islamic-umalqura', uaqMinYear, uaqMaxYear },
}) + '\n');

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
let uaqCount = 0;
for (let jdn = startJdn; jdn <= endJdn; jdn++) {
  // JDN 2440588 = 1970-01-01 00:00:00 UTC.
  const date = new Date((jdn - 2440588) * 86400 * 1000);

  const gParts = Object.fromEntries(gregFmt.formatToParts(date).map((p) => [p.type, p.value]));
  const jParts = Object.fromEntries(persFmt.formatToParts(date).map((p) => [p.type, p.value]));
  const hcParts = Object.fromEntries(hcFmt.formatToParts(date).map((p) => [p.type, p.value]));
  const uaqParts = Object.fromEntries(uaqFmt.formatToParts(date).map((p) => [p.type, p.value]));

  const gy = Number(gParts.year);
  const gm = Number(gParts.month);
  const gd = Number(gParts.day);
  const jy = Number(jParts.year);
  const jm = Number(jParts.month);
  const jd = Number(jParts.day);
  const hcy = Number(hcParts.year);
  const hcm = Number(hcParts.month);
  const hcd = Number(hcParts.day);
  const uaqY = Number(uaqParts.year);
  const uaqM = Number(uaqParts.month);
  const uaqD = Number(uaqParts.day);
  const dow = DOW_INDEX[gParts.weekday] ?? -1;

  gregStream.write(JSON.stringify({ jdn, g: [gy, gm, gd], dow }) + '\n');
  jalStream.write(JSON.stringify({ jdn, g: [gy, gm, gd], j: [jy, jm, jd], dow }) + '\n');
  hcStream.write(JSON.stringify({ jdn, g: [gy, gm, gd], h: [hcy, hcm, hcd], dow }) + '\n');

  if (uaqMinYear !== null && uaqMaxYear !== null && uaqY >= uaqMinYear && uaqY <= uaqMaxYear) {
    uaqStream.write(JSON.stringify({ jdn, g: [gy, gm, gd], h: [uaqY, uaqM, uaqD], dow }) + '\n');
    uaqCount++;
  }

  count++;
  if (count % 50000 === 0) {
    process.stderr.write(`  ${count} rows written (${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')})…\n`);
  }
}

// Attach the 'finish' listeners BEFORE calling .end() to avoid the race
// where .end() may have already drained the gzip stream by the time the
// listener is registered. Node's 'finish' event does not fire again for
// late listeners.
const finished = [gregStream, jalStream, hcStream, uaqStream].map(
  (s) => new Promise((resolve) => s.once('finish', resolve)),
);
gregStream.end();
jalStream.end();
hcStream.end();
uaqStream.end();
await Promise.all(finished);

process.stderr.write(`Done. ${count} rows per base calendar, ${uaqCount} UAQ rows in range. ICU ${icuVersion}.\n`);
