<?php
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php'; // Pulls in HOST, DB_NAME, USER, PASSWORD constants

use PHPJasper\PHPJasper;

// 1. Get dynamic parameters from the GET/POST request
$reportName = $_GET['report'] ?? null;             // e.g., 'sales_summary'
$deptId     = $_GET['dept_id'] ?? null;            // e.g., 101
$startDate  = $_GET['start_date'] ?? null;         // e.g., '2026-01-01'

// 2. Validate input and ensure the report file exists
$allowedReports = ['sales_summary', 'employee_list', 'branch_audit'];

if (!in_array($reportName, $allowedReports)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid report requested.']);
    exit;
}

$inputPath = __DIR__ . "/reports/{$reportName}.jasper";
if (!file_exists($inputPath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Report template not found.']);
    exit;
}

// 3. Create a unique output file path
$uniqueId   = uniqid('report_', true);
$outputPath = __DIR__ . "/reports/output/{$uniqueId}";

// 4. Map parameters and DB connection settings from db.php
$options = [
    'format' => ['pdf'],
    'params' => [
        'p_department_id' => (int)$deptId,
        'p_start_date'    => (string)$startDate
    ],
    'db_connection' => [
        'driver'   => 'mysql',
        'username' => USER,
        'password' => PASSWORD,
        'host'     => HOST,
        'database' => DB_NAME,
        'port'     => '3306'
    ]
];

// 5. Generate and stream the PDF
try {
    $jasper = new PHPJasper();
    $jasper->process($inputPath, $outputPath, $options)->execute();

    $generatedPdf = $outputPath . '.pdf';

    if (!file_exists($generatedPdf)) {
        throw new Exception("PDF generation failed.");
    }

    // Stream PDF to browser
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $reportName . '.pdf"');
    header('Content-Length: ' . filesize($generatedPdf));
    readfile($generatedPdf);

    // Clean up temporary file
    unlink($generatedPdf);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Report generation failed: ' . $e->getMessage()
    ]);
    exit;
}