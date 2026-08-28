<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = rtrim((string)(getenv('APP_ROOT') ?: dirname(__DIR__)), DIRECTORY_SEPARATOR);
$sourceOrigin = 'https://manglefurniture.github.io/Solution-SPA';
$checkOnly = in_array('--check', $argv, true);
$detectOnly = in_array('--detect', $argv, true);
$validateOnly = in_array('--validate-url', $argv, true);

function normalizePublicUrl(string $url): string
{
    $url = rtrim(trim($url), '/');
    if ($url === '') {
        throw new InvalidArgumentException('APP_URL is required');
    }

    $parts = parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!is_array($parts) || !in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
        throw new InvalidArgumentException('APP_URL must be an absolute http(s) URL');
    }
    if (isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
        throw new InvalidArgumentException('APP_URL must be a public origin/path without credentials, query or fragment');
    }

    return $url;
}

if ($detectOnly) {
    $index = file_get_contents($root . DIRECTORY_SEPARATOR . 'index.html');
    if ($index === false || !preg_match('~<link\s+rel="canonical"\s+href="([^"]+)"\s*/?>~i', $index, $match)) {
        fwrite(STDERR, "PUBLIC_ORIGIN_FAIL canonical not found\n");
        exit(1);
    }
    try {
        echo normalizePublicUrl((string)$match[1]) . "\n";
    } catch (InvalidArgumentException $e) {
        fwrite(STDERR, "PUBLIC_ORIGIN_FAIL {$e->getMessage()}\n");
        exit(1);
    }
    exit(0);
}

try {
    $appUrl = normalizePublicUrl((string)getenv('APP_URL'));
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, "PUBLIC_ORIGIN_FAIL {$e->getMessage()}\n");
    exit(1);
}

if ($validateOnly) {
    echo "PUBLIC_ORIGIN_URL_OK {$appUrl}\n";
    exit(0);
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
