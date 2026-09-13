<?php
// admin_mailer.php
require_once __DIR__ . '/include/auth.php';
// admin_mailer.php
require_once __DIR__ . '/include/db.php'; // <-- Add your DB connection file here
// Security check: Block anyone who is not an authenticated Admin
if (!isAdmin()) {
    header('Location: ./index.php');
    exit;
}

// Safely require mailer helper if available
if (file_exists(__DIR__ . '/send_email.php')) {
    require_once __DIR__ . '/send_email.php';
} elseif (file_exists(__DIR__ . '/include/send_email.php')) {
    require_once __DIR__ . '/include/send_email.php';
}

// Fetch all valid contacts with their membership, age categories, and separate names
$contactsList = [];
try {
    $resContacts = $db->query("
        SELECT 
            contact_id, 
            COALESCE(first_name, '') AS first_name,
            COALESCE(last_name, '') AS last_name,
            TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) AS full_name,
            c_email,
            COALESCE(is_member, 0) AS is_member,
            COALESCE(is_child, 0) AS is_child
        FROM contacts 
        WHERE c_email IS NOT NULL AND c_email != ''
        ORDER BY last_name ASC, first_name ASC
    ");
    if ($resContacts) {
        $contactsList = $resContacts->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Mailer Contact Query Error: " . $e->getMessage());
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
    $senderName  = $verifiedSenders[$senderEmail] ?? 'NBBTM System';
    $subject     = trim($_POST['subject'] ?? '');
    $rawBody     = trim($_POST['body_template'] ?? '');
    $selectedContactIds = $_POST['selected_contacts'] ?? [];

    // Parse and sanitize comma-separated CC emails
    $rawCc = trim($_POST['cc_emails'] ?? '');
    $ccEmails = [];
    if (!empty($rawCc)) {
        $splitCc = array_map('trim', explode(',', $rawCc));
        foreach ($splitCc as $ccItem) {
            $validCc = filter_var($ccItem, FILTER_VALIDATE_EMAIL);
            if ($validCc) {
                $ccEmails[] = $validCc;
            }
        }
    }

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

    // Filter recipients from selected IDs
    $recipientsToSend = [];
    if (!empty($selectedContactIds) && is_array($selectedContactIds)) {
        $idLookup = array_flip($selectedContactIds);
        foreach ($contactsList as $contact) {
            if (isset($idLookup[$contact['contact_id']])) {
                $recipientsToSend[] = [
                    'first_name' => $contact['first_name'],
                    'last_name'  => $contact['last_name'],
                    'full_name'  => $contact['full_name'] ?: 'Member',
                    'name'       => $contact['first_name'] ?: ($contact['full_name'] ?: 'Member'),
                    'email'      => $contact['c_email']
                ];
            }
        }
    }

    if ($senderEmail && !empty($recipientsToSend) && $subject && $rawBody) {
        $successCount = 0;
        $failCount = 0;

        // Allow rich email formatting tags including inline styling and spans
        $allowedTags = '<p><br><strong><b><em><i><u><s><h1><h2><h3><h4><h5><h6><ul><ol><li><a><span><div><hr><table><tbody><tr><td><th>';
        $sanitizedBody = strip_tags($rawBody, $allowedTags);

        // Ministry Scripture Passage
        $scriptureVerse = '<em>"Trust in the Lord with all your heart, and do not lean on your own understanding; in all your ways acknowledge him, and he will make straight your paths."</em> — <strong>Proverbs 3:5-6</strong>';

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
                $sanitizedBody
            );

            $htmlEmail = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff;'>
                <!-- Subject Header -->
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #043b8f; margin: 0;'>" . htmlspecialchars($subject) . "</h2>
                </div>

                <!-- Formatted Email Body Content -->
                <div style='font-size: 15px; line-height: 1.6; color: #334155;'>
                    " . $personalizedBody . "
                </div>

                <!-- Scripture Blockquote Section -->
                <div style='margin-top: 25px; padding: 12px 16px; background-color: #f8fafc; border-left: 4px solid #043b8f; font-size: 13px; line-height: 1.5; color: #475569;'>
                    " . $scriptureVerse . "
                </div>

                <!-- Sign-off & Sender Identity -->
                <div style='margin-top: 20px; font-size: 14px; color: #334155;'>
                    <p style='margin: 0;'>Blessings in Christ,</p>
                    <p style='margin: 4px 0 0 0; font-weight: bold; color: #043b8f;'>" . htmlspecialchars($senderName) . "</p>
                </div>

                <!-- Organization Footer -->
                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0 15px 0;'>
                <p style='font-size: 12px; color: #64748b; text-align: center; margin: 0;'>
                    New Beginnings Baptist Tabernacle Ministries<br>Sent by " . htmlspecialchars($senderName) . "
                </p>
            </div>";

            if (function_exists('sendEmail')) {
                $result = sendEmail(
                    $recipient['email'],
                    $recipient['full_name'],
                    $subject,
                    $htmlEmail,
                    $attachments,
                    $senderEmail,
                    $senderName,
                    $ccEmails
                );
                (!empty($result['success'])) ? $successCount++ : $failCount++;
            } else {
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: {$senderName} <{$senderEmail}>\r\n";

                if (!empty($ccEmails)) {
                    $headers .= "Cc: " . implode(', ', $ccEmails) . "\r\n";
                }

                mail($recipient['email'], $subject, $htmlEmail, $headers) ? $successCount++ : $failCount++;
            }
        }

        $message = "Dispatch complete: <strong>{$successCount}</strong> sent successfully, <strong>{$failCount}</strong> failed.";
        $statusClass = $failCount > 0 ? 'text-danger' : 'text-success';
    } else {
        $message = "Please select at least one recipient and fill in all required fields.";
        $statusClass = "text-danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Send Email - Contacts - NBBTM CMS</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  
  <!-- TinyMCE CDN -->

<script src="https://cdn.tiny.cloud/1/saqcb3hu5pn4po97aj84k9x4lnmu4i8fsimn7zshcjjhv3iz/tinymce/8/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>
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
      min-height: 42px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      padding: 6px 10px;
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
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 0.82rem;
      font-weight: 500;
    }
    .select-actions {
      display: flex;
      gap: 0.5rem;
      margin-top: 4px;
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
    <h3>✉️ Send Email - Contacts</h3>

    <form method="POST" enctype="multipart/form-data" id="adminMailerForm" style="max-width: 100%; box-shadow: none; padding: 0;">
      
      <!-- Sender Configuration -->
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

      <!-- Recipient Filter Controls -->
      <div class="form-grid-section-12" style="margin-top: 1rem; background-color: #f1f5f9; padding: 1rem; border-radius: 6px;">
        <div class="field-group" style="--colspan: 6;">
          <label for="filter_membership"><strong>Filter by Membership</strong></label>
          <select id="filter_membership" onchange="filterContacts()" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%; height: 38px;">
            <option value="all">All Contacts</option>
            <option value="member">Members Only</option>
            <option value="non_member">Non-Members Only</option>
          </select>
        </div>

        <div class="field-group" style="--colspan: 6;">
          <label for="filter_age"><strong>Filter by Age Category</strong></label>
          <select id="filter_age" onchange="filterContacts()" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%; height: 38px;">
            <option value="all">All Ages</option>
            <option value="adult">Adults Only</option>
            <option value="child">Children Only</option>
          </select>
        </div>
      </div>

      <!-- Multi-select Contact Dropdown -->
      <div class="field-group" style="margin-top: 1rem;">
        <label for="selected_contacts"><strong>Select Contact(s)</strong> <small style="color:#64748b;">(Hold Ctrl/Cmd or Shift to select multiple)</small></label>
        <select name="selected_contacts[]" id="selected_contacts" multiple size="7" onchange="updateSelectedRecipients()" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;" required>
          <!-- Populated via JavaScript dynamically -->
        </select>
        
        <div class="select-actions">
          <button type="button" class="btn btn-secondary btn-sm" onclick="selectAllVisible(true)">Select All Filtered</button>
          <button type="button" class="btn btn-secondary btn-sm" onclick="selectAllVisible(false)">Deselect All</button>
        </div>
      </div>

      <!-- Selected Recipients Live View -->
      <div class="field-group" style="margin-top: 1rem;">
        <label><strong>Populated Email Addresses (<span id="recipient-count">0</span>)</strong></label>
        <div id="selected-emails-display" class="recipients-badge-box">
          <span style="color: #94a3b8; font-size: 0.85rem;">No contacts selected.</span>
        </div>
      </div>

      <!-- CC Field -->
      <div class="field-group" style="margin-top: 1rem;">
        <label for="cc_emails"><strong>CC (Optional)</strong> <small style="color:#64748b;">(Comma-separated emails)</small></label>
        <input type="text" name="cc_emails" id="cc_emails" placeholder="pastor@nbbtm.org, office@nbbtm.org" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
      </div>

      <!-- Subject Line -->
      <div class="field-group" style="margin-top: 1rem;">
        <label for="subject"><strong>Subject Line</strong></label>
        <input type="text" name="subject" id="subject" required placeholder="Ministry Announcement" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
      </div>

      <!-- Email Body with TinyMCE -->
      <div class="field-group" style="margin-top: 1rem;">
        <label for="body_template"><strong>Email Body</strong></label>
        <textarea name="body_template" id="body_template" rows="10" placeholder="Hello {{first_name}},&#10;&#10;We are pleased to inform you..." style="width: 100%;"></textarea>
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
        <button type="submit" class="btn btn-primary" style="width: 100%;">Send Email</button>
      </div>
    </form>
  </div>
</main>

<?php include_once __DIR__ . '/include/footer.php'; ?>

<script>
// Initialize TinyMCE Editor
// Replace your existing tinymce.init block with this:
tinymce.init({
  selector: '#body_template',
  height: 320,
  menubar: false,
  plugins: 'lists link code',
  toolbar: 'undo redo | blocks fontsize | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link clean',
  fontsize_formats: '10px 12px 14px 16px 18px 20px 24px 28px 32px',
  content_style: 'body { font-family: Arial, sans-serif; font-size: 15px; color: #334155; line-height: 1.6; }',
  setup: function(editor) {
    // Keep underlying textarea in sync in real-time
    editor.on('change keyup NodeChange', function() {
      editor.save();
    });
  }
});

// Raw contact dataset from PHP
const allContacts = <?= json_encode($contactsList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const selectEl = document.getElementById('selected_contacts');
const displayBox = document.getElementById('selected-emails-display');
const countSpan = document.getElementById('recipient-count');

// Sync TinyMCE before submission
document.getElementById('adminMailerForm').addEventListener('submit', function() {
  if (typeof tinymce !== 'undefined') {
    tinymce.triggerSave();
  }
});

function filterContacts() {
  const memFilter = document.getElementById('filter_membership').value;
  const ageFilter = document.getElementById('filter_age').value;

  const currentlySelected = Array.from(selectEl.selectedOptions).map(opt => opt.value);
  selectEl.innerHTML = '';

  allContacts.forEach(contact => {
    const isMember = parseInt(contact.is_member, 10) === 1;
    if (memFilter === 'member' && !isMember) return;
    if (memFilter === 'non_member' && isMember) return;

    const isChild = parseInt(contact.is_child, 10) === 1;
    if (ageFilter === 'child' && !isChild) return;
    if (ageFilter === 'adult' && isChild) return;

    const option = document.createElement('option');
    option.value = contact.contact_id;
    option.textContent = contact.full_name || `${contact.first_name} ${contact.last_name}`.trim() || 'No Name';
    option.setAttribute('data-email', contact.c_email);
    option.setAttribute('data-name', contact.full_name || 'Member');

    if (currentlySelected.includes(String(contact.contact_id))) {
      option.selected = true;
    }

    selectEl.appendChild(option);
  });

  updateSelectedRecipients();
}

function updateSelectedRecipients() {
  const selectedOptions = Array.from(selectEl.selectedOptions);
  displayBox.innerHTML = '';
  countSpan.textContent = selectedOptions.length;

  if (selectedOptions.length === 0) {
    displayBox.innerHTML = '<span style="color: #94a3b8; font-size: 0.85rem;">No contacts selected.</span>';
    return;
  }

  selectedOptions.forEach(opt => {
    const email = opt.getAttribute('data-email');
    const name = opt.getAttribute('data-name');
    
    const chip = document.createElement('span');
    chip.className = 'recipient-chip';
    chip.textContent = `${name} <${email}>`;
    displayBox.appendChild(chip);
  });
}

function selectAllVisible(selectState) {
  Array.from(selectEl.options).forEach(opt => opt.selected = selectState);
  updateSelectedRecipients();
}

document.addEventListener('DOMContentLoaded', () => {
  filterContacts();
});
</script>
</body>
</html>