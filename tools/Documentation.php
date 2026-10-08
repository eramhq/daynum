<?php

declare(strict_types=1);

namespace Eram\Daynum\Tools;

use RuntimeException;

/** Small checker for the documented authoring subset, not a general Markdown renderer. */
final class Documentation
{
    /** @return list<string> */
    public static function markdownFiles(string $root): array
    {
        $files = glob($root . '/*.md') ?: [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/docs', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Symlink in docs: ' . $file->getPathname());
            }
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        return $files;
    }

    /** @return list<array{language: string, code: string, offset: int, end: int}> */
    public static function fences(string $text): array
    {
        $blocks = [];
        $open = null;
        $offset = 0;
        foreach (explode("\n", $text) as $line) {
            if ($open === null && preg_match('/^ {0,3}(`{3,}|~{3,})([^\r\n]*)$/', $line, $match)) {
                $open = ['marker' => $match[1], 'language' => trim($match[2]), 'code' => '', 'offset' => $offset];
            } elseif ($open !== null && preg_match('/^ {0,3}' . preg_quote($open['marker'][0], '/') . '{' . strlen($open['marker']) . ',}\s*$/', $line)) {
                $blocks[] = ['language' => $open['language'], 'code' => $open['code'], 'offset' => $open['offset'], 'end' => $offset + strlen($line) + 1];
                $open = null;
            } elseif ($open !== null) {
                $open['code'] .= $line . "\n";
            }
            $offset += strlen($line) + 1;
        }
        if ($open !== null) {
            throw new RuntimeException('Unclosed code fence');
        }
        return $blocks;
    }

    public static function prose(string $text): string
    {
        foreach (array_reverse(self::fences($text)) as $block) {
            $text = substr_replace($text, "\n", $block['offset'], $block['end'] - $block['offset']);
        }
        return $text;
    }

    /**
     * GitHub/remark-style slugs for the plain EN/FA headings used here.
     * @return list<string>
     */
    public static function anchors(string $text): array
    {
        $text = self::prose($text);
        $text = preg_replace('/\A---\n.*?\n---\n/s', '', $text) ?? $text;
        preg_match_all('/^ {0,3}#{1,6}\s+(.+?)(?:\s+#+)?\s*$/mu', $text, $matches);
        $used = [];
        $anchors = [];
        foreach ($matches[1] as $heading) {
            $heading = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $heading) ?? $heading;
            $slug = strtolower($heading);
            $slug = preg_replace('/[^\p{L}\p{M}\p{N}_\- ]/u', '', $slug) ?? $slug;
            $slug = str_replace(' ', '-', $slug);
            $candidate = $slug;
            $suffix = 0;
            while (isset($used[$candidate])) {
                $candidate = $slug . '-' . ++$suffix;
            }
            $used[$candidate] = true;
            $anchors[] = $candidate;
        }
        return $anchors;
    }

    /** @return list<array{url: string, image: bool}> */
    public static function links(string $text): array
    {
        $text = self::prose($text);
        // Mask code spans before looking for Markdown syntax.
        $text = preg_replace('/(`+).*?\1/s', '', $text) ?? $text;
        $destination = '(?:<([^>\n]+)>|([^\s<>]+?))(?:\s+"[^"\n]*"|\s+\x27[^\x27\n]*\x27)?';
        $links = [];
        $definitions = [];
        preg_match_all('/^ {0,3}\[([^\]]+)\]:\s*' . $destination . '\s*$/m', $text, $defs, PREG_SET_ORDER);
        foreach ($defs as $def) {
            $key = self::referenceKey($def[1]);
            if (isset($definitions[$key])) {
                throw new RuntimeException('Duplicate reference definition: ' . $key);
            }
            $definitions[$key] = ($def[2] ?? '') !== '' ? $def[2] : ($def[3] ?? '');
            $links[] = ['url' => $definitions[$key], 'image' => false];
        }
        $text = preg_replace('/^ {0,3}\[[^\]]+\]:[^\n]*$/m', '', $text) ?? $text;
        // Destinations may have balanced parentheses, e.g. paths containing (draft).
        $inline = '/(!?)\[([^\]\n]*)\]\(\s*(<[^>\n]+>|(?:[^\s()]+|\((?:[^()]+|\([^()]*\))*\))+)(?:\s+"[^"\n]*"|\s+\x27[^\x27\n]*\x27)?\s*\)/u';
        $text = preg_replace_callback($inline, static function (array $match) use (&$links): string {
            $links[] = ['url' => trim($match[3], '<>'), 'image' => $match[1] === '!'];
            return '';
        }, $text) ?? $text;
        if (preg_match('/!?\[[^\]\n]*\]\(/', $text)) {
            throw new RuntimeException('Unsupported or malformed Markdown link');
        }
        $text = preg_replace_callback('/(!?)\[([^\]\n]+)\](?:\[([^\]\n]*)\])?/u', static function (array $match) use (&$links, $definitions): string {
            $explicit = isset($match[3]);
            $key = self::referenceKey(($match[3] ?? '') !== '' ? $match[3] : $match[2]);
            if (isset($definitions[$key])) {
                $links[] = ['url' => $definitions[$key], 'image' => $match[1] === '!'];
            } elseif ($explicit) {
                throw new RuntimeException('Undefined link reference: ' . $key);
            }
            return '';
        }, $text) ?? $text;
        return $links;
    }

    private static function referenceKey(string $key): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($key)) ?? $key);
    }

    /** @return list<string> errors; missing translations deliberately fail this bilingual repository gate. */
    public static function check(string $root): array
    {
        $errors = [];
        $root = realpath($root) ?: throw new RuntimeException('Missing repository');
        $nav = json_decode((string) file_get_contents($root . '/docs/navigation.json'), true, flags: JSON_THROW_ON_ERROR);
        if (($nav['schemaVersion'] ?? null) !== 1 || ($nav['defaultLocale'] ?? null) !== 'en' || ($nav['locales'] ?? null) !== ['en', 'fa'] || !is_array($nav['sections'] ?? null) || $nav['sections'] === []) {
            return ['Unsupported navigation contract'];
        }
        $slug = '/^[a-z0-9-]+(?:\/[a-z0-9-]+)*$/D';
        $sections = $pages = [];
        foreach ($nav['sections'] as $section) {
            $id = $section['id'] ?? '';
            if (!is_string($id) || preg_match($slug, $id) !== 1 || isset($sections[$id])) {
                $errors[] = 'Invalid or duplicate section ID';
            }
            if (is_string($id)) {
                $sections[$id] = true;
            }
            foreach (['en', 'fa'] as $lang) {
                if (!is_string($section['title'][$lang] ?? null) || trim($section['title'][$lang]) === '') {
                    $errors[] = 'Missing section title: ' . $lang;
                }
            }
            if (!is_array($section['pages'] ?? null) || $section['pages'] === []) {
                $errors[] = 'Empty/invalid section pages';
                continue;
            }
            foreach ($section['pages'] as $page) {
                if (!is_string($page) || preg_match($slug, $page) !== 1 || isset($pages[$page])) {
                    $errors[] = 'Invalid or duplicate page ID';
                    continue;
                }
                $pages[$page] = true;
            }
        }
        if (!is_string($nav['entry'] ?? null) || !isset($pages[$nav['entry']])) {
            $errors[] = 'Missing entry page';
        }
        if (($nav['entry'] ?? null) !== 'overview') {
            $errors[] = 'Daynum entry must be overview';
        }
        $public = [];
        foreach (array_keys($pages) as $page) {
            foreach (['en', 'fa'] as $lang) {
                $file = $root . "/docs/{$lang}/{$page}.md";
                $public[$file] = true;
                if (!is_file($file)) {
                    $errors[] = "Missing {$lang} page: {$page}";
                }
            }
        }
        foreach (self::markdownFiles($root) as $file) {
            try {
                $text = str_replace("\r\n", "\n", (string) file_get_contents($file));
                $prose = self::prose($text);
                if (isset($public[$file])) {
                    if (!preg_match('/\A---\n(.*?)\n---\n/s', $text, $front)) {
                        $errors[] = "Missing metadata: {$file}";
                    } else {
                        // JSON-quoted single-line strings are a portable YAML subset.
                        foreach (['title', 'description'] as $key) {
                            if (!preg_match('/^' . $key . ': ("[^\n]*")$/m', $front[1], $value)) {
                                $errors[] = "Missing/invalid {$key}: {$file}";
                                continue;
                            }
                            $decoded = json_decode($value[1], true, flags: JSON_THROW_ON_ERROR);
                            if (!is_string($decoded) || trim($decoded) === '') {
                                $errors[] = "Empty {$key}: {$file}";
                            }
                        }
                    }
                    if (preg_match_all('/^#\s+\S.*$/m', $prose) !== 1) {
                        $errors[] = "Expected one body H1: {$file}";
                    }
                    $plain = preg_replace('/(`+).*?\1/s', '', $prose) ?? $prose;
                    if (preg_match('/<\/?[A-Za-z][^>]*>|<!--|^\s*(?:import|export)\s/m', $plain)) {
                        $errors[] = "Raw HTML/MDX is not public Markdown: {$file}";
                    }
                    if (str_contains($file, '/docs/fa/') && preg_match('/[\x{064B}-\x{065F}\x{0670}\x{06C0}]/u', $plain)) {
                        $errors[] = "Authored Persian diacritics/visible ezafe: {$file}";
                    }
                } elseif (preg_match('~/docs/(en|fa)/~', $file) && basename($file) !== 'README.md') {
                    $errors[] = "Unlisted locale page: {$file}";
                }
                foreach (self::links($text) as $link) {
                    $url = $link['url'];
                    if (preg_match('/^(https?:|mailto:)/i', $url)) {
                        continue;
                    }
                    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) || str_starts_with($url, '/')) {
                        $errors[] = "Use relative local links: {$file}: {$url}";
                        continue;
                    }
                    [$path, $anchor] = array_pad(explode('#', $url, 2), 2, '');
                    $target = $path === '' ? $file : dirname($file) . '/' . rawurldecode($path);
                    $resolved = realpath($target);
                    if ($resolved === false || !str_starts_with($resolved, $root . '/')) {
                        $errors[] = "Missing/unsafe target: {$file}: {$url}";
                        continue;
                    }
                    if ($link['image'] && isset($public[$file]) && (!is_file($resolved) || !str_starts_with($resolved, $root . '/docs/assets/'))) {
                        $errors[] = "Public image must be in docs/assets: {$file}: {$url}";
                    }
                    if ($anchor !== '' && str_ends_with($resolved, '.md') && !in_array(rawurldecode($anchor), self::anchors((string) file_get_contents($resolved)), true)) {
                        $errors[] = "Missing heading anchor: {$file}: {$url}";
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = $file . ': ' . $e->getMessage();
            }
        }
        // Enforce byte-identical examples and stated output between translations.
        foreach (array_keys($pages) as $page) {
            $examples = [];
            foreach (['en', 'fa'] as $lang) {
                $file = $root . "/docs/{$lang}/{$page}.md";
                if (is_file($file)) {
                    try {
                        $examples[$lang] = array_map(static fn(array $b): array => [$b['language'], $b['code']], self::fences((string) file_get_contents($file)));
                    } catch (RuntimeException) {
                        // Already reported above.
                    }
                }
            }
            if (isset($examples['en'], $examples['fa']) && $examples['en'] !== $examples['fa']) {
                $errors[] = 'Translation example/output mismatch: ' . $page;
            }
        }
        return $errors;
    }

    /**
     * Run a PHP subprocess without a shell; bound time and capture both streams.
     * @return array{int, string, string}
     */
    public static function php(string $code, string $root, bool $lint): array
    {
        $source = tempnam(sys_get_temp_dir(), 'daynum-doc-');
        $stdout = tempnam(sys_get_temp_dir(), 'daynum-out-');
        $stderr = tempnam(sys_get_temp_dir(), 'daynum-err-');
        if ($source === false || $stdout === false || $stderr === false) {
            throw new RuntimeException('Cannot create temporary files');
        }
        try {
            file_put_contents($source, $code);
            $command = [PHP_BINARY, '-d', 'date.timezone=UTC', '-d', 'display_errors=stderr', '-d', 'error_reporting=-1'];
            if ($lint) {
                $command[] = '-l';
            }
            $command[] = $source;
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']], $pipes, $root);
            if (!is_resource($process)) {
                throw new RuntimeException('Cannot start PHP');
            }
            fclose($pipes[0]);
            $deadline = microtime(true) + 10;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (microtime(true) > $deadline) {
                    proc_terminate($process, 9);
                    proc_close($process);
                    throw new RuntimeException('PHP example exceeded 10 seconds');
                }
                usleep(10000);
            } while (true);
            $exit = $status['exitcode'];
            proc_close($process);
            return [$exit, (string) file_get_contents($stdout), (string) file_get_contents($stderr)];
        } finally {
            unlink($source);
            unlink($stdout);
            unlink($stderr);
        }
    }

    /** @return array{errors: list<string>, linted: int, executed: int} */
    public static function examples(string $root): array
    {
        $errors = [];
        $linted = $executed = 0;
        foreach (self::markdownFiles($root) as $file) {
            if (!str_contains($file, '/docs/') && !in_array(basename($file), ['README.md', 'README.fa.md'], true)) {
                continue;
            }
            $text = (string) file_get_contents($file);
            $blocks = self::fences($text);
            foreach ($blocks as $index => $block) {
                if ($block['language'] !== 'php') {
                    continue;
                }
                $label = $file . ':' . (substr_count(substr($text, 0, $block['offset']), "\n") + 1);
                $standalone = str_starts_with($block['code'], '<?php');
                $code = $standalone ? $block['code'] : "<?php\n" . $block['code'];
                [$exit, $out, $err] = self::php($code, $root, true);
                $linted++;
                if ($exit !== 0) {
                    $errors[] = "Syntax error at {$label}: {$out}{$err}";
                    continue;
                }
                if (!$standalone) {
                    continue; // Host-dependent snippets are never executed.
                }
                $next = $blocks[$index + 1] ?? null;
                if ($next === null || $next['language'] !== 'text' || trim(substr($text, $block['end'], $next['offset'] - $block['end'])) !== '') {
                    $errors[] = "Standalone PHP needs adjacent exact text output: {$label}";
                    continue;
                }
                [$exit, $out, $err] = self::php($code, $root, false);
                $executed++;
                if ($exit !== 0 || $err !== '' || $out !== $next['code']) {
                    $errors[] = "Example mismatch at {$label}: exit={$exit}\nExpected: " . $next['code'] . "Actual: {$out}\nStderr: {$err}";
                }
            }
        }
        return ['errors' => $errors, 'linted' => $linted, 'executed' => $executed];
    }
}
