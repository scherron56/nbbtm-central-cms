<?php
//THIS IS THE REPORT CONTROLLER FOR VBS EXAMPLE CONTROLLER
// report_controller.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Session Security & Authentication
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/vendor/autoload.php'; // PHPJasper Composer Autoload

use PHPJasper\PHPJasper;

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'export_pdf':
        $reportName = $_REQUEST['report_name'] ?? 'session_summary';
        $sessionId  = intval($_REQUEST['vbs_sessions_id'] ?? 0);

        // Path definitions
        $input = __DIR__ . '/reports/' . $reportName . '.jasper';
        $outputFolder = __DIR__ . '/reports/output';
        $outputFileName = $reportName . '_' . time();
        $outputPath = $outputFolder . '/' . $outputFileName;

        // Ensure output directory exists
        if (!file_exists($outputFolder)) {
            mkdir($outputFolder, 0777, true);
        }

        // Database Credentials (pulled from your DB configuration)
        $options = [
            'format' => ['pdf'],
            'params' => [
                'p_session_id' => $sessionId
            ],
            'db_connection' => [
                'driver'   => 'mysql',
                'username' => $db_user ?? 'root',
                'password' => $db_pass ?? '',
                'host'     => $db_host ?? 'localhost',
                'database' => $db_name ?? 'your_database',
                'port'     => '3306'
            ]
        ];

        try {
            $jasper = new PHPJasper();
            
            // Generate command and execute report export
            $jasper->process($input, $outputPath, $options)->execute();

            $pdfFile = $outputPath . '.pdf';

            if (file_exists($pdfFile)) {
                // Stream output directly to browser viewer
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $reportName . '.pdf"');
                header('Content-Length: ' . filesize($pdfFile));
                readfile($pdfFile);

                // Optional: Cleanup generated temp file
                unlink($pdfFile);
                exit;
            } else {
                echo json_encode(["success" => false, "message" => "Report generation failed. Output file not created."]);
            }
        } catch (Exception $e) {
            echo json_encode(["success" => false, "message" => "Jasper Error: " . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(["success" => false, "message" => "Invalid action requested."]);
        break;
}