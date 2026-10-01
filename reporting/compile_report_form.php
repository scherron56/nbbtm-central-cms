<?php
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../config/report_paths.php';

if (!isAdmin()) {
    http_response_code(403);
    exit('Access denied. Admin privileges required.');
}

$message = $_SESSION['compile_report_message'] ?? '';
$error = $_SESSION['compile_report_error'] ?? '';
unset($_SESSION['compile_report_message'], $_SESSION['compile_report_error']);

$reportDirectory = reportPath('REPORTS_TEMPLATE_PATH');
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
  <base href="../">
  <title>Compile Jasper Report - Central Management</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .compile-form {
      max-width: 640px;
    }
    .form-control {
      width: 100%;
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
    label {
      display: block;
      margin-bottom: 4px;
      color: #1e293b;
      font-size: 0.9rem;
      font-weight: 600;
    }
  </style>
</head>
<body>
<?php include_once __DIR__ . '/../include/header.php'; ?>
<main class="dashboard-container">
  <h2>Compile Jasper Report</h2>
  <p>Select a saved <code>.jrxml</code> template to compile it into a <code>.jasper</code> file in the reports directory.</p>

  <?php if ($message): ?>
    <div class="card" style="color:#166534; margin-bottom:1rem;"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="card" style="color:#991b1b; margin-bottom:1rem; white-space:pre-wrap;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <section class="card compile-form">
    <?php if ($existingReports): ?>
      <form method="post" action="reporting/compile_report.php">
        <div style="margin-bottom:1rem;">
          <label for="report_name">JRXML Report Template</label>
          <select id="report_name" name="report_name" class="form-control" required autofocus>
            <option value="">-- Select a report --</option>
            <?php foreach ($existingReports as $reportFile): ?>
              <option value="<?= htmlspecialchars($reportFile) ?>"><?= htmlspecialchars($reportFile) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:0.75rem;">
          <button type="submit" class="btn-primary">Compile Report</button>
          <a href="reporting/report_files.php" class="btn-accent" style="text-decoration:none;">View Saved Reports</a>
          <a href="reporting/manage_reports.php" class="btn-secondary" style="text-decoration:none;">Manage Reports</a>
        </div>
      </form>
    <?php else: ?>
      <p>No JRXML templates were found in the reports directory.</p>
      <a href="reporting/report_files.php" class="btn-primary" style="text-decoration:none;">Upload a Report Template</a>
    <?php endif; ?>
  </section>
</main>
<?php include_once __DIR__ . '/../include/footer.php'; ?>
</body>
</html>
