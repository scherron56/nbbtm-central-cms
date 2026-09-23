<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/auth.php';
/** @var mysqli $db Provided globally by include/auth.php -> config/db.php */

$message = '';
$error = '';

if (isset($_SESSION['manage_reports_message'])) {
    $message = $_SESSION['manage_reports_message'];
    unset($_SESSION['manage_reports_message']);
}
if (isset($_SESSION['manage_reports_error'])) {
    $error = $_SESSION['manage_reports_error'];
    unset($_SESSION['manage_reports_error']);
}

$userIsAdmin = isDeveloper();

if ($userIsAdmin) {
    // 1. Handle Report Deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_report') {
        $reportId = (int)($_POST['report_id'] ?? 0);
        if ($reportId > 0) {
            $stmt = $db->prepare("DELETE FROM app_reports WHERE id = ?");
            $stmt->bind_param('i', $reportId);
            if ($stmt->execute()) {
                $_SESSION['manage_reports_message'] = "Report configuration removed.";
            } else {
                $_SESSION['manage_reports_error'] = "Failed to delete report: " . $db->error;
            }
            $stmt->close();
            header('Location: manage_reports.php');
            exit;
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
                    $pStmt = $db->prepare("INSERT INTO app_report_parameters (report_id, lbl_param_name, param_name, param_type, control_type, static_options, placeholder, is_required, default_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($rawParams as $param) {
                        $pName  = trim($param['name'] ?? '');
                        if ($pName === '') continue;

                        $pLabel = trim($param['label'] ?? '') !== '' ? trim($param['label']) : null;
                        if (strtolower($pName) === 'sel_opt' && ($pLabel === null || $pLabel === '')) {
                            $pLabel = 'Print Group';
                        }
                        $pType  = in_array($param['type'] ?? '', ['string', 'int', 'float', 'bool', 'date']) ? $param['type'] : 'string';
                        $pControl = in_array($param['control_type'] ?? '', ['auto', 'text', 'number', 'date', 'checkbox', 'single_select'], true)
                            ? $param['control_type']
                            : 'auto';
                        $pControl = $pControl === 'auto' ? null : $pControl;
                        $pOptions = trim($param['options'] ?? '');

                        if (strtolower($pName) === 'sel_opt') {
                            $pType = 'string';
                            $pControl = 'single_select';
                            if ($pOptions === '') {
                                $pOptions = json_encode([
                                    ['value' => '', 'label' => 'Everyone'],
                                    ['value' => 'Y', 'label' => 'Youth'],
                                    ['value' => 'A', 'label' => 'Adults'],
                                ], JSON_THROW_ON_ERROR);
                            }
                        }

                        if ($pControl === 'single_select') {
                            try {
                                $options = json_decode($pOptions, true, 512, JSON_THROW_ON_ERROR);
                            } catch (JsonException $e) {
                                throw new RuntimeException("Options for '{$pName}' must be valid JSON.");
                            }

                            if (!is_array($options)) {
                                throw new RuntimeException("Options for '{$pName}' must be a JSON array.");
                            }

                            foreach ($options as $option) {
                                if (!is_array($option)
                                    || !array_key_exists('value', $option)
                                    || !array_key_exists('label', $option)
                                    || !is_scalar($option['value'])
                                    || !is_scalar($option['label'])) {
                                    throw new RuntimeException("Each option for '{$pName}' must contain scalar value and label fields.");
                                }
                            }

                            $pOptions = json_encode($options, JSON_THROW_ON_ERROR);
                        } elseif ($pOptions !== '') {
                            throw new RuntimeException("Static options are only supported for a single-select control ('{$pName}').");
                        } else {
                            $pOptions = null;
                        }
                        $pPlaceholder = trim($param['placeholder'] ?? '') !== '' ? trim($param['placeholder']) : null;
                        $pReq   = isset($param['required']) ? 1 : 0;
                        $pDef   = trim($param['default'] ?? '') !== '' ? trim($param['default']) : null;
                        if (strtolower($pName) === 'sel_opt' && ($pDef === null || $pDef === '')) {
                            $pDef = '';
                        }

                        $pStmt->bind_param('issssssis', $reportId, $pLabel, $pName, $pType, $pControl, $pOptions, $pPlaceholder, $pReq, $pDef);
                        $pStmt->execute();
                    }
                    $pStmt->close();
                }

                $db->commit();
                $_SESSION['manage_reports_message'] = $message;
                header('Location: manage_reports.php');
                exit;
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
  <link rel="stylesheet" href="../css/style.css">
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
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.5rem;
      padding: 0.75rem;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      margin-bottom: 0.75rem;
    }
    .param-row .param-options {
      grid-column: 1 / -1;
      min-height: 72px;
      resize: vertical;
    }
    .param-row .param-actions {
      display: flex;
      align-items: center;
      gap: 0.75rem;
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

<?php include_once __DIR__ . '/../include/header.php'; ?>

<main class="dashboard-container">

  <?php if (!$userIsAdmin): ?>
    <div style="background-color: #fee2e2; color: #991b1b; padding: 1.5rem; border-radius: 8px; font-weight: 600;">
      Access Denied: You must be signed in with a Developer account to configure reports.
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

          <p style="color: #64748b; margin: 0 0 1rem;">
            Need to build the JSON for a dropdown parameter's options?
            Use the <a href="option_builder.php">Dropdown Option Builder</a>.
          </p>
          
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
          <p style="color: #64748b; margin: 0 0 0.75rem;">
            Use <strong>Auto</strong> to create an input from the Jasper type. For a dropdown, enter options as JSON, for example:
            <code>[{"value":"1","label":"Music Ministry"}]</code>.
          </p>
          <div id="param-list">
            <?php if (!empty($editParams)): ?>
              <?php foreach ($editParams as $idx => $param): ?>
                <div class="param-row">
                  <input type="text" name="params[<?= $idx ?>][name]" class="form-control" value="<?= htmlspecialchars($param['param_name']) ?>" placeholder="Param Name (e.g. p_dept_id)" required>
                  <input type="text" name="params[<?= $idx ?>][label]" class="form-control" value="<?= htmlspecialchars($param['lbl_param_name'] ?? '') ?>" placeholder="Display Label (e.g. Department)">
                  <select name="params[<?= $idx ?>][type]" class="form-control">
                    <option value="string" <?= $param['param_type'] === 'string' ? 'selected' : '' ?>>String / Text</option>
                    <option value="int" <?= $param['param_type'] === 'int' ? 'selected' : '' ?>>Integer</option>
                    <option value="float" <?= $param['param_type'] === 'float' ? 'selected' : '' ?>>Decimal / Float</option>
                    <option value="date" <?= $param['param_type'] === 'date' ? 'selected' : '' ?>>Date</option>
                    <option value="bool" <?= $param['param_type'] === 'bool' ? 'selected' : '' ?>>Boolean</option>
                  </select>
                  <select name="params[<?= $idx ?>][control_type]" class="form-control">
                    <option value="auto" <?= empty($param['control_type']) ? 'selected' : '' ?>>Auto control</option>
                    <option value="text" <?= ($param['control_type'] ?? '') === 'text' ? 'selected' : '' ?>>Text input</option>
                    <option value="number" <?= ($param['control_type'] ?? '') === 'number' ? 'selected' : '' ?>>Number input</option>
                    <option value="date" <?= ($param['control_type'] ?? '') === 'date' ? 'selected' : '' ?>>Date picker</option>
                    <option value="checkbox" <?= ($param['control_type'] ?? '') === 'checkbox' ? 'selected' : '' ?>>Checkbox</option>
                    <option value="single_select" <?= ($param['control_type'] ?? '') === 'single_select' ? 'selected' : '' ?>>Dropdown</option>
                  </select>
                  <input type="text" name="params[<?= $idx ?>][default]" class="form-control" value="<?= htmlspecialchars($param['default_value'] ?? '') ?>" placeholder="Default Val">
                  <input type="text" name="params[<?= $idx ?>][placeholder]" class="form-control" value="<?= htmlspecialchars($param['placeholder'] ?? '') ?>" placeholder="Placeholder (text inputs)">
                  <textarea name="params[<?= $idx ?>][options]" class="form-control param-options" placeholder='Dropdown options JSON, e.g. [{"value":"1","label":"Music Ministry"}]'><?= htmlspecialchars($param['static_options'] ?? '') ?></textarea>
                  <div class="param-actions">
                    <label style="display: flex; align-items: center; gap: 4px; font-size: 0.8rem; margin: 0;">
                      <input type="checkbox" name="params[<?= $idx ?>][required]" value="1" <?= $param['is_required'] ? 'checked' : '' ?>> Required
                    </label>
                    <button type="button" class="del-btn" onclick="removeParamRow(this)">Remove</button>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="param-row">
                <input type="text" name="params[0][name]" class="form-control" placeholder="Param Name (e.g. p_dept_id)" required>
                <input type="text" name="params[0][label]" class="form-control" placeholder="Display Label (e.g. Department)">
                <select name="params[0][type]" class="form-control">
                  <option value="string">String / Text</option>
                  <option value="int">Integer</option>
                  <option value="float">Decimal / Float</option>
                  <option value="date">Date</option>
                  <option value="bool">Boolean</option>
                </select>
                <select name="params[0][control_type]" class="form-control">
                  <option value="auto">Auto control</option>
                  <option value="text">Text input</option>
                  <option value="number">Number input</option>
                  <option value="date">Date picker</option>
                  <option value="checkbox">Checkbox</option>
                  <option value="single_select">Dropdown</option>
                </select>
                <input type="text" name="params[0][default]" class="form-control" placeholder="Default Val">
                <input type="text" name="params[0][placeholder]" class="form-control" placeholder="Placeholder (text inputs)">
                <textarea name="params[0][options]" class="form-control param-options" placeholder='Dropdown options JSON, e.g. [{"value":"1","label":"Music Ministry"}]'></textarea>
                <div class="param-actions">
                  <label style="display: flex; align-items: center; gap: 4px; font-size: 0.8rem; margin: 0;">
                    <input type="checkbox" name="params[0][required]" value="1"> Required
                  </label>
                  <button type="button" class="del-btn" onclick="removeParamRow(this)">Remove</button>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <button type="button" class="btn-secondary" onclick="addParamRow()" style="margin-top: 0.5rem; width: fit-content;">+ Add Parameter</button>

          <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn-primary"><?= $editReport ? 'Update Report Configuration' : 'Save Report Configuration' ?></button>
            <a href="report_files.php" class="btn-secondary" style="text-decoration:none; margin-left:0.5rem;">Manage Saved Reports</a>
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

<?php include_once __DIR__ . '/../include/footer.php'; ?>

<script>
let paramCounter = <?= count($editParams) > 0 ? count($editParams) : 1 ?>;

function applyDefaultPresetForSelOpt(row) {
  const nameInput = row.querySelector('input[name$="[name]"]');
  if (!nameInput) return;

  const applyPreset = () => {
    const paramName = (nameInput.value || '').trim().toLowerCase();
    if (paramName !== 'sel_opt') return;

  const labelInput = row.querySelector('input[name$="[label]"]');
  const typeSelect = row.querySelector('select[name$="[type]"]');
  const controlSelect = row.querySelector('select[name$="[control_type]"]');
  const defaultInput = row.querySelector('input[name$="[default]"]');
  const optionsInput = row.querySelector('textarea[name$="[options]"]');

  if (labelInput && !labelInput.value.trim()) labelInput.value = 'Print Group';
  if (typeSelect) typeSelect.value = 'string';
  if (controlSelect) controlSelect.value = 'single_select';
  if (defaultInput && !defaultInput.value.trim()) defaultInput.value = '';
  if (optionsInput && !optionsInput.value.trim()) {
    optionsInput.value = JSON.stringify([
      { value: '', label: 'Everyone' },
      { value: 'Y', label: 'Youth' },
      { value: 'A', label: 'Adults' }
    ], null, 2);
  }
  };

  nameInput.addEventListener('input', applyPreset);
  applyPreset();
}

function addParamRow() {
  const container = document.getElementById('param-list');
  const row = document.createElement('div');
  row.className = 'param-row';
  row.innerHTML = `
    <input type="text" name="params[${paramCounter}][name]" class="form-control" placeholder="Param Name (e.g. p_dept_id)" required>
    <input type="text" name="params[${paramCounter}][label]" class="form-control" placeholder="Display Label (e.g. Department)">
    <select name="params[${paramCounter}][type]" class="form-control">
      <option value="string">String / Text</option>
      <option value="int">Integer</option>
      <option value="float">Decimal / Float</option>
      <option value="date">Date</option>
      <option value="bool">Boolean</option>
    </select>
    <select name="params[${paramCounter}][control_type]" class="form-control">
      <option value="auto">Auto control</option>
      <option value="text">Text input</option>
      <option value="number">Number input</option>
      <option value="date">Date picker</option>
      <option value="checkbox">Checkbox</option>
      <option value="single_select">Dropdown</option>
    </select>
    <input type="text" name="params[${paramCounter}][default]" class="form-control" placeholder="Default Val">
    <input type="text" name="params[${paramCounter}][placeholder]" class="form-control" placeholder="Placeholder (text inputs)">
    <textarea name="params[${paramCounter}][options]" class="form-control param-options" placeholder='Dropdown options JSON, e.g. [{"value":"1","label":"Music Ministry"}]'></textarea>
    <div class="param-actions">
      <label style="display: flex; align-items: center; gap: 4px; font-size: 0.8rem; margin: 0;">
        <input type="checkbox" name="params[${paramCounter}][required]" value="1"> Required
      </label>
      <button type="button" class="del-btn" onclick="removeParamRow(this)">Remove</button>
    </div>
  `;
  container.appendChild(row);
  applyDefaultPresetForSelOpt(row);
  paramCounter++;
}

function removeParamRow(btn) {
  btn.closest('.param-row').remove();
}

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.param-row').forEach(function(row) {
    applyDefaultPresetForSelOpt(row);
  });
});
</script>
</body>
</html>