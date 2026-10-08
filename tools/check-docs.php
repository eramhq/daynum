<?php

declare(strict_types=1);

require __DIR__ . '/Documentation.php';

try {
    $errors = \Eram\Daynum\Tools\Documentation::check(dirname(__DIR__));
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    if ($errors !== []) {
        exit(1);
    }
    echo "Documentation structure, bilingual coverage, links, anchors and assets: OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
