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

function reportJasperClasspath(): string
{
    $fontDirectory = reportPath('REPORTS_FONTS_PATH');
    if (!is_dir($fontDirectory)) {
        throw new RuntimeException("Report fonts directory not found: {$fontDirectory}");
    }

    $starterPath = reportPath('JASPER_STARTER_PATH');
    $starterJar = dirname($starterPath, 2) . '/lib/jasperstarter.jar';
    if (!is_file($starterJar) || !is_readable($starterJar)) {
        throw new RuntimeException("JasperStarter JAR not found or not readable: {$starterJar}");
    }

    $starterDirectory = dirname($starterPath, 2);
    $stylesDirectory = reportPath('REPORTS_STYLE_PATH');
    if (!is_dir($stylesDirectory)) {
        throw new RuntimeException("Report styles directory not found: {$stylesDirectory}");
    }

    // Preload extensions on Java 21 instead of relying on runtime classloader mutation.
    return implode(PATH_SEPARATOR, [
        $starterJar,
        $starterDirectory . '/lib/*',
        $starterDirectory . '/jdbc/*',
        $fontDirectory,
        $fontDirectory . '/*',
        $stylesDirectory,
    ]);
}

function reportJasperStarterPath(): string
{
    $launcher = __DIR__ . '/../reporting/bin/jasperstarter';
    if (!is_file($launcher) || !is_executable($launcher)) {
        throw new RuntimeException("Report launcher not found or not executable: {$launcher}");
    }

    return $launcher;
}
