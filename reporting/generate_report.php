<?php
// Suppress deprecation warnings
ini_set('display_errors', 0);

// 1. Verify Admin Authentication. The shared auth helper treats developers as admins.
require_once __DIR__ . '/../include/auth.php';
if (!isAdmin()) {
    http_response_code(403);
    die("Access Denied: You must be an administrator to generate reports.");
}

// 2. Load Database Configuration
if (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
} elseif (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
} else {
    http_response_code(500);
    die("Error: Database configuration file not found.");
}

// 3. Load Composer Autoloader
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    http_response_code(500);
    die("Error: Composer autoloader not found. Run 'composer require geekcom/phpjasper' in the project root.");
}
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/report_paths.php';

use PHPJasper\PHPJasper;
 


// 4. Retrieve Report Key
$reportKey = trim($_POST['report'] ?? $_GET['report'] ?? '');
if ($reportKey === '') {
    http_response_code(400);
    die("Error: No report was specified.");
}

// 5. Fetch Report Details
$stmt = $db->prepare("SELECT id, report_title, file_name, is_active FROM app_reports WHERE report_key = ?");
$stmt->bind_param('s', $reportKey);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report || (int)$report['is_active'] !== 1) {
    http_response_code(404);
    die("Error: Report not found or is currently inactive.");
}

// 6. Verify Template File Exists
$templateDirectory = reportPath('REPORTS_TEMPLATE_PATH');
$inputPath = $templateDirectory . '/' . $report['file_name'];
if (!file_exists($inputPath)) {
    http_response_code(404);
    die("Error: Compiled report template '{$report['file_name']}' not found in {$templateDirectory}.");
}

// 7. Fetch Parameters
$stmt = $db->prepare("SELECT param_name, param_type, control_type, is_required, default_value FROM app_report_parameters WHERE report_id = ?");
$stmt->bind_param('i', $report['id']);
$stmt->execute();
$paramResult = $stmt->get_result();

$jasperParams = [];
while ($param = $paramResult->fetch_assoc()) {
    $pName = $param['param_name'];

    // multi_select controls submit their value as an array via a
    // "paramName[]" field name; everything else is a plain scalar.
    if (($param['control_type'] ?? '') === 'multi_select') {
        $rawVal = $_POST[$pName] ?? $_GET[$pName] ?? null;
        if (!is_array($rawVal) || empty($rawVal)) {
            $rawVal = $param['default_value'];
        } else {
            $selectedValues = [];
            foreach ($rawVal as $value) {
                if (!is_scalar($value)) {
                    http_response_code(422);
                    die("Error: Invalid value for parameter '{$pName}'.");
                }

                if ($param['param_type'] === 'int') {
                    $integerValue = filter_var($value, FILTER_VALIDATE_INT);
                    if ($integerValue === false) {
                        http_response_code(422);
                        die("Error: Parameter '{$pName}' requires integer values.");
                    }
                    $selectedValues[] = $integerValue;
                } else {
                    $selectedValues[] = (string)$value;
                }
            }

            $rawVal = json_encode($selectedValues, JSON_THROW_ON_ERROR);
        }
    } else {
        $rawVal = $_POST[$pName] ?? $_GET[$pName] ?? $param['default_value'];
    }

    if ((int)$param['is_required'] === 1 && ($rawVal === null || $rawVal === '')) {
        http_response_code(422);
        die("Error: Required parameter '{$pName}' is missing.");
    }

    if ($rawVal !== null && $rawVal !== '') {
        $jasperParams[$pName] = match (true) {
            ($param['control_type'] ?? '') === 'multi_select' => (string)$rawVal,
            $param['param_type'] === 'int' => (int)$rawVal,
            $param['param_type'] === 'float' => (float)$rawVal,
            $param['param_type'] === 'bool' => filter_var($rawVal, FILTER_VALIDATE_BOOLEAN),
            default => (string)$rawVal,
        };
    }
}
$stmt->close();

// 8. Prepare Output Directory
$outputDir = reportPath('REPORTS_OUTPUT_PATH');
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

$resourceDirectory = reportPath('REPORTS_STYLE_PATH');
if (!is_dir($resourceDirectory)) {
    http_response_code(500);
    die("Error: Report styles directory not found: {$resourceDirectory}");
}

$uniqueId   = uniqid('rep_', true);
$outputPath = $outputDir . '/' . $uniqueId;

// 9. Configure JasperStarter's Java font extensions.
configureReportFonts();

// 10. Database Connection Parameters
$options = [
    'format' => ['pdf'],
    'params' => $jasperParams, //
    'resources' => $resourceDirectory,
    'db_connection' => [
        'driver'   => 'mysql', //
        'username' => USER,     // from config/db.php
        'password' => PASSWORD, // from config/db.php
        'host'     => HOST,     // from config/db.php
        'database' => DB_NAME,  // from config/db.php
        'port'     => '3306' //
    ]
];


// 11. Run JasperReports and Output PDF
try {
    $jasperStarterPath = reportPath('JASPER_STARTER_PATH');
    $jasper = new PHPJasper(dirname($jasperStarterPath));
    $jasper->process($inputPath, $outputPath, $options)->execute();

    $generatedPdf = $outputPath . '.pdf';

    if (!file_exists($generatedPdf)) {
        http_response_code(500);
        die("Error: Report execution completed but output PDF was not created. Check your MySQL stored procedure.");
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $reportKey . '.pdf"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($generatedPdf));
    header('Accept-Ranges: bytes');

    readfile($generatedPdf);

    unlink($generatedPdf);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die("Jasper Execution Exception: " . $e->getMessage());
}