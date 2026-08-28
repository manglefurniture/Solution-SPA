<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = rtrim((string)(getenv('APP_ROOT') ?: dirname(__DIR__)), DIRECTORY_SEPARATOR);
$appUrl = rtrim(trim((string)getenv('APP_URL')), '/');
$sourceOrigin = 'https://manglefurniture.github.io/Solution-SPA';
$checkOnly = in_array('--check', $argv, true);

if ($appUrl === '') {
    fwrite(STDERR, "PUBLIC_ORIGIN_FAIL APP_URL is required\n");
    exit(1);
}

$parts = parse_url($appUrl);
if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) {
    fwrite(STDERR, "PUBLIC_ORIGIN_FAIL APP_URL must be an absolute http(s) URL\n");
    exit(1);
}

$files = ['index.html', 'privacy.html', 'robots.txt', 'sitemap.xml'];
foreach ($files as $file) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "PUBLIC_ORIGIN_FAIL cannot read {$file}\n");
        exit(1);
    }

    if (!$checkOnly) {
        $content = str_replace($sourceOrigin, $appUrl, $content);
        if (file_put_contents($path, $content) === false) {
            fwrite(STDERR, "PUBLIC_ORIGIN_FAIL cannot write {$file}\n");
            exit(1);
        }
    }
}

$expected = [
    'index.html' => [
        '<link rel="canonical" href="' . $appUrl . '/" />',
        '<meta property="og:url" content="' . $appUrl . '/" />',
    ],
    'privacy.html' => [
        '<link rel="canonical" href="' . $appUrl . '/privacy.html" />',
    ],
    'robots.txt' => [
        'Sitemap: ' . $appUrl . '/sitemap.xml',
    ],
    'sitemap.xml' => [
        '<loc>' . $appUrl . '/</loc>',
        '<loc>' . $appUrl . '/privacy.html</loc>',
    ],
];

foreach ($expected as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "PUBLIC_ORIGIN_FAIL cannot verify {$file}\n");
        exit(1);
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            fwrite(STDERR, "PUBLIC_ORIGIN_FAIL {$file} does not match APP_URL\n");
            exit(1);
        }
    }
    if ($appUrl !== $sourceOrigin && str_contains($content, $sourceOrigin)) {
        fwrite(STDERR, "PUBLIC_ORIGIN_FAIL {$file} still contains the GitHub Pages origin\n");
        exit(1);
    }
}

echo "PUBLIC_ORIGIN_OK {$appUrl}\n";
