<?php
// ministry_sms.php
require_once __DIR__ . '/include/auth.php';

// Security check: Block anyone who is not an authenticated Admin or Staff
if (!isAdmin()) {
    requireRole(['admin', 'staff']);
}

require_once __DIR__ . '/include/send_sms.php';

$message = '';
$statusClass = '';

// Handle SMS dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawBody = trim($_POST['sms_body'] ?? '');
    $selectedRecipients = $_POST['selected_recipients'] ?? []; // Array of recipient JSON objects

    // Parse recipient payload with individual name/phone fields
    $recipientsToSend = [];
    if (!empty($selectedRecipients) && is_array($selectedRecipients)) {
        foreach ($selectedRecipients as $rawItem) {
            $parsed = json_decode($rawItem, true);
            if ($parsed && !empty($parsed['phone'])) {
                $firstName = trim($parsed['first_name'] ?? '');
                $lastName  = trim($parsed['last_name'] ?? '');
                $fullName  = trim($parsed['full_name'] ?? '');

                if (!$fullName) {
                    $fullName = trim($firstName . ' ' . $lastName) ?: 'Ministry Member';
                }

                $recipientsToSend[] = [
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'full_name'  => $fullName,
                    'phone'      => $parsed['phone']
                ];
            }
        }
    }

    if (!empty($recipientsToSend) && $rawBody) {
        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($recipientsToSend as $recipient) {
            $personalizedBody = str_replace(
                ['{{first_name}}', '{{last_name}}', '{{full_name}}', '{{date}}'],
                [
                    $recipient['first_name'],
                    $recipient['last_name'],
                    $recipient['full_name'],
                    date('F j, Y')
                ],
                $rawBody
            );

            $result = sendSms($recipient['phone'], $personalizedBody);
            if (!empty($result['success'])) {
                $successCount++;
            } else {
                $failCount++;
                $errors[] = htmlspecialchars($recipient['full_name']) . ': ' . htmlspecialchars($result['error'] ?? 'Unknown error');
            }
        }

        $message = "Ministry SMS broadcast complete: <strong>{$successCount}</strong> sent successfully, <strong>{$failCount}</strong> failed.";
        if (!empty($errors)) {
            $message .= '<br><small>' . implode('<br>', $errors) . '</small>';
        }
        $statusClass = $failCount > 0 ? 'text-danger' : 'text-success';
    } else {
        $message = "Please select at least one recipient member and enter a message.";
        $statusClass = "text-danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Send SMS - Ministry & Committee - NBBTM CMS</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <style>
    .tag-hint {
      font-size: 0.8rem;
      color: #64748b;
      margin-top: 6px;
    }
    .tag-hint code {
      background: #f1f5f9;
      padding: 2px 4px;
      border-radius: 4px;
      color: #043b8f;
      font-weight: 600;
    }
    .recipients-badge-box {
      min-height: 44px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      padding: 8px 10px;
      background-color: #f8fafc;
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      align-items: center;
    }
    .recipient-chip {
      background-color: #e0e7ff;
      color: #28089a;
      border: 1px solid #c7d2fe;
      padding: 4px 10px;
      border-radius: 4px;
      font-size: 0.85rem;
      font-weight: 500;
    }
    .select-actions {
      display: flex;
      gap: 0.5rem;
      margin-top: 6px;
    }
    .btn-sm {
      padding: 4px 10px;
      font-size: 0.8rem;
      cursor: pointer;
    }
    #sms-char-count.limit-warning {
      color: #b45309;
      font-weight: 600;
    }
  </style>
</head>
<body>

<?php include_once __DIR__ . '/include/header.php'; ?>

