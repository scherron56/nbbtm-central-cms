<?php
// ministry_mailer.php
require_once __DIR__ . '/include/auth.php';

// Security check: Block anyone who is not an authenticated Admin or Staff
if (!isAdmin()) {
    requireRole(['admin', 'staff']);
}

// Safely require mailer helper
if (file_exists(__DIR__ . '/send_email.php')) {
    require_once __DIR__ . '/send_email.php';
} elseif (file_exists(__DIR__ . '/include/send_email.php')) {
    require_once __DIR__ . '/include/send_email.php';
}

// Verified Brevo sender identities
$verifiedSenders = [
    'revtdsublett@claricecraftedsolutions.org'   => 'Rev. Tracye Sublett',
    'nbbtmadmin@claricecraftedsolutions.org' => 'NBBTM Admin',
    'nbbtmtest@claricecraftedsolutions.org' => 'NBBTM Test Account',
    'ccsadmin@claricecraftedsolutions.org' => 'CCS Admin',
];

$message = '';
$statusClass = '';

// Handle email dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senderEmail = filter_var($_POST['sender_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $senderName  = $verifiedSenders[$senderEmail] ?? 'NBBTM Ministry Communication';
    $subject     = trim($_POST['subject'] ?? '');
    $rawBody     = trim($_POST['body_template'] ?? '');
    $selectedEmails = $_POST['selected_emails'] ?? []; // Array of recipient JSON objects

    $attachments = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        foreach ($_FILES['attachments']['tmp_name'] as $idx => $tmpPath) {
            if ($_FILES['attachments']['error'][$idx] === UPLOAD_ERR_OK) {
                $attachments[] = [
                    'path' => $tmpPath,
                    'name' => $_FILES['attachments']['name'][$idx]
                ];
            }
        }
    }

    // Parse recipient payload with individual name fields
    $recipientsToSend = [];
    if (!empty($selectedEmails) && is_array($selectedEmails)) {
        foreach ($selectedEmails as $rawItem) {
            $parsed = json_decode($rawItem, true);
            if ($parsed && !empty($parsed['email'])) {
                $firstName = trim($parsed['first_name'] ?? '');
                $lastName  = trim($parsed['last_name'] ?? '');
                $fullName  = trim($parsed['full_name'] ?? ($parsed['name'] ?? ''));

                if (!$fullName) {
                    $fullName = trim($firstName . ' ' . $lastName) ?: 'Ministry Member';
                }

                $recipientsToSend[] = [
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'full_name'  => $fullName,
                    'name'       => $firstName ?: $fullName,
                    'email'      => $parsed['email']
                ];
            }
        }
    }

    if ($senderEmail && !empty($recipientsToSend) && $subject && $rawBody) {
        $successCount = 0;
        $failCount = 0;

        foreach ($recipientsToSend as $recipient) {
            $personalizedBody = str_replace(
                ['{{name}}', '{{first_name}}', '{{last_name}}', '{{full_name}}', '{{email}}', '{{date}}'],
                [
                    htmlspecialchars($recipient['name']),
                    htmlspecialchars($recipient['first_name']),
                    htmlspecialchars($recipient['last_name']),
                    htmlspecialchars($recipient['full_name']),
                    htmlspecialchars($recipient['email']),
                    date('F j, Y')
                ],
                $rawBody
            );

            $htmlEmail = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #043b8f; margin: 0;'>" . htmlspecialchars($subject) . "</h2>
                </div>
                <div style='font-size: 15px; line-height: 1.6; color: #334155;'>" . nl2br($personalizedBody) . "</div>
                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0 15px 0;'>
                <p style='font-size: 12px; color: #64748b; text-align: center;'>New Beginnings Baptist Tabernacle Ministries<br>Sent by " . htmlspecialchars($senderName) . "</p>
            </div>";

            if (function_exists('sendEmail')) {
                $result = sendEmail(
                    $recipient['email'],
                    $recipient['full_name'],
                    $subject,
                    $htmlEmail,
                    $attachments,
                    $senderEmail,
                    $senderName
                );
                (!empty($result['success'])) ? $successCount++ : $failCount++;
            } else {
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: {$senderName} <{$senderEmail}>\r\n";
                mail($recipient['email'], $subject, $htmlEmail, $headers) ? $successCount++ : $failCount++;
            }
        }

        $message = "Ministry broadcast complete: <strong>{$successCount}</strong> sent successfully, <strong>{$failCount}</strong> failed.";
        $statusClass = $failCount > 0 ? 'text-danger' : 'text-success';
    } else {
        $message = "Please select at least one recipient member and fill in all required fields.";
        $statusClass = "text-danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Send Email - Ministry & Committee - NBBTM CMS</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <style>
    .tag-hint {
      font-size: 0.8rem;
      color: #64748b;
      margin-top: 4px;
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
    <h3>✉️ Send Email - Ministry & Committee</h3>

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

    <!-- Email Dispatch Form -->
    <form method="POST" enctype="multipart/form-data" id="mailerForm" style="max-width: 100%; box-shadow: none; padding: 0;">
      
      <!-- Sender Identity -->
      <div class="form-grid-section-12">
        <div class="field-group" style="--colspan: 12;">
          <label for="sender_email"><strong>Sender Identity</strong></label>
          <select name="sender_email" id="sender_email" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%; height: 38px;" required>
            <?php foreach ($verifiedSenders as $email => $label): ?>
              <option value="<?= htmlspecialchars($email) ?>">
                <?= htmlspecialchars($label) ?> &lt;<?= htmlspecialchars($email) ?>&gt;
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Recipient Multiselect (Displays ONLY Full Name) -->
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

      <!-- Live Populated Email Chips -->
      <div class="field-group" style="margin-top: 1rem;">
        <label><strong>Populated Recipient Emails (<span id="recipient-count">0</span>)</strong></label>
        <div id="selected-emails-display" class="recipients-badge-box">
          <span style="color: #94a3b8; font-size: 0.85rem;">No recipients selected.</span>
        </div>
      </div>

      <!-- Hidden container for POSTed selected members -->
      <div id="hidden-inputs-container"></div>

      <!-- Subject Line -->
      <div class="field-group" style="margin-top: 1rem;">
        <label for="subject"><strong>Subject Line</strong></label>
        <input type="text" name="subject" id="subject" required placeholder="Ministry Meeting Announcement" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
      </div>

      <!-- Email Body -->
      <div class="field-group" style="margin-top: 1rem;">
        <label for="body_template"><strong>Email Body</strong></label>
        <textarea name="body_template" id="body_template" rows="8" required placeholder="Hello {{first_name}},&#10;&#10;Here is the latest update for our ministry..." style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%; font-family: inherit;"></textarea>
        <div class="tag-hint">
          Dynamic placeholders: <code>{{name}}</code> (First name), <code>{{first_name}}</code>, <code>{{last_name}}</code>, <code>{{full_name}}</code>, <code>{{email}}</code>, <code>{{date}}</code>
        </div>
      </div>

      <!-- Attachments -->
      <div class="field-group" style="margin-top: 1rem;">
        <label for="attachments"><strong>Attachments (Optional)</strong></label>
        <input type="file" name="attachments[]" id="attachments" multiple style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; width: 100%;">
      </div>

      <div style="margin-top: 1.5rem;">
        <button type="submit" class="btn btn-primary" style="width: 100%;">Send Ministry Broadcast Email</button>
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
  const displayBox = $('#selected-emails-display');
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
            // Only add members who have an email address
            if (m.c_email && m.c_email.trim() !== '') {
              const firstName = (m.first_name || '').trim();
              const lastName  = (m.last_name || '').trim();
              const fullName  = `${firstName} ${lastName}`.trim() || 'No Name';

              // Display ONLY the contact name in the dropdown option
              options += `<option value="${escapeHtml(m.contact_id || m.c_email)}" 
                            data-first-name="${escapeHtml(firstName)}" 
                            data-last-name="${escapeHtml(lastName)}" 
                            data-full-name="${escapeHtml(fullName)}" 
                            data-email="${escapeHtml(m.c_email)}">${escapeHtml(fullName)}</option>`;
              countValid++;
            }
          });

          if (countValid > 0) {
            memberSelect.html(options);
          } else {
            memberSelect.html('<option value="" disabled>No members with email addresses found.</option>');
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

  // 4. Update and Display Selected Emails
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
      const email     = $(this).data('email');
      const firstName = $(this).data('first-name') || '';
      const lastName  = $(this).data('last-name') || '';
      const fullName  = $(this).data('full-name') || `${firstName} ${lastName}`.trim() || 'Member';

      // Create live chip display showing name and resolved email
      const chip = $('<span class="recipient-chip"></span>').text(`${fullName} <${email}>`);
      displayBox.append(chip);

      // Create hidden inputs for PHP POST processing
      const payload = JSON.stringify({
        first_name: firstName,
        last_name: lastName,
        full_name: fullName,
        email: email
      });

      $('<input>').attr({
        type: 'hidden',
        name: 'selected_emails[]',
        value: payload
      }).appendTo(hiddenContainer);
    });
  }

  memberSelect.on('change', syncSelectedRecipients);

  // Quick selection buttons
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
</script>
</body>
</html>