<?php
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

if (!isAdmin()) {
    http_response_code(403);
    exit('Access denied.');
}

$reportsOutputPath = __DIR__ . '/../' . ($_ENV['REPORTS_OUTPUT_PATH'] ?? 'reports/output');

$message = '';
$error = '';
if (isset($_SESSION['generated_reports_message'])) {
    $message = $_SESSION['generated_reports_message'];
    unset($_SESSION['generated_reports_message']);
}
if (isset($_SESSION['generated_reports_error'])) {
    $error = $_SESSION['generated_reports_error'];
    unset($_SESSION['generated_reports_error']);
}

if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $fileName = basename($_POST['file_name'] ?? '');
    $outputDirectory = realpath($reportsOutputPath);
    $filePath = $outputDirectory ? $outputDirectory . '/' . $fileName : '';

    if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf' && $outputDirectory && is_file($filePath)) {
        unlink($filePath);
        $_SESSION['generated_reports_message'] = "Generated report '{$fileName}' deleted.";
    } else {
        $_SESSION['generated_reports_error'] = 'The selected generated report was not found.';
    }
    header('Location: generated_reports.php');
    exit;
}

// Map report_key back to a friendly title for display.
$reportTitlesByKey = [];
if (isset($db) && $db instanceof mysqli) {
    $titleQuery = $db->query("SELECT report_key, report_title FROM app_reports");
    if ($titleQuery) {
        foreach ($titleQuery->fetch_all(MYSQLI_ASSOC) as $row) {
            $reportTitlesByKey[$row['report_key']] = $row['report_title'];
        }
    }
}

$outputDirectory = $reportsOutputPath;
$generatedReports = [];
if (is_dir($outputDirectory)) {
    foreach (scandir($outputDirectory) as $fileName) {
        $filePath = $outputDirectory . '/' . $fileName;
        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'pdf' || !is_file($filePath)) {
            continue;
        }

        // Generated files are named "<report_key>_<YYYYmmdd>_<His>.pdf". Parse the
        // report key back out (when it matches that pattern) so we can show a
        // friendly title; otherwise just display the raw filename.
        $reportKey = null;
        $reportTitle = null;
        if (preg_match('/^(.+)_(\d{8})_(\d{6})$/', pathinfo($fileName, PATHINFO_FILENAME), $matches)) {
            $reportKey = $matches[1];
            $reportTitle = $reportTitlesByKey[$reportKey] ?? null;
        }

        $generatedReports[] = [
            'name' => $fileName,
            'title' => $reportTitle,
            'size' => filesize($filePath),
            'modified' => filemtime($filePath),
        ];
    }
}
usort($generatedReports, static fn(array $a, array $b): int => $b['modified'] <=> $a['modified']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generated Reports - Central Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <?php include_once __DIR__ . '/../include/header.php'; ?>
    <main class="dashboard-container">
        <h2>Generated Reports</h2>
        <p>View PDF reports that have already been generated. Reports remain here until deleted.</p>

        <?php if (!empty($message)): ?><div class="card" style="color:#166534; margin-bottom:1rem;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="card" style="color:#991b1b; margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <section class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>File</th>
                        <th>Size</th>
                        <th>Generated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$generatedReports): ?>
                        <tr>
                            <td colspan="5">No generated reports found yet. Run a report from <a href="admin_reports.php">Run Jasper Reports</a> to create one.</td>
                        </tr>
                        <?php else: foreach ($generatedReports as $report): ?>
                            <tr>
                                <td><?= htmlspecialchars($report['title'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($report['name']) ?></td>
                                <td><?= number_format($report['size'] / 1024, 1) ?> KB</td>
                                <td><?= htmlspecialchars(date('Y-m-d H:i', $report['modified'])) ?></td>
                                <td>
                                    <a class="btn btn-sm btn-primary" href="preview_generated_report.php?file=<?= rawurlencode($report['name']) ?>" target="_blank">View</a>
                                    <form method="post" action="generated_reports.php" class="inline-form" onsubmit="return confirm('Delete this generated report?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="file_name" value="<?= htmlspecialchars($report['name']) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </section>
    </main>
    <?php include_once __DIR__ . '/../include/footer.php'; ?>
</body>

</html>