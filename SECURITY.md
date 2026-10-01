# Security policy

## Supported versions

Daynum is in beta. Security fixes go into the latest `1.0.0-beta` release only.

| Version | Supported |
|---|---|
| 1.0.0-beta (latest) | ✓ |
| older betas | ✗ |

## Reporting a vulnerability

Please **do not** open a public issue for security problems.

Report privately through GitHub instead: go to the repository's **Security** tab and click **Report a vulnerability** (<https://github.com/eramhq/daynum/security/advisories/new>). Include:

- the Daynum version and PHP version,
- a minimal code snippet that reproduces the problem,
- what you expected and what happened.

You should get a first response within a week. Once a fix is ready we publish a release and a GitHub security advisory, crediting you unless you ask us not to.

## What counts

Daynum does date math and formatting; it does no I/O, networking or deserialization of untrusted code. Relevant problems include, for example:

- input to `parseExact()`, `fromArray()` or a format pattern that causes a crash, hang or excessive memory use,
- output that could break out of its context when inserted into HTML or SQL unescaped *and* that differs from what the documentation promises.

Wrong dates are bugs, not vulnerabilities — please report those as normal issues.
