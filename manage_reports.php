<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/include/auth.php';

$message = '';
$error = '';

$userIsAdmin = isAdmin();

if ($userIsAdmin) {
    // 1. Handle Report Deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_report') {
        $reportId = (int)($_POST['report_id'] ?? 0);
        if ($reportId > 0) {
            $stmt = $db->prepare("DELETE FROM app_reports WHERE id = ?");
            $stmt->bind_param('i', $reportId);
            if ($stmt->execute()) {
                $message = "Report configuration removed.";
            } else {
                $error = "Failed to delete report: " . $db->error;
            }
            $stmt->close();
        }
    }

    // 2. Handle New Report Registration OR Edit Update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action      = $_POST['action'];
        $reportId    = (int)($_POST['report_id'] ?? 0);
        $reportKey   = trim($_POST['report_key'] ?? '');
        $reportTitle = trim($_POST['report_title'] ?? '');
        $fileName    = trim($_POST['file_name'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $rawParams   = $_POST['params'] ?? [];

        if ($reportKey && $reportTitle && $fileName) {
            $db->begin_transaction();
            try {
                if ($action === 'create_report') {
                    $stmt = $db->prepare("INSERT INTO app_reports (report_key, report_title, file_name, is_active) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('sssi', $reportKey, $reportTitle, $fileName, $isActive);
                    $stmt->execute();
                    $reportId = $stmt->insert_id;
                    $stmt->close();
                    $message = "Report '{$reportTitle}' registered successfully!";
                } elseif ($action === 'update_report' && $reportId > 0) {
                    $stmt = $db->prepare("UPDATE app_reports SET report_key = ?, report_title = ?, file_name = ?, is_active = ? WHERE id = ?");
                    $stmt->bind_param('sssii', $reportKey, $reportTitle, $fileName, $isActive, $reportId);
                    $stmt->execute();
                    $stmt->close();

                    // Delete existing parameters before re-inserting updated parameters
                    $delPStmt = $db->prepare("DELETE FROM app_report_parameters WHERE report_id = ?");
                    $delPStmt->bind_param('i', $reportId);
                    $delPStmt->execute();
                    $delPStmt->close();

                    $message = "Report '{$reportTitle}' updated successfully!";
                }

                // Insert / Re-insert parameters
                if (!empty($rawParams) && is_array($rawParams)) {
                    $pStmt = $db->prepare("INSERT INTO app_report_parameters (report_id, param_name, param_type, is_required, default_value) VALUES (?, ?, ?, ?, ?)");
                    foreach ($rawParams as $param) {
                        $pName = trim($param['name'] ?? '');
                        if ($pName === '') continue;

                        $pType = in_array($param['type'] ?? '', ['string', 'int', 'float', 'bool', 'date']) ? $param['type'] : 'string';
                        $pReq  = isset($param['required']) ? 1 : 0;
                        $pDef  = trim($param['default'] ?? '') !== '' ? trim($param['default']) : null;

                        $pStmt->bind_param('issis', $reportId, $pName, $pType, $pReq, $pDef);
                        $pStmt->execute();
                    }
                    $pStmt->close();
                }

                $db->commit();
            } catch (Exception $e) {
                $db->rollback();
                $error = "Error saving report: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all required fields (Title, Key, and Jasper File).";
        }
    }
}

// 3. Check for edit mode
$editReport = null;
$editParams = [];
if ($userIsAdmin && isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $db->prepare("SELECT * FROM app_reports WHERE id = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $editReport = $res->fetch_assoc()) {
        $stmt->close();
        $pStmt = $db->prepare("SELECT * FROM app_report_parameters WHERE report_id = ? ORDER BY id ASC");
        $pStmt->bind_param('i', $editId);
        $pStmt->execute();
        $editParams = $pStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $pStmt->close();
    }
}

// 4. Fetch all reports
$allReports = [];
try {
    $reportsQuery = $db->query("
        SELECT r.*, COUNT(p.id) AS param_count 
        FROM app_reports r 
        LEFT JOIN app_report_parameters p ON r.id = p.report_id 
        GROUP BY r.id 
        ORDER BY r.created_at DESC
    ");
    if ($reportsQuery) {
        $allReports = $reportsQuery->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Reports - Central Management</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <style>
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
    .param-row {
      display: grid;
      grid-template-columns: 2fr 1.5fr 0.8fr 1.5fr auto;
      gap: 0.5rem;
      align-items: center;
      margin-bottom: 0.5rem;
    }
    .del-btn {
      background: none;
      border: none;
      color: #dc2626;
      font-weight: bold;
      cursor: pointer;
      font-size: 1rem;
      padding: 4px 8px;
    }
    .del-btn:hover { color: #991b1b; }
    .edit-btn {
      text-decoration: none;
      color: #2563eb;
      font-weight: bold;
      font-size: 0.9rem;
      margin-right: 8px;
    }
    .edit-btn:hover { text-decoration: underline; }
  </style>
</head>
<body>

<?php include_once __DIR__ . '/include/header.php'; ?>

<main class="dashboard-container">

  <?php if (!$userIsAdmin): ?>
    <div style="background-color: #fee2e2; color: #991b1b; padding: 1.5rem; border-radius: 8px; font-weight: 600;">
      Access Denied: You must be signed in with an Administrator account to configure reports.
    </div>
  <?php else: ?>

    <?php if ($message): ?>
      <div style="background-color: #dcfce7; color: #166534; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 600;">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div style="background-color: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 600;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="dashboard-layout">
      <!-- Add/Edit Report Form Section -->
      <section class="main-content">
        <form action="manage_reports.php" method="POST" style="max-width: 100%;">
          <input type="hidden" name="action" value="<?= $editReport ? 'update_report' : 'create_report' ?>">
          <?php if ($editReport): ?>
            <input type="hidden" name="report_id" value="<?= (int)$editReport['id'] ?>">
          <?php endif; ?>

          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="color: #28089a; margin: 0;">
              <?= $editReport ? 'Edit Report Configuration' : 'Add Report Configuration' ?>
            </h3>
            <?php if ($editReport): ?>
              <a href="manage_reports.php" style="color: #64748b; font-size: 0.85rem; text-decoration: none;">+ Add New Instead</a>
            <?php endif; ?>
          </div>
          
          <div class="form-grid-section-12">
            <div class="field-group" style="--colspan: 6;">
              <label for="report_title">Report Title <span class="text-danger">*</span></label>
              <input type="text" id="report_title" name="report_title" class="form-control" value="<?= htmlspecialchars($editReport['report_title'] ?? '') ?>" placeholder="e.g. Member Attendance" required>
            </div>
            
            <div class="field-group" style="--colspan: 6;">
              <label for="report_key">Report Key (Slug) <span class="text-danger">*</span></label>
              <input type="text" id="report_key" name="report_key" class="form-control" value="<?= htmlspecialchars($editReport['report_key'] ?? '') ?>" placeholder="e.g. member_attendance" required>
            </div>

            <div class="field-group" style="--colspan: 8;">
              <label for="file_name">Jasper File Name <span class="text-danger">*</span></label>
              <input type="text" id="file_name" name="file_name" class="form-control" value="<?= htmlspecialchars($editReport['file_name'] ?? '') ?>" placeholder="e.g. member_attendance.jasper" required>
            </div>

            <div class="field-group" style="--colspan: 4; display: flex; align-items: center; gap: 0.5rem; margin-top: 1.5rem;">
              <input type="checkbox" id="is_active" name="is_active" value="1" <?= ($editReport['is_active'] ?? 1) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
              <label for="is_active" style="margin: 0; cursor: pointer;">Active for Run</label>
            </div>
          </div>

          <!-- Procedure Parameters -->
          <h4 style="color: #28089a; margin: 1.5rem 0 0.5rem 0;">Procedure Parameters</h4>
          <div id="param-list">
            <?php if (!empty($editParams)): ?>
              <?php foreach ($editParams as $idx => $param): ?>
                <div class="param-row">
                  <input type="text" name="params[<?= $idx ?>][name]" class="form-control" value="<?= htmlspecialchars($param['param_name']) ?>" placeholder="Param Name" required>
                  <select name="params[<?= $idx ?>][type]" class="form-control">
                    <option value="string" <?= $param['param_type'] === 'string' ? 'selected' : '' ?>>String / Text</option>
                    <option value="int" <?= $param['param_type'] === 'int' ? 'selected' : '' ?>>Integer</option>
                    <option value="float" <?= $param['param_type'] === 'float' ? 'selected' : '' ?>>Decimal / Float</option>
                    <option value="date" <?= $param['param_type'] === 'date' ? 'selected' : '' ?>>Date</option>
                    <option value="bool" <?= $param['param_type'] === 'bool' ? 'selected' : '' ?>>Boolean</option>
                  </select>
                  <label style="display: flex; align-items: center; gap: 4px; font-size: 0.8rem; margin: 0;">
                    <input type="checkbox" name="params[<?= $idx ?>][required]" value="1" <?= $param['is_required'] ? 'checked' : '' ?>> Req
                  </label>
                  <input type="text" name="params[<?= $idx ?>][default]" class="form-control" value="<?= htmlspecialchars($param['default_value'] ?? '') ?>" placeholder="Default Val">
                  <button type="button" class="del-btn" onclick="removeParamRow(this)">✕</button>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="param-row">
                <input type="text" name="params[0][name]" class="form-control" placeholder="Param Name (e.g. p_dept_id)">
                <select name="params[0][type]" class="form-control">
                  <option value="string">String / Text</option>
                  <option value="int">Integer</option>
                  <option value="float">Decimal / Float</option>
                  <option value="date">Date</option>
                  <option value="bool">Boolean</option>
                </select>
                <label style="display: flex; align-items: center; gap: 4px; font-size: 0.8rem; margin: 0;">
                  <input type="checkbox" name="params[0][required]" value="1"> Req
                </label>
                <input type="text" name="params[0][default]" class="form-control" placeholder="Default Val">
                <button type="button" class="del-btn" onclick="removeParamRow(this)">✕</button>
              </div>
            <?php endif; ?>
          </div>

          <button type="button" class="btn-secondary" onclick="addParamRow()" style="margin-top: 0.5rem; width: fit-content;">+ Add Parameter</button>

          <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn-primary"><?= $editReport ? 'Update Report Configuration' : 'Save Report Configuration' ?></button>
          </div>
        </form>
      </section>

      <!-- Sidebar: Configured Reports Table -->
      <aside class="sidebar">
        <div class="card">
          <h3>Configured Reports</h3>
          <table class="data-table">
            <thead>
              <tr>
                <th>Title / Key</th>
                <th>File</th>
                <th>Params</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($allReports)): ?>
                <tr><td colspan="4" style="text-align: center; color: #64748b;">No reports configured yet.</td></tr>
              <?php else: ?>
                <?php foreach ($allReports as $rep): ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars($rep['report_title']) ?></strong><br>
                      <small style="color: #64748b;"><?= htmlspecialchars($rep['report_key']) ?></small>
                    </td>
                    <td><small><?= htmlspecialchars($rep['file_name']) ?></small></td>
                    <td><?= (int)$rep['param_count'] ?></td>
                    <td style="white-space: nowrap;">
                      <a href="manage_reports.php?edit_id=<?= (int)$rep['id'] ?>" class="edit-btn" title="Edit Report">Edit</a>
                      <form method="POST" action="manage_reports.php" onsubmit="return confirm('Delete this report?');" style="display: inline;">
                        <input type="hidden" name="action" value="delete_report">
                        <input type="hidden" name="report_id" value="<?= (int)$rep['id'] ?>">
                        <button type="submit" class="del-btn" title="Delete Report">✕</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </aside>
    </div>

  <?php endif; ?>
</main>

<?php include_once __DIR__ . '/include/footer.php'; ?>

<script>
let paramCounter = <?= count($editParams) > 0 ? count($editParams) : 1 ?>;

function addParamRow() {
  const container = document.getElementById('param-list');
  const row = document.createElement('div');
  row.className = 'param-row';
  row.innerHTML = `
    <input type="text" name="params[${paramCounter}][name]" class="form-control" placeholder="Param Name" required>
    <select name="params[${paramCounter}][type]" class="form-control">
      <option value="string">String / Text</option>
      <option value="int">Integer</option>
      <option value="float">Decimal / Float</option>
      <option value="date">Date</option>
      <option value="bool">Boolean</option>
    </select>
    <label style="display: flex; align-items: center; gap: 4px; font-size: 0.8rem; margin: 0;">
      <input type="checkbox" name="params[${paramCounter}][required]" value="1"> Req
    </label>
    <input type="text" name="params[${paramCounter}][default]" class="form-control" placeholder="Default Val">
    <button type="button" class="del-btn" onclick="removeParamRow(this)">✕</button>
  `;
  container.appendChild(row);
  paramCounter++;
}

function removeParamRow(btn) {
  btn.closest('.param-row').remove();
}
</script>
</body>
</html>