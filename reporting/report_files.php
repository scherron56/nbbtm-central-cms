<?php
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

if (!isDeveloper()) {
    http_response_code(403);
    exit('Access denied. Developer privileges required.');
}

$reportDirectory = __DIR__ . '/../' . ($_ENV['REPORTS_TEMPLATE_PATH'] ?? 'reports');
$allowedExtensions = ['jrxml', 'jasper'];
$message = '';
$error = '';

if (isset($_SESSION['report_files_message'])) {
    $message = $_SESSION['report_files_message'];
    unset($_SESSION['report_files_message']);
}
if (isset($_SESSION['report_files_error'])) {
    $error = $_SESSION['report_files_error'];
    unset($_SESSION['report_files_error']);
}

if (!is_dir($reportDirectory) && !mkdir($reportDirectory, 0775, true)) {
    $error = 'The reports directory could not be created.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $file = $_FILES['report_file'] ?? null;
        $fileName = basename($file['name'] ?? '');
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0) {
            $_SESSION['report_files_error'] = 'Select a valid report file.';
        } elseif (!in_array($extension, $allowedExtensions, true)) {
            $_SESSION['report_files_error'] = 'Only .jrxml and .jasper report files may be uploaded.';
        } elseif (!move_uploaded_file($file['tmp_name'], $reportDirectory . '/' . $fileName)) {
            $_SESSION['report_files_error'] = 'The report file could not be saved.';
        } else {
            $_SESSION['report_files_message'] = "Report file '{$fileName}' uploaded successfully.";
        }
    } elseif ($action === 'delete') {
        $fileName = basename($_POST['file_name'] ?? '');
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $filePath = $reportDirectory . '/' . $fileName;

        if (!in_array($extension, $allowedExtensions, true) || !is_file($filePath)) {
            $_SESSION['report_files_error'] = 'The selected report file was not found.';
        } elseif (!unlink($filePath)) {
            $_SESSION['report_files_error'] = 'The report file could not be deleted.';
        } else {
            $_SESSION['report_files_message'] = "Report file '{$fileName}' deleted.";
        }
    }
    header('Location: report_files.php');
    exit;
}

$reportFiles = [];
if (is_dir($reportDirectory)) {
    foreach (scandir($reportDirectory) as $fileName) {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($extension, $allowedExtensions, true) && is_file($reportDirectory . '/' . $fileName)) {
            $reportFiles[] = [
                'name' => $fileName,
                'size' => filesize($reportDirectory . '/' . $fileName),
                'modified' => filemtime($reportDirectory . '/' . $fileName),
            ];
        }
    }
}
usort($reportFiles, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

// Map each saved template file to its configured report_key (if any), so we can
// link directly into the Run Reports page to preview the actual rendered report.
$reportKeysByFile = [];
if (isset($db) && $db instanceof mysqli) {
    $mapQuery = $db->query("SELECT report_key, file_name FROM app_reports WHERE is_active = 1");
    if ($mapQuery) {
        foreach ($mapQuery->fetch_all(MYSQLI_ASSOC) as $row) {
            $reportKeysByFile[$row['file_name']] = $row['report_key'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Saved Reports - Central Management</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include_once __DIR__ . '/../include/header.php'; ?>
<main class="dashboard-container">
  <h2>Saved Report Files</h2>
  <p>Upload, read, or delete Jasper report templates stored in the reports directory.</p>

  <?php if ($message): ?><div class="card" style="color:#166534; margin-bottom:1rem;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="card" style="color:#991b1b; margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <section class="card" style="margin-bottom:1.5rem;">
    <h3>Upload Report</h3>
    <form method="post" action="report_files.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <input type="file" name="report_file" accept=".jrxml,.jasper" required>
      <button type="submit" class="btn-primary">Upload Report</button>
    </form>
  </section>

  <section class="card">
    <h3>Saved Reports</h3>
    <table class="data-table">
      <thead><tr><th>File</th><th>Size</th><th>Modified</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (!$reportFiles): ?>
        <tr><td colspan="4">No saved report files found.</td></tr>
      <?php else: foreach ($reportFiles as $reportFile): ?>
        <tr>
          <td><?= htmlspecialchars($reportFile['name']) ?></td>
          <td><?= number_format($reportFile['size'] / 1024, 1) ?> KB</td>
          <td><?= htmlspecialchars(date('Y-m-d H:i', $reportFile['modified'])) ?></td>
          <td>
            <?php $mappedKey = $reportKeysByFile[$reportFile['name']] ?? null; ?>
            <?php if ($mappedKey !== null): ?>
              <a class="btn btn-sm btn-primary" href="admin_reports.php?report=<?= rawurlencode($mappedKey) ?>" target="_blank">Preview Report</a>
            <?php endif; ?>
            <a class="btn btn-sm btn-secondary" href="view_report_file.php?file=<?= rawurlencode($reportFile['name']) ?>" target="_blank">View XML</a>
            <form method="post" action="report_files.php" class="inline-form" onsubmit="return confirm('Delete this report file?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="file_name" value="<?= htmlspecialchars($reportFile['name']) ?>">
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </section>
</main>
<?php include_once __DIR__ . '/../include/footer.php'; ?>
</body>
</html>
