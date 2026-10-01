<?php
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/JasperCompiler.php';

if (!isDeveloper()) {
    http_response_code(403);
    exit('Access denied. Developer privileges required.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: compile_report_form.php');
    exit;
}

$reportName = basename(trim($_POST['report_name'] ?? ''));
if ($reportName === '') {
    $_SESSION['compile_report_error'] = 'Select a report to compile.';
    header('Location: compile_report_form.php');
    exit;
}

try {
    $compiler = new JasperCompiler();
    $compiler->setReportName($reportName)->compile();
    $_SESSION['compile_report_message'] = "Report '{$reportName}' compiled successfully.";
} catch (Throwable $e) {
    $_SESSION['compile_report_error'] = 'Compilation failed: ' . $e->getMessage();
}

header('Location: compile_report_form.php');
exit;
