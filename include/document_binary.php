<?php

function isDocxDocument(string $name, string $mime): bool
{
    $normalizedMime = strtolower(trim($mime));
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    // Legacy .doc files are sometimes uploaded with a .docx filename.
    // Do not send those binary files through the ZIP-based DOCX validator.
    if ($normalizedMime === 'application/msword') {
        return false;
    }

    return $normalizedMime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        || $extension === 'docx';
}

function isPdfDocument(string $name, string $mime): bool
{
    return strtolower($mime) === 'application/pdf'
        || strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pdf';
}

function normalizeDocxData(string $data): string
{
    $zipStart = strpos($data, "PK\x03\x04");
    if ($zipStart === false) {
        throw new RuntimeException('The uploaded file does not contain a DOCX ZIP package.');
    }
    if ($zipStart > 0) {
        $data = substr($data, $zipStart);
    }

    $endOfCentralDirectory = strrpos($data, "PK\x05\x06");
    if ($endOfCentralDirectory === false) {
        throw new RuntimeException('The uploaded file does not contain a DOCX ZIP package.');
    }

    // A few DOCX producers omit the final byte of the 22-byte ZIP footer.
    // Add only that missing padding byte; do not rebuild the package.
    $footerLength = strlen($data) - $endOfCentralDirectory;
    if ($footerLength === 21) {
        $data .= "\0";
    }

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
            throw new RuntimeException('The uploaded DOCX archive could not be opened.');
        }

        $normalized = new ZipArchive();
        if ($normalized->open($normalizedPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $source->close();
            throw new RuntimeException('Unable to prepare the DOCX document for reading.');
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
            throw new RuntimeException('Unable to finalize the DOCX document.');
        }

        $normalizedData = file_get_contents($normalizedPath);
        if ($normalizedData === false) {
            throw new RuntimeException('Unable to read the prepared DOCX document.');
        }
        return $normalizedData;
    } finally {
        unlink($sourcePath);
        unlink($normalizedPath);
    }
}
