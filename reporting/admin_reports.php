<?php
// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// auth.php loads the session and automatically imports config/db.php
require_once __DIR__ . '/../include/auth.php';

$userIsAdmin = isAdmin();
$reports = [];
$dbError = '';
$preselectedReport = $_GET['report'] ?? '';

// Only query database if user is confirmed admin
if ($userIsAdmin) {
    try {
        if (isset($db) && $db instanceof mysqli) {
            $reportsQuery = $db->query("SELECT id, report_key, report_title FROM app_reports WHERE is_active = 1 ORDER BY report_title ASC");
            if ($reportsQuery) {
                $reports = $reportsQuery->fetch_all(MYSQLI_ASSOC);
            }
        } else {
            $dbError = "Database handle (\$db) not available.";
        }
    } catch (mysqli_sql_exception $e) {
        $dbError = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Run Reports - Central Management</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <style>
    .form-wrapper {
      display: flex;
      justify-content: center;
      padding: 2rem 1rem;
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
    label { font-weight: 600; font-size: 0.9rem; color: #1e293b; margin-bottom: 4px; display: block; }
  </style>
</head>
<body>

<?php include_once __DIR__ . '/../include/header.php'; ?>

<main class="dashboard-container">

  <?php if (!$userIsAdmin): ?>
    <div style="background-color: #fee2e2; color: #991b1b; padding: 1.5rem; border-radius: 8px; font-weight: 600; max-width: 600px; margin: 2rem auto; text-align: center;">
      Access Denied: You must be signed in with an Administrator account to run system reports.
    </div>
  <?php else: ?>

    <div class="form-wrapper">
      <form action="generate_report.php" method="POST" target="_blank" style="max-width: 750px; width: 100%;">
        <h2 style="color: #28089a; margin-top: 0;">Run System Report</h2>

        <?php if (!empty($dbError)): ?>
          <div style="background-color: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; font-weight: 600;">
            <?= htmlspecialchars($dbError) ?>
          </div>
        <?php endif; ?>
        
        <div class="field-group" style="--colspan: 12;">
          <label for="report_select">Select Report</label>
          <select id="report_select" name="report" class="form-control" required onchange="loadReportParameters(this.value)">
            <option value="">-- Choose a Report --</option>
            <?php foreach ($reports as $rep): ?>
              <option value="<?= htmlspecialchars($rep['report_key']) ?>" <?= ($rep['report_key'] === $preselectedReport) ? 'selected' : '' ?>>
                <?= htmlspecialchars($rep['report_title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="dynamic-params" class="form-grid-section-12" style="margin-top: 1rem;"></div>

        <div style="margin-top: 1.5rem; display: flex; gap: 1rem; align-items: center;">
          <button type="submit" class="btn-primary" id="btn-submit" disabled>Generate PDF</button>
          <a href="manage_reports.php" class="btn-accent" style="text-decoration: none;">+ Add / Edit Reports</a>
        </div>
      </form>
    </div>

  <?php endif; ?>

</main>

<?php include_once __DIR__ . '/../include/footer.php'; ?>
<script src="js/paramConfig.js"></script>
<script src="js/report-render.js"></script>
<?php if ($preselectedReport !== ''): ?>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('report_select');
    if (select && select.value) {
      loadReportParameters(select.value);
    }
  });
</script>
<?php endif; ?>
</body>
</html>