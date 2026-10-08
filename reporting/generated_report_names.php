<?php

function generatedReportCustomName($value): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException('The report name must be text.');
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
        throw new InvalidArgumentException('The report name cannot contain control characters.');
    }
    $name = trim($value);
    if ($name === '') {
        return '';
    }

    $name = preg_replace('/\.pdf$/i', '', $name);
    if (preg_match('/^.{1,100}$/us', $name) !== 1 || strlen($name) > 200) {
        throw new InvalidArgumentException('Use a report name of 1-100 characters (up to 200 bytes).');
    }
    if (preg_match('/[<>:"\/\\\\|?*]/', $name) || $name[0] === '.' || preg_match('/[. ]$/', $name)) {
        throw new InvalidArgumentException('The report name cannot contain < > : " / \\ | ? *, start with a period, or end with a period or space.');
    }
    if (preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $name)) {
        throw new InvalidArgumentException('Please choose a report name that is not a reserved device name.');
    }

    return $name;
}

function saveGeneratedReport(string $sourcePath, string $outputDirectory, string $stem, bool $customName): string
{
    $source = fopen($sourcePath, 'rb');
    if ($source === false) {
        throw new RuntimeException('Unable to read the generated PDF.');
    }

    $destination = false;
    $destinationPath = '';
    try {
        for ($suffix = 1; ; $suffix++) {
            $name = $stem . ($suffix === 1 ? '' : ($customName ? ' (' . $suffix . ')' : '_' . $suffix));
            $destinationPath = $outputDirectory . '/' . $name . '.pdf';
            // Exclusive creation prevents concurrent requests from replacing an existing PDF.
            $destination = @fopen($destinationPath, 'xb');
            if ($destination !== false) {
                break;
            }
            if (!file_exists($destinationPath)) {
                throw new RuntimeException('Unable to create the saved PDF.');
            }
        }

        $size = filesize($sourcePath);
        $copied = stream_copy_to_stream($source, $destination);
        if ($size === false || $copied === false || $copied !== $size || !fflush($destination)) {
            throw new RuntimeException('Unable to save the complete generated PDF.');
        }
        return $destinationPath;
    } catch (Throwable $e) {
        if (is_resource($destination)) {
            fclose($destination);
            $destination = false;
            if (!unlink($destinationPath)) {
                error_log('Unable to remove incomplete generated PDF: ' . $destinationPath);
            }
        }
        throw $e;
    } finally {
        fclose($source);
        if (is_resource($destination)) {
            fclose($destination);
        }
    }
}
