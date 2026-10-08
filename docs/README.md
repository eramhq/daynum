# Documentation maintenance and publishing

This file is a maintainer guide, outside public navigation. Start the public guides at [English](en/overview.md) or [فارسی](fa/overview.md). [navigation.json](navigation.json) is the authoritative list and order of public pages.

## Structure and authoring

- Use schemaVersion 1, `defaultLocale: "en"`, `locales: ["en", "fa"]`, and `entry: "overview"`. Section IDs and page IDs must be unique lowercase English paths; page IDs omit `.md`. Sections have nonempty English and Persian titles.
- Keep matching paths under `en/` and `fa/`. The website imports only navigation-listed pages. Locale `README.md` files preserve existing index paths and remain outside navigation; the root READMEs remain brief entry points. Add maintainer notes here, not in public navigation.
- Every public page has single-line JSON-quoted YAML `title` and `description`, followed by exactly one body H1. The website removes that H1 before rendering its own title. Link to lower headings or the page itself, not its H1 fragment. Use ATX headings (`## Heading`), plain Markdown and relative links. No raw HTML, MDX, scripts, embedded components or custom HTML anchors.
- Keep existing paths and useful headings where possible. English cookbook headings used by the website's historical link mappings are retained. Substantially reorganized sections may have new anchors; check links when editing. The importer maps legacy README links to the entry and may discard their fragments.
- Shared images belong under `docs/assets/`. From a top-level locale page use `../assets/name.png`; from `calendars/`, use `../../assets/name.png`. No images are needed yet; `.gitkeep` reserves the folder.
- Use inline Markdown links or reference links with optional quoted titles. The local checker supports angle-wrapped destinations, balanced parentheses in inline paths, reference definitions, percent-encoded paths/fragments, and duplicate heading suffixes. Prefer simple headings and links. It is a checker for this authoring subset, not a complete CommonMark renderer; website preview remains the rendering check. Remote URLs are not fetched by CI.
- Persian prose should be easy for developers to read: familiar words, useful English terms, correct half-spaces, no authored vowel marks or visible ezafe. Preserve code, identifiers and exact library output even if output contains combining marks. Update both languages together. The checker compares code fences and outputs byte-for-byte; prose equivalence still needs review.

## Examples

A standalone executable example is a `php` fence starting with `<?php`, loading `vendor/autoload.php`, immediately followed by a `text` fence with exact stdout. Examples run from the repository root with UTC as PHP's default timezone, in separate PHP processes, with a ten-second limit and full error reporting. Explicit zone examples use PHP's installed timezone database. Include deterministic inputs and explicit checks/printed results; do not rely on PHP assertions being enabled.

A contextual PHP snippet omits `<?php` and explains its host requirements in the surrounding prose. It is syntax-checked, never executed. Use inline code or `text` fences for signatures that are not valid PHP programs. Do not mark database writes, package removal, network requests or other destructive examples as executable. The runner executes only reviewed standalone PHP/output pairs; it is not a security sandbox and never runs shell/SQL fences.

## Local checks and CI

```sh
composer docs:check
composer docs:examples
composer test
composer phpstan
composer cs
```

`tools/check-docs.php` validates the manifest, both-language coverage, page metadata, one H1, plain Markdown, Persian prose marks, relative file links, heading anchors, referenced assets, and code/output parity. It scans root Markdown and all `docs/` Markdown; unlisted locale pages fail except the compatibility READMEs. Missing Persian pages fail this repository's completeness gate even though the website supports English fallback. Symlinks in docs or targets escaping the repository fail. Assets must exist even when they are not public pages.

`tools/check-doc-examples.php` syntax-checks PHP fences in both locale trees, maintainer docs and the root READMEs, then executes standalone/output pairs. Contextual fragments are not run. It compares exit status, stderr and exact stdout, and cleans temporary files. `tests/Unit/DocumentationTest.php` exercises checker failure cases using temporary fixtures. CI runs both Composer documentation commands alongside the existing PHP 8.1–8.5 test/static-analysis jobs; existing style, coverage, mutation and oracle jobs stay in place.

## Release status and evidence

These guides accompany prerelease `v1.0.0-beta.4`, dated 2026-10-08. The previous published release, `v1.0.0-beta.3` (2026-10-01), resolves to `aad2084cbc7f4f97db4509f04e82a29ce66b711e`; its annotated tag was checked through the GitHub API. The documentation review began at `ebb05a3`. The parsing guide identifies behavior first included in beta.4: repeated AM/PM/offset conflict rejection, ignored Arabic vowel marks, and C-locale-independent ASCII case folding. The guide examples were checked against this release source; beta.3-compatible examples were also checked against the previous tag.

Before releasing, update the status in both overviews, installation guides and root READMEs to match the intended published version. Move an “unreleased” label only when that behavior is included in the release being described. Review the changelog and run examples against that source. Do not edit old release claims just because a branch has changed.

Corrections made in this documentation pass include morilog v3 immutability, Jalali attribution, bounded civil Hijri, immediate Umm al-Qura month/year failures, supported parsing/formatting tokens, locale/digit separation, and the scope of ICU tests. Source comments still contain the old Jalali attribution and some overbroad range wording; the guides describe the implementation and source evidence rather than treating comments as authoritative. Updating those comments can be reviewed separately without changing algorithms.

## Website contract and preview

The website contract lives in the sibling `eramdev-website/site/docs-publishing.md`; its `scripts/docs-source.mjs` and `scripts/remark-doc-links.mjs` define collection and rewriting. This repository does not modify or run publication automation in that repository.

For a separate website preview session, from that website's `site/` directory, a maintainer can run:

```sh
npm run docs:local -- /absolute/path/to/daynum --serve
```

That command writes the website's ignored local-preview area and labels it unpublished; it is not a public release source. It was not run as part of this Daynum-only change. The exported `collectDocs()` function can also be called read-only to validate these sources without writing a website snapshot.

The public website imports only published GitHub releases, including clearly labeled prereleases. It resolves the release tag to its exact commit (peeling annotated tags), not a branch HEAD, working tree, draft release or unpublished tag. The website's reviewed snapshot and lock pin that commit; normal production builds do not refresh releases. A new library release and a separately reviewed website sync are required to publish documentation corrections.

If a Persian page is absent, the website serves the English page with a translation notice while retaining Persian navigation. The language switch preserves the page ID. Relative guide links become website routes; links to source, changelog and other unlisted files point to the same release commit on GitHub. Images are pinned to that commit, or served from the local asset snapshot during preview.

All navigation-listed Markdown and referenced assets must be committed in the source that will be tagged. Git source availability is required even if a future packaging policy excludes docs from Composer or GitHub source archives. Currently neither Composer archive exclusions nor `.gitattributes` exclude `docs/`; preserve that availability. These checks never commit, push, tag or publish a release.