<main class="dashboard-container">

  <!-- Status Alert Messages -->
  <?php if ($message): ?>
    <div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
      <span class="<?= $statusClass ?>"><?= $message ?></span>
    </div>
  <?php endif; ?>

  <div class="card" style="max-width: 950px; margin: 0 auto 2rem auto;">
    <h3>📱 Send SMS(s) - Ministry & Committee</h3>

    <!-- Filter & Ministry Selector -->
    <div class="form-grid-section-12" style="background-color: #f1f5f9; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;">
      <div class="field-group" style="--colspan: 6;">
        <label for="filter_group_type"><strong>Filter by Group Type</strong></label>
        <select id="filter_group_type" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%; height: 38px;">
          <option value="">-- All Group Types --</option>
        </select>
      </div>

      <div class="field-group" style="--colspan: 6;">
        <label for="min_comm_id"><strong>Select Ministry / Committee *</strong></label>
        <select id="min_comm_id" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%; height: 38px;">
          <option value="">-- Choose Ministry to Load Members --</option>
        </select>
      </div>
    </div>

    <!-- SMS Dispatch Form -->
    <form method="POST" id="smsForm" style="max-width: 100%; box-shadow: none; padding: 0;">

      <!-- Recipient Multiselect (Displays Name + Phone) -->
      <div class="field-group" style="margin-top: 1rem;">
        <label for="member_select">
          <strong>Select Ministry Member(s)</strong>
          <small style="color:#64748b;">(Hold Ctrl/Cmd or Shift to select multiple)</small>
        </label>

        <select id="member_select" multiple size="7" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
          <option value="" disabled style="color: #94a3b8;">-- Please select a ministry above to load member roster --</option>
        </select>

        <div class="select-actions">
          <button type="button" class="btn btn-secondary btn-sm" id="select_all_btn">Select All Members</button>
          <button type="button" class="btn btn-secondary btn-sm" id="deselect_all_btn">Deselect All</button>
        </div>
      </div>

      <!-- Live Populated Phone Chips -->
      <div class="field-group" style="margin-top: 1rem;">
        <label><strong>Populated Recipient Phone Numbers (<span id="recipient-count">0</span>)</strong></label>
        <div id="selected-phones-display" class="recipients-badge-box">
          <span style="color: #94a3b8; font-size: 0.85rem;">No recipients selected.</span>
        </div>
      </div>

      <!-- Hidden container for POSTed selected members -->
      <div id="hidden-inputs-container"></div>

      <!-- SMS Body -->
      <div class="field-group" style="margin-top: 1rem;">
        <label for="sms_body"><strong>SMS Message</strong></label>
        <textarea name="sms_body" id="sms_body" rows="5" maxlength="918" required placeholder="Hi {{first_name}}, here is a ministry update..." style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1;" oninput="updateCharCount()"></textarea>
        <div class="tag-hint">
          Dynamic placeholders: <code>{{first_name}}</code>, <code>{{last_name}}</code>, <code>{{full_name}}</code>, <code>{{date}}</code>
          &mdash; <span id="sms-char-count">0 / 160 characters (1 segment)</span>
        </div>
      </div>

      <div style="margin-top: 1.5rem;">
        <button type="submit" class="btn btn-primary" style="width: 100%;">Send Ministry SMS Broadcast</button>
      </div>
    </form>
  </div>
</main>

<?php include_once __DIR__ . '/include/footer.php'; ?>

