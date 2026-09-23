<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/auth.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dropdown Option Builder - Central Management</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
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
    textarea.form-control { height: auto; resize: vertical; }
    .option-row {
      display: grid;
      grid-template-columns: 1fr 1fr auto auto;
      gap: 0.5rem;
      align-items: center;
      margin-bottom: 0.5rem;
    }
    .option-row .icon-btn {
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      padding: 4px 8px;
    }
    .option-row .move-btn { color: #475569; }
    .option-row .move-btn:hover { color: #1e293b; }
    .option-row .del-btn { color: #dc2626; font-weight: bold; }
    .option-row .del-btn:hover { color: #991b1b; }
    .builder-panel {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 1.25rem;
      margin-bottom: 1.5rem;
    }
    #json-output {
      min-height: 160px;
      font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
      font-size: 0.85rem;
    }
    .btn-primary, .btn-secondary {
      cursor: pointer;
    }
    .copy-feedback {
      color: #166534;
      font-weight: 600;
      font-size: 0.85rem;
      margin-left: 0.75rem;
      display: none;
    }
  </style>
</head>
<body>

<?php include_once __DIR__ . '/../include/header.php'; ?>

<main class="dashboard-container">

  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <h3 style="color: #28089a; margin: 0;">Dropdown Option Builder</h3>
    <a href="manage_reports.php" style="color: #64748b; font-size: 0.85rem; text-decoration: none;">&larr; Back to Manage Reports</a>
  </div>
  <p style="color: #64748b; margin: 0 0 1.5rem;">
    Build the <code>value</code>/<code>label</code> pairs for a report parameter's dropdown, then copy the generated
    JSON into the <strong>Dropdown options</strong> field for a <code>single_select</code> or
    <code>multi_select</code> parameter in <a href="manage_reports.php">Manage Reports</a>.
  </p>

  <div class="dashboard-layout">
    <section class="main-content">

      <div class="builder-panel">
        <h4 style="color: #28089a; margin: 0 0 0.75rem;">Bulk Paste (optional)</h4>
        <label for="bulk-input">One option per line, as <code>value,label</code> (label optional)</label>
        <textarea id="bulk-input" class="form-control" rows="4" placeholder="1,Music Ministry&#10;2,Youth Ministry&#10;3,Outreach"></textarea>
        <div style="margin-top: 0.5rem;">
          <button type="button" class="btn-secondary" id="bulk-add-btn">+ Add Lines as Options</button>
        </div>
      </div>

      <div class="builder-panel">
        <h4 style="color: #28089a; margin: 0 0 0.75rem;">Options</h4>
        <div class="option-row" style="font-weight: 600; color: #1e293b;">
          <span>Value</span>
          <span>Label</span>
          <span></span>
          <span></span>
        </div>
        <div id="option-list"></div>
        <button type="button" class="btn-secondary" id="add-row-btn" style="margin-top: 0.5rem;">+ Add Option</button>
      </div>

      <div class="builder-panel">
        <h4 style="color: #28089a; margin: 0 0 0.75rem;">Generated JSON</h4>
        <textarea id="json-output" class="form-control" readonly></textarea>
        <div style="margin-top: 0.75rem; display: flex; align-items: center;">
          <button type="button" class="btn-primary" id="copy-btn">Copy JSON</button>
          <span class="copy-feedback" id="copy-feedback">Copied!</span>
        </div>
      </div>

      <div class="builder-panel">
        <h4 style="color: #28089a; margin: 0 0 0.75rem;">Preview</h4>
        <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 0.75rem;">
          <label style="display: flex; align-items: center; gap: 6px; margin: 0;">
            <input type="radio" name="preview-mode" value="single_select" checked> Single-select
          </label>
          <label style="display: flex; align-items: center; gap: 6px; margin: 0;">
            <input type="radio" name="preview-mode" value="multi_select"> Multi-select
          </label>
        </div>
        <select id="preview-select" class="form-control"></select>
      </div>

    </section>
  </div>

</main>

<script>
  const optionList = document.getElementById('option-list');
  const jsonOutput = document.getElementById('json-output');
  const previewSelect = document.getElementById('preview-select');

  function createOptionRow(value = '', label = '') {
    const row = document.createElement('div');
    row.className = 'option-row';
    row.innerHTML = `
      <input type="text" class="form-control opt-value" value="${escapeAttr(value)}" placeholder="e.g. 1">
      <input type="text" class="form-control opt-label" value="${escapeAttr(label)}" placeholder="e.g. Music Ministry">
      <button type="button" class="icon-btn move-btn" title="Move up">&uarr;</button>
      <button type="button" class="icon-btn del-btn" title="Remove">&times;</button>
    `;

    row.querySelectorAll('.opt-value, .opt-label').forEach(input => {
      input.addEventListener('input', updateOutputs);
    });
    row.querySelector('.move-btn').addEventListener('click', () => {
      const prev = row.previousElementSibling;
      if (prev) {
        optionList.insertBefore(row, prev);
        updateOutputs();
      }
    });
    row.querySelector('.del-btn').addEventListener('click', () => {
      row.remove();
      updateOutputs();
    });

    return row;
  }

  function escapeAttr(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML.replace(/"/g, '&quot;');
  }

  function addRow(value = '', label = '') {
    optionList.appendChild(createOptionRow(value, label));
  }

  function getOptions() {
    return Array.from(optionList.querySelectorAll('.option-row')).map(row => ({
      value: row.querySelector('.opt-value').value.trim(),
      label: row.querySelector('.opt-label').value.trim(),
    })).filter(opt => opt.value !== '' || opt.label !== '')
      .map(opt => ({
        value: opt.value,
        label: opt.label !== '' ? opt.label : opt.value,
      }));
  }

  function updateOutputs() {
    const options = getOptions();
    jsonOutput.value = JSON.stringify(options);

    previewSelect.replaceChildren();
    previewSelect.multiple = document.querySelector('input[name="preview-mode"]:checked').value === 'multi_select';
    options.forEach(opt => {
      const optionEl = document.createElement('option');
      optionEl.value = opt.value;
      optionEl.textContent = opt.label;
      previewSelect.appendChild(optionEl);
    });
  }

  document.getElementById('add-row-btn').addEventListener('click', () => {
    addRow();
    updateOutputs();
  });

  document.getElementById('bulk-add-btn').addEventListener('click', () => {
    const lines = document.getElementById('bulk-input').value.split('\n');
    lines.forEach(line => {
      const trimmed = line.trim();
      if (trimmed === '') {
        return;
      }
      const [value, ...rest] = trimmed.split(',');
      addRow(value.trim(), rest.join(',').trim());
    });
    document.getElementById('bulk-input').value = '';
    updateOutputs();
  });

  document.querySelectorAll('input[name="preview-mode"]').forEach(radio => {
    radio.addEventListener('change', updateOutputs);
  });

  document.getElementById('copy-btn').addEventListener('click', () => {
    navigator.clipboard.writeText(jsonOutput.value).then(() => {
      const feedback = document.getElementById('copy-feedback');
      feedback.style.display = 'inline';
      setTimeout(() => { feedback.style.display = 'none'; }, 1500);
    });
  });

  // Seed with a couple of blank rows to start.
  addRow();
  addRow();
  updateOutputs();
</script>

</body>
</html>
