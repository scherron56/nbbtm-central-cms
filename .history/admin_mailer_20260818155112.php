<?php
// admin_mailer.php
require_once __DIR__ . '/include/auth.php';

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

// Fetch recipient groups using active MySQLi $db connection
$recipientGroups = [
    'contacts'     => [],
    'system_users' => []
];

// 1. Fetch active contacts from database (using correct first_name, last_name columns)
try {
    $resContacts = $db->query("SELECT contact_id, CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) AS name, c_email AS email FROM contacts WHERE c_email IS NOT NULL AND c_email != ''");
    if ($resContacts) {
        while ($row = $resContacts->fetch_assoc()) {
            $recipientGroups['contacts'][] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Mailer Contact Query Error: " . $e->getMessage());
}

// 2. Fetch active system users from database
try {
    $resUsers = $db->query("SELECT user_id, username AS name, email FROM system_users WHERE is_active = 1 AND email IS NOT NULL AND email != ''");
    if ($resUsers) {
        while ($row = $resUsers->fetch_assoc()) {
            $recipientGroups['system_users'][] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Mailer User Query Error: " . $e->getMessage());
}

// Verified Brevo sender identities
$verifiedSenders = [
    'info@nbbtm.org'   => 'NBBTM Office',
    'pastor@nbbtm.org' => 'Pastoral Staff',
    'events@nbbtm.org' => 'Programs & Events Team'
];

$message = '';
$statusClass = '';

// Handle email dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senderEmail = filter_var($_POST['sender_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $senderName  = $verifiedSenders[$senderEmail] ?? 'NBBTM System';
    $subject     = trim($_POST['subject'] ?? '');
    $rawBody     = trim($_POST['body_template'] ?? '');
    $targetType  = $_POST['target_type'] ?? 'custom';

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

    $recipientsToSend = [];
    if ($targetType === 'custom') {
        $customEmail = filter_var($_POST['custom_email'] ?? '', FILTER_VALIDATE_EMAIL);
        $customName  = trim($_POST['custom_name'] ?? 'Member');
        if ($customEmail) {
            $recipientsToSend[] = ['name' => $customName, 'email' => $customEmail];
        }
    } elseif (isset($recipientGroups[$targetType])) {
        $recipientsToSend = $recipientGroups[$targetType];
    }

    if ($senderEmail && !empty($recipientsToSend) && $subject && $rawBody) {
        $successCount = 0;
        $failCount = 0;

        foreach ($recipientsToSend as $recipient) {
            $personalizedBody = str_replace(
                ['{{name}}', '{{email}}', '{{date}}'],
                [htmlspecialchars($recipient['name']), htmlspecialchars($recipient['email']), date('F j, Y')],
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
                    $recipient['name'],
                    $subject,
                    $htmlEmail,
                    $attachments,
                    $senderEmail,
                    $senderName
                );
                (!empty($result['success'])) ? $successCount++ : $failCount++;
            } else {
                // Fallback standard PHP mail if sendEmail helper is unavailable
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: {$senderName} <{$senderEmail}>\r\n";
                mail($recipient['email'], $subject, $htmlEmail, $headers) ? $successCount++ : $failCount++;
            }
        }

        $message = "Dispatch complete: <strong>{$successCount}</strong> sent successfully, <strong>{$failCount}</strong> failed.";
        $statusClass = $failCount > 0 ? 'text-danger' : 'text-success';
    } else {
        $message = "Please check all required fields and verify recipients.";
        $statusClass = "text-danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Dispatcher - NBBTM CMS</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
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

  <div class="card" style="max-width: 900px; margin: 0 auto 2rem auto;">
    <h3>✉️ Admin Email Dispatcher</h3>

    <form method="POST" enctype="multipart/form-data" style="max-width: 100%; box-shadow: none; padding: 0;">
      <div class="form-grid-section-12">
        
        <div class="field-group" style="--colspan: 6;">
          <label for="sender_email"><strong>Sender Identity</strong></label>
          <select name="sender_email" id="sender_email" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%; height: 38px;" required>
            <?php foreach ($verifiedSenders as $email => $label): ?>
              <option value="<?= htmlspecialchars($email) ?>">
                <?= htmlspecialchars($label) ?> &lt;<?= htmlspecialchars($email) ?>&gt;
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field-group" style="--colspan: 6;">
          <label for="target_type"><strong>Recipient Audience</strong></label>
          <select name="target_type" id="target_type" onchange="toggleCustomFields(this.value)" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%; height: 38px;">
            <option value="custom">Single Custom Recipient</option>
            <option value="contacts">All Active Contacts (<?= count($recipientGroups['contacts']) ?> recipients)</option>
            <option value="system_users">All System Users (<?= count($recipientGroups['system_users']) ?> recipients)</option>
          </select>
        </div>

      </div>

      <!-- Single Custom Recipient Fields -->
      <div id="custom-recipient-fields" class="form-grid-section-12" style="margin-top: 1rem;">
        <div class="field-group" style="--colspan: 6;">
          <label for="custom_name"><strong>Recipient Name</strong></label>
          <input type="text" name="custom_name" id="custom_name" placeholder="First Last" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
        </div>
        <div class="field-group" style="--colspan: 6;">
          <label for="custom_email"><strong>Recipient Email</strong></label>
          <input type="email" name="custom_email" id="custom_email" placeholder="recipient@example.com" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
        </div>
      </div>

      <div class="field-group" style="margin-top: 1rem;">
        <label for="subject"><strong>Subject Line</strong></label>
        <input type="text" name="subject" id="subject" required placeholder="Ministry Announcement" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
      </div>

      <div class="field-group" style="margin-top: 1rem;">
        <label for="body_template"><strong>Email Body</strong></label>
        <textarea name="body_template" id="body_template" rows="8" required placeholder="Hello {{name}},&#10;&#10;We are pleased to inform you..." style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%; font-family: inherit;"></textarea>
        <div class="tag-hint">
          Dynamic placeholders: <code>{{name}}</code>, <code>{{email}}</code>, <code>{{date}}</code>
        </div>
      </div>

      <div class="field-group" style="margin-top: 1rem;">
        <label for="attachments"><strong>Attachments (Optional)</strong></label>
        <input type="file" name="attachments[]" id="attachments" multiple style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; width: 100%;">
      </div>

      <div style="margin-top: 1.5rem;">
        <button type="submit" class="btn btn-primary" style="width: 100%;">Send Broadcast Email</button>
      </div>
    </form>
  </div>
</main>

<?php include_once __DIR__ . '/include/footer.php'; ?>

<script>
function toggleCustomFields(val) {
  const customDiv = document.getElementById('custom-recipient-fields');
  customDiv.style.display = (val === 'custom') ? 'grid' : 'none';
}
</script>
</body>
</html>