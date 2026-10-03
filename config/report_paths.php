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

function configureReportFonts(): void
{
    $fontDirectory = reportPath('REPORTS_FONTS_PATH');
    if (!is_dir($fontDirectory)) {
        throw new RuntimeException("Report fonts directory not found: {$fontDirectory}");
    }

    if (is_file('/opt/java8/bin/java')) {
        putenv('JAVA_HOME=/opt/java8');
        putenv('PATH=/opt/java8/bin:' . getenv('PATH'));
    }

    $extensionDirectories = [$fontDirectory];
    $javaHome = getenv('JAVA_HOME');
    if ($javaHome !== false && $javaHome !== '') {
        foreach ([$javaHome . '/jre/lib/ext', $javaHome . '/lib/ext'] as $directory) {
            if (is_dir($directory)) {
                $extensionDirectories[] = $directory;
            }
        }
    }

    $existingOptions = getenv('JAVA_TOOL_OPTIONS');
    $fontOption = '-Djava.ext.dirs=' . implode(PATH_SEPARATOR, array_unique($extensionDirectories));
    putenv('JAVA_TOOL_OPTIONS=' . trim(($existingOptions ?: '') . ' ' . $fontOption));
}
