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

// 3b. Load .env Configuration (Jasper report paths)
require_once __DIR__ . '/../config/env.php';

use PHPJasper\PHPJasper;

// Resolve the Jasper report directories from .env, falling back to the
// historical defaults so the page keeps working if a path is not set.
$reportsTemplatePath = __DIR__ . '/../' . ($_ENV['REPORTS_TEMPLATE_PATH'] ?? 'reports');
$reportsSubreportPath = __DIR__ . '/../' . ($_ENV['REPORTS_SUBREPORT_PATH'] ?? 'reports/subreports');
$reportsOutputPath = __DIR__ . '/../' . ($_ENV['REPORTS_OUTPUT_PATH'] ?? 'reports/output');
$reportsFontsPath = __DIR__ . '/../' . ($_ENV['REPORTS_FONTS_PATH'] ?? 'storage/fonts');
$reportsStylePath = __DIR__ . '/../' . ($_ENV['REPORTS_STYLE_PATH'] ?? 'reports/styles');

// PHPJasper/JasperStarter cannot load extension jars via the CLASSPATH
// environment variable: the jasperstarter wrapper launches Java with
// `java -jar jasperstarter.jar`, and the JVM silently ignores CLASSPATH
// whenever -jar is used. PHPJasper also never wires a "classpath" option
// through to the CLI. The only supported way to add extra font jars is
// JasperStarter's own `-r <resource>` flag (PHPJasper's "resources"
// option), which accepts a single directory or jar file. Since Jaspersoft
// Studio exports one font-extension jar per family into storage/fonts/,
// this merges all of them into one directory once (re-merging only when
// the jar files change) so they can all be supplied through that single
// -r argument.
function buildJasperFontResourceDir(): ?string
{
    global $reportsFontsPath;

    static $resourceDir = null;
    static $resolved = false;

    if ($resolved) {
        return $resourceDir;
    }
    $resolved = true;

    $fontJars = glob($reportsFontsPath . '/*.jar') ?: [];
    if (empty($fontJars)) {
        return null;
    }

    // Build a signature from each jar's name/size/mtime so the merged
    // directory is only rebuilt when a font jar is added, removed, or
    // replaced.
    $signatureParts = [];
    foreach ($fontJars as $jar) {
        $signatureParts[] = basename($jar) . ':' . filemtime($jar) . ':' . filesize($jar);
    }
    sort($signatureParts);
    $signature = md5(implode('|', $signatureParts));

    $mergedDir = rtrim($reportsFontsPath, '/\\') . '/_merged';
    $signatureFile = $mergedDir . '/.signature';

    $needsRebuild = true;
    if (is_dir($mergedDir) && file_exists($signatureFile)) {
        $needsRebuild = trim(file_get_contents($signatureFile)) !== $signature;
    }

    if ($needsRebuild) {
        // Wipe out any previous merge before rebuilding it.
        if (is_dir($mergedDir)) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($mergedDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
        } else {
            mkdir($mergedDir, 0775, true);
        }

        $propertyLines = [];
        foreach ($fontJars as $jar) {
            $zip = new ZipArchive();
            if ($zip->open($jar) !== true) {
                continue;
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                // The extension properties files are merged separately below;
                // everything else (fonts/<family>/...) is extracted as-is.
                if ($name === 'jasperreports_extension.properties' || str_starts_with($name, 'META-INF/')) {
                    if ($name === 'jasperreports_extension.properties') {
                        $contents = $zip->getFromIndex($i);
                        foreach (preg_split('/\r\n|\r|\n/', (string) $contents) as $line) {
                            $line = trim($line);
                            if ($line !== '' && !in_array($line, $propertyLines, true)) {
                                $propertyLines[] = $line;
                            }
                        }
                    }
                    continue;
                }
                $zip->extractTo($mergedDir, $name);
            }
            $zip->close();
        }

        file_put_contents($mergedDir . '/jasperreports_extension.properties', implode("\n", $propertyLines) . "\n");
        file_put_contents($signatureFile, $signature);
    }

    $resourceDir = $mergedDir;

    return $resourceDir;
}

function resolveSubreportDir(string $reportPath): ?string
{
    global $reportsSubreportPath, $reportsTemplatePath;

    static $resolved = [];

    $cacheKey = realpath($reportPath) ?: $reportPath;
    if (!isset($resolved[$cacheKey])) {
        $resolved[$cacheKey] = null;

        $candidateDirs = [
            $reportsSubreportPath,
            $reportsTemplatePath,
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
$inputPath = $reportsTemplatePath . '/' . $report['file_name'];
if (!file_exists($inputPath)) {
    http_response_code(404);
    die("Error: Compiled report template '{$report['file_name']}' not found in " . $reportsTemplatePath . '/');
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

// Detect declared parameters from the report's .jrxml *source*, not the
// compiled .jasper file. Compiled .jasper files are serialized Java objects
// (binary), so a text regex against them never matches, which silently
// dropped IMAGE_DIR/path even when the .jrxml declared them. The .jrxml
// source always sits alongside the compiled report with the same base name.
$jrxmlPath = preg_replace('/\.jasper$/', '.jrxml', $inputPath);
$reportSource = file_exists($jrxmlPath) ? file_get_contents($jrxmlPath) : file_get_contents($inputPath);

// Provide the shared IMAGE_DIR parameter for report templates (e.g. letterhead
// images) that declare it; only pass it when the .jrxml actually defines this
// parameter, since JasperStarter errors on unknown -P parameters.
if (preg_match('/<parameter\s+name="IMAGE_DIR"/', $reportSource)) {
    $jasperParams['IMAGE_DIR'] = realpath(__DIR__ . '/../images') . '/';
}

// Provide the shared "path" parameter used by report style templates
// (e.g. <template>$P{path} + "styles.jrtx"</template>) so .jrtx files resolve
// against the dedicated reports/styles directory. This always overrides any
// request-supplied value so the resolved path stays consistent regardless of
// the current working directory.
if (preg_match('/<parameter\s+name="path"/', $reportSource)) {
    $jasperParams['path'] = rtrim(realpath($reportsStylePath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
}

// 8. Prepare Output Directory
$outputDir = $reportsOutputPath;
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
    'resources' => buildJasperFontResourceDir(),
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