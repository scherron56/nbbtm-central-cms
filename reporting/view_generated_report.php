<?php
require_once __DIR__ . '/../include/auth.php';

if (!isAdmin()) {
    http_response_code(403);
    exit('Access denied.');
}

$outputDirectory = realpath(__DIR__ . '/../reports/output');
$fileName = basename($_GET['file'] ?? '');
$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$filePath = $outputDirectory ? $outputDirectory . '/' . $fileName : '';

if ($extension !== 'pdf' || !$outputDirectory || !is_file($filePath)) {
    http_response_code(404);
    exit('Generated report not found.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . addcslashes($fileName, "\"\\") . '"');
header('Content-Length: ' . filesize($filePath));
header('Accept-Ranges: bytes');
readfile($filePath);
