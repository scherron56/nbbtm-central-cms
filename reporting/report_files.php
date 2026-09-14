<?php
require_once __DIR__ . '/include/auth.php';

if (!isAdmin()) {
    http_response_code(403);
    exit('Access denied.');
}

$reportDirectory = __DIR__ . '/reports';
$allowedExtensions = ['jrxml', 'jasper'];
$message = '';
$error = '';

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
            $error = 'Select a valid report file.';
        } elseif (!in_array($extension, $allowedExtensions, true)) {
            $error = 'Only .jrxml and .jasper report files may be uploaded.';
        } elseif (!move_uploaded_file($file['tmp_name'], $reportDirectory . '/' . $fileName)) {
            $error = 'The report file could not be saved.';
        } else {
            $message = "Report file '{$fileName}' uploaded successfully.";
        }
    } elseif ($action === 'delete') {
        $fileName = basename($_POST['file_name'] ?? '');
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $filePath = $reportDirectory . '/' . $fileName;

        if (!in_array($extension, $allowedExtensions, true) || !is_file($filePath)) {
            $error = 'The selected report file was not found.';
        } elseif (!unlink($filePath)) {
            $error = 'The report file could not be deleted.';
        } else {
            $message = "Report file '{$fileName}' deleted.";
        }
    }
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Saved Reports - Central Management</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include_once __DIR__ . '/include/header.php'; ?>
<main class="dashboard-container">
  <h2>Saved Report Files</h2>
  <p>Upload, read, or delete Jasper report templates stored in the reports directory.</p>

  <?php if ($message): ?><div class="card" style="color:#166534; margin-bottom:1rem;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="card" style="color:#991b1b; margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <section class="card" style="margin-bottom:1.5rem;">
    <h3>Upload Report</h3>
    <form method="post" enctype="multipart/form-data">
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
            <a class="btn btn-sm btn-secondary" href="report_file.php?file=<?= rawurlencode($reportFile['name']) ?>" target="_blank">Read</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this report file?');">
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
<?php include_once __DIR__ . '/include/footer.php'; ?>
</body>
</html>
