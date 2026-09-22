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

// basename() strips any directory separators so the report name can't be used
// to traverse outside the configured reports directory.
$reportName = basename(trim($_POST['report_name'] ?? ''));

if ($reportName === '') {
    $_SESSION['compile_report_error'] = 'Enter a report name to compile.';
    header('Location: compile_report_form.php');
    exit;
}

try {
    $compiler = new JasperCompiler();
    $compiler->setReportName($reportName);
    $compiler->compile();

    $_SESSION['compile_report_message'] = "Report '{$reportName}' compiled successfully.";
} catch (Exception $e) {
    $_SESSION['compile_report_error'] = 'Error: ' . $e->getMessage();
}

header('Location: compile_report_form.php');
exit;