<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Tools\Documentation;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../tools/Documentation.php';

final class DocumentationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/daynum-doc-test-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/docs/en', 0777, true);
        mkdir($this->root . '/docs/fa', 0777, true);
        mkdir($this->root . '/docs/assets', 0777, true);
        $nav = [
            'schemaVersion' => 1,
            'defaultLocale' => 'en',
            'locales' => ['en', 'fa'],
            'entry' => 'overview',
            'sections' => [['id' => 'start', 'title' => ['en' => 'Start', 'fa' => 'شروع'], 'pages' => ['overview']]],
        ];
        file_put_contents($this->root . '/docs/navigation.json', json_encode($nav, JSON_THROW_ON_ERROR));
        foreach (['en', 'fa'] as $lang) {
            file_put_contents($this->root . "/docs/{$lang}/overview.md", "---\ntitle: \"Title\"\ndescription: \"Description\"\n---\n# Title\n\n## A heading\n\n## A heading\n\n## راهنمای فارسی\n");
        }
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->root);
    }

    public function testValidLinksReferencesAssetsAndCodeMasking(): void
    {
        file_put_contents($this->root . '/docs/assets/example (1).svg', '<svg/>');
        $this->append('en', <<<'MD'

[Inline](#a-heading "Title") and [duplicate](#a-heading-1).
[Persian](#راهنمای-فارسی) and [encoded](#%D8%B1%D8%A7%D9%87%D9%86%D9%85%D8%A7%DB%8C-%D9%81%D8%A7%D8%B1%D8%B3%DB%8C).
![image][asset]
[asset]: <../assets/example (1).svg> "Caption"
[reference][page]
[page]: overview.md#a-heading
[page][] and [page].
`[fake](missing.md)`

```text
[also fake](missing.md)
```
MD);
        $this->append('fa', "\n```text\n[also fake](missing.md)\n```\n");
        self::assertSame([], Documentation::check($this->root));
    }

    /** @dataProvider brokenPageProvider */
    public function testBrokenPagesFail(string $addition, string $expected): void
    {
        $this->append('en', $addition);
        self::assertStringContainsString($expected, implode("\n", Documentation::check($this->root)));
    }

    /** @return iterable<string, array{string, string}> */
    public static function brokenPageProvider(): iterable
    {
        yield 'missing file' => ["\n[bad](missing.md)\n", 'Missing/unsafe target'];
        yield 'missing fragment' => ["\n[bad](#absent)\n", 'Missing heading anchor'];
        yield 'missing asset' => ["\n![bad](../assets/missing.png)\n", 'Missing/unsafe target'];
        yield 'undefined reference' => ["\n[bad][unknown]\n", 'Undefined link reference'];
        yield 'extra h1' => ["\n# Second H1\n", 'Expected one body H1'];
        yield 'raw html' => ["\n<div>bad</div>\n", 'Raw HTML/MDX'];
        yield 'unsafe scheme' => ["\n[bad](javascript:alert)\n", 'Use relative local links'];
        yield 'absolute file' => ["\n[bad](/tmp/file.md)\n", 'Use relative local links'];
        yield 'malformed inline link' => ["\n[bad](unterminated\n", 'Unsupported or malformed Markdown link'];
        yield 'unclosed fence' => ["\n```php\n", 'Unclosed code fence'];
    }

    public function testMissingTranslationAndUnlistedPageFail(): void
    {
        unlink($this->root . '/docs/fa/overview.md');
        file_put_contents($this->root . '/docs/en/unlisted.md', '# Unlisted');
        $errors = implode("\n", Documentation::check($this->root));
        self::assertStringContainsString('Missing fa page', $errors);
        self::assertStringContainsString('Unlisted locale page', $errors);
    }

    public function testMetadataAndPersianProseMarksFail(): void
    {
        file_put_contents($this->root . '/docs/en/overview.md', "---\ntitle: \"\"\n---\n# Title\n");
        $this->append('fa', "\nمتن\u{0650}\n");
        $errors = implode("\n", Documentation::check($this->root));
        self::assertStringContainsString('Empty title', $errors);
        self::assertStringContainsString('Missing/invalid description', $errors);
        self::assertStringContainsString('Authored Persian', $errors);
    }

    public function testDuplicateManifestAndEntryFail(): void
    {
        $file = $this->root . '/docs/navigation.json';
        $nav = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        $nav['sections'][] = $nav['sections'][0];
        $nav['entry'] = 'missing';
        file_put_contents($file, json_encode($nav, JSON_THROW_ON_ERROR));
        $errors = implode("\n", Documentation::check($this->root));
        self::assertStringContainsString('duplicate section', $errors);
        self::assertStringContainsString('duplicate page', $errors);
        self::assertStringContainsString('Missing entry', $errors);
    }

    public function testNavigationRejectsUnsupportedSchemaAndUnsafeSlug(): void
    {
        $file = $this->root . '/docs/navigation.json';
        $original = (string) file_get_contents($file);
        $nav = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
        $nav['schemaVersion'] = 2;
        file_put_contents($file, json_encode($nav, JSON_THROW_ON_ERROR));
        self::assertSame(['Unsupported navigation contract'], Documentation::check($this->root));
        $nav['schemaVersion'] = 1;
        $nav['sections'][0]['pages'][] = '../escape';
        file_put_contents($file, json_encode($nav, JSON_THROW_ON_ERROR));
        self::assertStringContainsString('Invalid or duplicate page', implode("\n", Documentation::check($this->root)));
    }

    public function testSymlinksAreRejected(): void
    {
        symlink($this->root . '/docs/en/overview.md', $this->root . '/docs/en/link.md');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Symlink in docs');
        Documentation::check($this->root);
    }

    public function testExampleParityFails(): void
    {
        $this->append('en', "\n```text\nDifferent output\n```\n");
        self::assertStringContainsString('Translation example/output mismatch', implode("\n", Documentation::check($this->root)));
    }

    public function testExamplesExecuteOnlyStandalonePairsAndCheckExactOutput(): void
    {
        $this->append('en', <<<'MD'

```php
throw new RuntimeException('Context must not execute');
```

```php
<?php
echo "verified\n";
```

```text
verified
```
MD);
        $result = Documentation::examples($this->root);
        self::assertSame([], $result['errors']);
        self::assertSame(2, $result['linted']);
        self::assertSame(1, $result['executed']);
        $file = $this->root . '/docs/en/overview.md';
        file_put_contents($file, str_replace("```text\nverified", "```text\nstale", (string) file_get_contents($file)));
        self::assertStringContainsString('Example mismatch', implode("\n", Documentation::examples($this->root)['errors']));
    }

    public function testContextSyntaxAndMissingExpectedOutputFail(): void
    {
        $this->append('en', "\n```php\n\$x = ;\n```\n\n```php\n<?php\necho 'unsafe to run without declared output';\n```\n");
        $errors = implode("\n", Documentation::examples($this->root)['errors']);
        self::assertStringContainsString('Syntax error', $errors);
        self::assertStringContainsString('needs adjacent exact text output', $errors);
    }

    private function append(string $lang, string $text): void
    {
        file_put_contents($this->root . "/docs/{$lang}/overview.md", $text . "\n", FILE_APPEND);
    }
}
