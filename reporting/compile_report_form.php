<?php
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../config/env.php';

if (!isDeveloper()) {
    http_response_code(403);
    exit('Access denied. Developer privileges required.');
}

$message = '';
$error = '';
if (isset($_SESSION['compile_report_message'])) {
    $message = $_SESSION['compile_report_message'];
    unset($_SESSION['compile_report_message']);
}
if (isset($_SESSION['compile_report_error'])) {
    $error = $_SESSION['compile_report_error'];
    unset($_SESSION['compile_report_error']);
}

// List existing .jrxml templates as autocomplete suggestions only; the field
// still accepts any name so newly-added templates can be compiled right away.
$reportDirectory = __DIR__ . '/../' . ($_ENV['REPORTS_TEMPLATE_PATH'] ?? 'reports');
$existingReports = [];
if (is_dir($reportDirectory)) {
    foreach (scandir($reportDirectory) as $fileName) {
        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'jrxml') {
            $existingReports[] = pathinfo($fileName, PATHINFO_FILENAME);
        }
    }
    sort($existingReports, SORT_STRING | SORT_FLAG_CASE);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Compile Jasper Report - Central Management</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .form-control {
      width: 100%;
      max-width: 420px;
      height: 38px;
      padding: 6px 12px;
      font-size: 0.95rem;
      border-radius: 4px;
      border: 1px solid #cbd5e1;
      color: #28089a;
      background: #ffffff;
      outline: none;
    }
    .form-control:focus { border-color: #2563eb; }
    label { font-weight: 600; font-size: 0.9rem; color: #1e293b; margin-bottom: 4px; display: block; }
  </style>
</head>
<body>
<?php include_once __DIR__ . '/../include/header.php'; ?>
<main class="dashboard-container">
  <h2>Compile Jasper Report</h2>
  <p>Enter the name of a saved <code>.jrxml</code> template (with or without the extension) to compile it into a <code>.jasper</code> file.</p>

  <?php if ($message): ?><div class="card" style="color:#166534; margin-bottom:1rem;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="card" style="color:#991b1b; margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <section class="card">
    <form method="post" action="compile_report.php">
      <div style="margin-bottom: 1rem;">
        <label for="report_name">Report Name</label>
        <input type="text" id="report_name" name="report_name" class="form-control" list="existing-reports" placeholder="e.g. contacts_listing" required autofocus>
        <datalist id="existing-reports">
          <?php foreach ($existingReports as $reportFile): ?>
            <option value="<?= htmlspecialchars($reportFile) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <button type="submit" class="btn-primary">Compile Report</button>
      <a href="report_files.php" class="btn-accent" style="text-decoration: none;">View Saved Reports</a>
    </form>
  </section>
</main>
<?php include_once __DIR__ . '/../include/footer.php'; ?>
</body>
</html>
