<?php
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../config/report_paths.php';

if (!isAdmin()) {
    http_response_code(403);
    exit('Access denied.');
}

$fileName = basename($_GET['file'] ?? '');
$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$filePath = reportPath('REPORTS_TEMPLATE_PATH') . '/' . $fileName;

if (!in_array($extension, ['jrxml', 'jasper'], true) || !is_file($filePath)) {
    http_response_code(404);
    exit('Report file not found.');
}

if ($extension === 'jrxml') {
    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: inline; filename="' . addcslashes($fileName, "\"\\") . '"');
} else {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addcslashes($fileName, "\"\\") . '"');
}
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
