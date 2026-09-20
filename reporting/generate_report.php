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

use PHPJasper\PHPJasper;

function buildJasperClasspath(): array
{
    static $classpath = null;

    if ($classpath === null) {
        $classpath = [];

        // Auto-discover every font extension jar dropped into storage/fonts/ so new
        // font families can be added without touching this classpath list.
        $candidateJars = glob(__DIR__ . '/../storage/fonts/*.jar') ?: [];
        $candidateJars[] = __DIR__ . '/../vendor/geekcom/phpjasper/bin/jasperstarter/lib/custom-fonts.jar';

        foreach ($candidateJars as $jar) {
            if (file_exists($jar) && !in_array($jar, $classpath, true)) {
                $classpath[] = $jar;
            }
        }

        $existingClasspath = getenv('CLASSPATH');
        if ($existingClasspath !== false && $existingClasspath !== '') {
            foreach (explode(PATH_SEPARATOR, $existingClasspath) as $jar) {
                if ($jar !== '' && !in_array($jar, $classpath, true)) {
                    $classpath[] = $jar;
                }
            }
        }

        putenv('CLASSPATH=' . implode(PATH_SEPARATOR, $classpath));
    }

    return $classpath;
}

function resolveSubreportDir(string $reportPath): ?string
{
    static $resolved = [];

    $cacheKey = realpath($reportPath) ?: $reportPath;
    if (!isset($resolved[$cacheKey])) {
        $resolved[$cacheKey] = null;

        $candidateDirs = [
            __DIR__ . '/../reports/subreports',
            __DIR__ . '/../reports',
            dirname($reportPath) . '/subreports',
        ];

        foreach ($candidateDirs as $candidate) {
            $realPath = realpath($candidate);
            if ($realPath !== false && is_dir($realPath)) {
                $resolved[$cacheKey] = rtrim($realPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                break;
            }
        }
    }

    return $resolved[$cacheKey];
}

// 1. Set the CLASSPATH before PHPJasper runs Java
buildJasperClasspath();

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
$inputPath = __DIR__ . '/../reports/' . $report['file_name'];
if (!file_exists($inputPath)) {
    http_response_code(404);
    die("Error: Compiled report template '{$report['file_name']}' not found in " . __DIR__ . '/../reports/');
}

// 7. Fetch Parameters
$stmt = $db->prepare("SELECT param_name, param_type, is_required, default_value FROM app_report_parameters WHERE report_id = ?");
$stmt->bind_param('i', $report['id']);
$stmt->execute();
$paramResult = $stmt->get_result();

$jasperParams = [];
while ($param = $paramResult->fetch_assoc()) {
    $pName  = $param['param_name'];
    if ($param['param_type'] === 'bool' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawVal = isset($_POST[$pName]);
    } else {
        $rawVal = $_POST[$pName] ?? $_GET[$pName] ?? $param['default_value'];
    }

    if ((int)$param['is_required'] === 1 && ($rawVal === null || $rawVal === '')) {
        http_response_code(422);
        die("Error: Required parameter '{$pName}' is missing.");
    }

    if ($rawVal !== null && $rawVal !== '') {
        $jasperParams[$pName] = match ($param['param_type']) {
            'int'    => (int)$rawVal,
            'float'  => (float)$rawVal,
            'bool'   => filter_var($rawVal, FILTER_VALIDATE_BOOLEAN),
            default  => (string)$rawVal,
        };
    }
}
$stmt->close();

// Provide shared Jasper parameters once so subreports and assets resolve
// consistently regardless of the current working directory.
$subreportDir = resolveSubreportDir($inputPath);
if ($subreportDir !== null) {
    $jasperParams['SUBREPORT_DIR'] = $subreportDir;
}

// Provide the shared IMAGE_DIR parameter for report templates (e.g. letterhead
// images) that declare it; only pass it when the .jrxml actually defines this
// parameter, since JasperStarter errors on unknown -P parameters.
if (preg_match('/<parameter\s+name="IMAGE_DIR"/', file_get_contents($inputPath))) {
    $jasperParams['IMAGE_DIR'] = realpath(__DIR__ . '/../images') . '/';
}

// 8. Prepare Output Directory
$outputDir = __DIR__ . '/../reports/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

// Give each generated PDF a stable, identifiable name (report key + timestamp)
// so it can be listed and re-viewed later from the Generated Reports page,
// instead of a throwaway uniqid that we immediately delete.
$safeReportKey = preg_replace('/[^A-Za-z0-9_-]/', '_', $reportKey);
$outputName = $safeReportKey . '_' . date('Ymd_His');
$outputPath = $outputDir . '/' . $outputName;

// 9. Force Execution with Java 8
if (file_exists('/opt/java8/bin/java')) {
    putenv("JAVA_HOME=/opt/java8");
    putenv("PATH=/opt/java8/bin:" . getenv('PATH'));
}

// 10. Database Connection Parameters
$options = [
    'format' => ['pdf'],
    'params' => $jasperParams,
    'classpath' => buildJasperClasspath(),
    'db_connection' => [
        'driver'   => 'mysql',
        'username' => USER,
        'password' => PASSWORD,
        'host'     => HOST,
        'database' => DB_NAME,
        'port'     => '3306'
    ]
];


// 11. Run JasperReports and Output PDF
try {
    $jasper = new PHPJasper();
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

    // Keep the generated PDF in reports/output/ so it can be reviewed later
    // from the Generated Reports page, instead of deleting it immediately.
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die("Jasper Execution Exception: " . $e->getMessage());
}