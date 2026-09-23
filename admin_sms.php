<?php
// admin_sms.php
require_once __DIR__ . '/include/auth.php';
// Security check: Block anyone who is not an authenticated Admin
if (!isAdmin()) {
    header('Location: ./index.php');
    exit;
}

require_once __DIR__ . '/include/send_sms.php';

// Fetch all valid contacts (with a phone number on file) with membership/age categories
$contactsList = [];
try {
    $resContacts = $db->query("
        SELECT
            contact_id,
            COALESCE(first_name, '') AS first_name,
            COALESCE(last_name, '') AS last_name,
            TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) AS full_name,
            phone_1,
            COALESCE(is_member, 0) AS is_member,
            COALESCE(is_child, 0) AS is_child
        FROM contacts
        WHERE phone_1 IS NOT NULL AND phone_1 != ''
        ORDER BY last_name ASC, first_name ASC
    ");
    if ($resContacts) {
        $contactsList = $resContacts->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("SMS Contact Query Error: " . $e->getMessage());
}

$message = '';
$statusClass = '';

// Handle SMS dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawBody = trim($_POST['sms_body'] ?? '');
    $selectedContactIds = $_POST['selected_contacts'] ?? [];

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
                    'phone'      => $contact['phone_1']
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

        $message = "Dispatch complete: <strong>{$successCount}</strong> sent successfully, <strong>{$failCount}</strong> failed.";
        if (!empty($errors)) {
            $message .= '<br><small>' . implode('<br>', $errors) . '</small>';
        }
        $statusClass = $failCount > 0 ? 'text-danger' : 'text-success';
    } else {
        $message = "Please select at least one recipient and enter a message.";
        $statusClass = "text-danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Send SMS - Contacts - NBBTM CMS</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
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
    <h3>📱 Send SMS - Contacts</h3>

    <form method="POST" id="adminSmsForm" style="max-width: 100%; box-shadow: none; padding: 0;">

      <!-- Recipient Filter Controls -->
      <div class="form-grid-section-12" style="background-color: #f1f5f9; padding: 1rem; border-radius: 6px;">
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
        <label><strong>Populated Phone Numbers (<span id="recipient-count">0</span>)</strong></label>
        <div id="selected-phones-display" class="recipients-badge-box">
          <span style="color: #94a3b8; font-size: 0.85rem;">No contacts selected.</span>
        </div>
      </div>

      <!-- SMS Body -->
      <div class="field-group" style="margin-top: 1rem;">
        <label for="sms_body"><strong>SMS Message</strong></label>
        <textarea name="sms_body" id="sms_body" rows="5" maxlength="918" required placeholder="Hi {{first_name}}, this is a reminder about..." style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1;" oninput="updateCharCount()"></textarea>
        <div class="tag-hint">
          Dynamic placeholders: <code>{{first_name}}</code>, <code>{{last_name}}</code>, <code>{{full_name}}</code>, <code>{{date}}</code>
          &mdash; <span id="sms-char-count">0 / 160 characters (1 segment)</span>
        </div>
      </div>

      <div style="margin-top: 1.5rem;">
        <button type="submit" class="btn btn-primary" style="width: 100%;">Send SMS</button>
      </div>
    </form>
  </div>
</main>

<?php include_once __DIR__ . '/include/footer.php'; ?>

<script>
// Raw contact dataset from PHP
const allContacts = <?= json_encode($contactsList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const selectEl = document.getElementById('selected_contacts');
const displayBox = document.getElementById('selected-phones-display');
const countSpan = document.getElementById('recipient-count');

function filterContacts() {
  const memFilter = document.getElementById('filter_membership').value;
  const ageFilter = document.getElementById('filter_age').value;
  const currentlySelected = Array.from(selectEl.selectedOptions).map(o => o.value);

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
    option.textContent = (contact.full_name || `${contact.first_name} ${contact.last_name}`.trim() || 'No Name') + ' - ' + contact.phone_1;
    option.setAttribute('data-phone', contact.phone_1);
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
    const phone = opt.getAttribute('data-phone');
    const name = opt.getAttribute('data-name');

    const chip = document.createElement('span');
    chip.className = 'recipient-chip';
    chip.textContent = `${name} <${phone}>`;
    displayBox.appendChild(chip);
  });
}

function selectAllVisible(selectState) {
  Array.from(selectEl.options).forEach(opt => opt.selected = selectState);
  updateSelectedRecipients();
}

function updateCharCount() {
  const body = document.getElementById('sms_body').value;
  const len = body.length;
  const segments = Math.max(1, Math.ceil(len / 160));
  const counter = document.getElementById('sms-char-count');
  counter.textContent = `${len} / 160 characters (${segments} segment${segments > 1 ? 's' : ''})`;
  counter.classList.toggle('limit-warning', segments > 1);
}

document.addEventListener('DOMContentLoaded', () => {
  filterContacts();
  updateCharCount();
});
</script>
</body>
</html>
