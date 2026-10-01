<?php

require_once __DIR__ . '/env.php';

function reportPath(string $key): string
{
    $path = trim((string)($_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: ''));
    if ($path === '') {
        throw new RuntimeException("Missing required reporting path: {$key}");
    }

    $isAbsolute = str_starts_with($path, '/')
        || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

    $resolvedPath = rtrim($isAbsolute ? $path : __DIR__ . '/../' . $path, '/\\');
    return realpath($resolvedPath) ?: $resolvedPath;
}
