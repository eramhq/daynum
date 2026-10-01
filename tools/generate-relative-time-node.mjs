#!/usr/bin/env node
/**
 * Node-side oracle for relative-time strings ("3 days ago", "in 2 hours").
 *
 * PHP's ext-intl has no RelativeTimeFormatter, so unlike the calendar
 * fixtures this one has a single (Node) source. It is consumed directly by
 * tests/Conformance/RelativeTimeConformanceTest.php.
 *
 * Usage:
 *   node tools/generate-relative-time-node.mjs
 *
 * Writes:
 *   tests/fixtures/relative-time.jsonl.gz
 *
 * Values stop below 1000 because Intl groups larger numbers ("1,000 years
 * ago") and Daynum does not emit digit-group separators.
 */

import { createWriteStream } from 'node:fs';
import { createGzip } from 'node:zlib';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.resolve(__dirname, '../tests/fixtures/relative-time.jsonl.gz');

const LOCALES = ['en', 'fa', 'fa-AF', 'ar'];
const UNITS = ['second', 'minute', 'hour', 'day', 'week', 'month', 'year'];

// 0..130 covers every Arabic plural category (zero, one, two, few 3–10,
// many 11–99, other 100–102) at least once; the tail re-checks the n % 100
// rules above one hundred.
const VALUES = [
  ...Array.from({ length: 131 }, (_, i) => i),
  199, 200, 201, 202, 203, 210, 211, 299, 300, 365, 999,
];

const icuVersion = process.versions.icu || 'unknown';

const gzip = createGzip();
gzip.pipe(createWriteStream(OUT));
const finished = new Promise((resolve) => gzip.once('finish', resolve));

// No nodeVersion / timestamp in the header: the ICU version is the drift
// signal, and a patch-level Node upgrade must not change the file.
gzip.write(JSON.stringify({
  meta: { generator: 'generate-relative-time-node.mjs', icuVersion, numeric: 'always' },
}) + '\n');

let rows = 0;
for (const locale of LOCALES) {
  // The word for a zero difference ("now"), from numeric: 'auto'.
  const auto = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
  gzip.write(JSON.stringify({ locale, now: auto.format(0, 'second') }) + '\n');
  rows++;

  const fmt = new Intl.RelativeTimeFormat(locale, { numeric: 'always' });
  for (const unit of UNITS) {
    for (const value of VALUES) {
      gzip.write(JSON.stringify({
        locale,
        unit,
        value,
        past: fmt.format(-value, unit),
        future: fmt.format(value, unit),
      }) + '\n');
      rows++;
    }
  }
}

gzip.end();
await finished;

process.stderr.write(`Done. ${rows} relative-time rows. ICU ${icuVersion}.\n`);