<script>
$(document).ready(function() {
  const filterGroup = $('#filter_group_type');
  const selectMinistry = $('#min_comm_id');
  const memberSelect = $('#member_select');
  const displayBox = $('#selected-phones-display');
  const countSpan = $('#recipient-count');
  const hiddenContainer = $('#hidden-inputs-container');

  // 1. Load Group Types
  $.ajax({
    url: 'ministry_api.php',
    type: 'GET',
    data: { action: 'get_group_types' },
    dataType: 'json',
    success: function(res) {
      if (res.status === 'success') {
        let options = '<option value="">-- All Group Types --</option>';
        $.each(res.data, function(i, gt) {
          options += `<option value="${gt.min_grp_type_id}">${escapeHtml(gt.min_grp_type_desc)}</option>`;
        });
        filterGroup.html(options);
      }
    }
  });

  // 2. Load Committees Dropdown
  function loadCommittees(filterType = '') {
    $.ajax({
      url: 'ministry_api.php',
      type: 'GET',
      data: { action: 'get_committees', filter_type: filterType },
      dataType: 'json',
      success: function(res) {
        if (res.status === 'success') {
          let options = '<option value="">-- Select Ministry / Committee --</option>';
          $.each(res.data, function(i, c) {
            options += `<option value="${c.min_comm_id}">${escapeHtml(c.min_comm_name)}</option>`;
          });
          selectMinistry.html(options);
        }
      }
    });
  }
  loadCommittees();

  // Filter Group Type Trigger
  filterGroup.on('change', function() {
    loadCommittees($(this).val());
    clearMembersList();
  });

  // 3. Load Members Roster on Ministry Selection
  selectMinistry.on('change', function() {
    const minCommId = $(this).val();
    if (!minCommId) {
      clearMembersList();
      return;
    }

    memberSelect.html('<option value="" disabled>Loading ministry members...</option>');

    $.ajax({
      url: 'ministry_api.php',
      type: 'GET',
      data: { action: 'get_members', min_comm_id: minCommId },
      dataType: 'json',
      success: function(res) {
        if (res.status === 'success' && res.data.length > 0) {
          let options = '';
          let countValid = 0;

          $.each(res.data, function(i, m) {
            if (m.phone_1 && m.phone_1.trim() !== '') {
              const firstName = (m.first_name || '').trim();
              const lastName  = (m.last_name || '').trim();
              const fullName  = `${firstName} ${lastName}`.trim() || 'No Name';

              options += `<option value="${escapeHtml(m.phone_1)}"
                            data-first-name="${escapeHtml(firstName)}"
                            data-last-name="${escapeHtml(lastName)}"
                            data-full-name="${escapeHtml(fullName)}"
                            data-phone="${escapeHtml(m.phone_1)}">${escapeHtml(fullName)} - ${escapeHtml(m.phone_1)}</option>`;
              countValid++;
            }
          });

          if (countValid > 0) {
            memberSelect.html(options);
          } else {
            memberSelect.html('<option value="" disabled>No members with phone numbers found.</option>');
          }
        } else {
          memberSelect.html('<option value="" disabled>No active members found in this ministry.</option>');
        }
        syncSelectedRecipients();
      },
      error: function() {
        memberSelect.html('<option value="" disabled>Error loading roster from server.</option>');
        syncSelectedRecipients();
      }
    });
  });

  // 4. Update and Display Selected Recipients
  function syncSelectedRecipients() {
    displayBox.empty();
    hiddenContainer.empty();

    const selectedOptions = memberSelect.find('option:selected:not(:disabled)');
    countSpan.text(selectedOptions.length);

    if (selectedOptions.length === 0) {
      displayBox.html('<span style="color: #94a3b8; font-size: 0.85rem;">No recipients selected.</span>');
      return;
    }

    selectedOptions.each(function() {
      const phone     = $(this).data('phone');
      const firstName = $(this).data('first-name') || '';
      const lastName  = $(this).data('last-name') || '';
      const fullName  = $(this).data('full-name') || `${firstName} ${lastName}`.trim() || 'Member';

      const chip = $('<span class="recipient-chip"></span>').text(`${fullName} <${phone}>`);
      displayBox.append(chip);

      const payload = JSON.stringify({
        first_name: firstName,
        last_name: lastName,
        full_name: fullName,
        phone: phone
      });

      $('<input>').attr({
        type: 'hidden',
        name: 'selected_recipients[]',
        value: payload
      }).appendTo(hiddenContainer);
    });
  }

  memberSelect.on('change', syncSelectedRecipients);

  $('#select_all_btn').on('click', function() {
    memberSelect.find('option:not(:disabled)').prop('selected', true);
    syncSelectedRecipients();
  });

  $('#deselect_all_btn').on('click', function() {
    memberSelect.find('option').prop('selected', false);
    syncSelectedRecipients();
  });

  function clearMembersList() {
    memberSelect.html('<option value="" disabled style="color: #94a3b8;">-- Please select a ministry above to load member roster --</option>');
    syncSelectedRecipients();
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }
});

function updateCharCount() {
  const body = document.getElementById('sms_body').value;
  const len = body.length;
  const segments = Math.max(1, Math.ceil(len / 160));
  const counter = document.getElementById('sms-char-count');
  counter.textContent = `${len} / 160 characters (${segments} segment${segments > 1 ? 's' : ''})`;
  counter.classList.toggle('limit-warning', segments > 1);
}

document.addEventListener('DOMContentLoaded', updateCharCount);
</script>
</body>
</html>
