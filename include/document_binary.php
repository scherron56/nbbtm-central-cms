<?php

function isDocxDocument(string $name, string $mime): bool
{
    return strtolower($mime) === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        || strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'docx';
}

function isPdfDocument(string $name, string $mime): bool
{
    return strtolower($mime) === 'application/pdf'
        || strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pdf';
}

function normalizeDocxData(string $data): string
{
    $sourcePath = tempnam(sys_get_temp_dir(), 'docx_source_');
    $normalizedPath = tempnam(sys_get_temp_dir(), 'docx_normalized_');
    if ($sourcePath === false || $normalizedPath === false) {
        throw new RuntimeException('Unable to create temporary document files.');
    }

    try {
        if (file_put_contents($sourcePath, $data) !== strlen($data)) {
            throw new RuntimeException('Unable to prepare document data.');
        }

        $source = new ZipArchive();
        if ($source->open($sourcePath) !== true) {
            throw new RuntimeException('The DOCX archive could not be opened.');
        }

        $normalized = new ZipArchive();
        if ($normalized->open($normalizedPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $source->close();
            throw new RuntimeException('The DOCX archive could not be rebuilt.');
        }

        for ($index = 0; $index < $source->numFiles; $index++) {
            $name = $source->getNameIndex($index);
            $entryData = $source->getFromIndex($index);
            if ($name === false || $entryData === false || !$normalized->addFromString($name, $entryData)) {
                $source->close();
                $normalized->close();
                throw new RuntimeException('The DOCX archive contains an unreadable entry.');
            }
        }

        $source->close();
        if (!$normalized->close()) {
            throw new RuntimeException('The DOCX archive could not be finalized.');
        }

        $result = file_get_contents($normalizedPath);
        if ($result === false) {
            throw new RuntimeException('The normalized document could not be read.');
        }
        // Some browser ZIP readers probe one byte past the EOCD record.
        return $result . "\0";
    } finally {
        if ($sourcePath !== false) {
            unlink($sourcePath);
        }
        if ($normalizedPath !== false) {
            unlink($normalizedPath);
        }
    }
}
