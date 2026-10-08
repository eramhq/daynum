<?php

declare(strict_types=1);

require __DIR__ . '/Documentation.php';

try {
    $result = \Eram\Daynum\Tools\Documentation::examples(dirname(__DIR__));
    foreach ($result['errors'] as $error) {
        fwrite(STDERR, $error . "\n");
    }
    if ($result['errors'] !== []) {
        exit(1);
    }
    printf("Documentation examples: %d PHP blocks linted, %d exact outputs verified\n", $result['linted'], $result['executed']);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
