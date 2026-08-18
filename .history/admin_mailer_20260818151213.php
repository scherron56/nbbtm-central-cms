<?php
// admin_mailer.php
require_once __DIR__ . '/include/auth.php';

// Security check: Block anyone who is not an authenticated Admin
if (!isAdmin()) {
    header('Location: ./index.php');
    exit;
}

require_once __DIR__ . '/send_email.php';

// Fetch recipient groups using active MySQLi $db connection
$recipientGroups = [
    'contacts'     => [],
    'system_users' => []
];

// 1. Fetch active contacts from database
$resContacts = $db->query("SELECT contact_id, CONCAT(COALESCE(c_first_name,''), ' ', COALESCE(c_last_name,'')) AS name, c_email AS email FROM contacts WHERE c_email IS NOT NULL AND c_email != ''");
if ($resContacts) {
    while ($row = $resContacts->fetch_assoc()) {
        $recipientGroups['contacts'][] = $row;
    }
}

// 2. Fetch active system users from database
$resUsers = $db->query("SELECT user_id, username AS name, email FROM system_users WHERE is_active = 1 AND email IS NOT NULL AND email != ''");
if ($resUsers) {
    while ($row = $resUsers->fetch_assoc()) {
        $recipientGroups['system_users'][] = $row;
    }
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
    $senderName  = $verifiedSenders[$senderEmail] ?? $_ENV['FROM_NAME'];
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

            $result = sendEmail(
                $recipient['email'],
                $recipient['name'],
                $subject,
                $htmlEmail,
                $attachments,
                $senderEmail,
                $senderName
            );

            $result['success'] ? $successCount++ : $failCount++;
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
    <title>Email Dispatcher - NBBTM Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .admin-wrapper {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
            width: 100%;
        }
        .admin-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .admin-card h2 {
            margin-top: 0;
            color: #28089a;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.5rem;
        }
        .alert-box {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
        }
        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .form-row .field-group {
            flex: 1;
        }
        .tag-hint {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 4px;
        }
        .tag-hint code {
            background: #f1f5f9;
            padding: 2px 4px;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/header.php'; ?>

<main class="admin-wrapper">
    <div class="admin-card">
        <h2>✉️ Admin Email Dispatcher</h2>

        <?php if ($message): ?>
            <div class="alert-box <?= $statusClass ?>"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="field-group">
                    <label for="sender_email"><strong>Sender Identity</strong></label>
                    <select name="sender_email" id="sender_email" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;" required>
                        <?php foreach ($verifiedSenders as $email => $label): ?>
                            <option value="<?= htmlspecialchars($email) ?>">
                                <?= htmlspecialchars($label) ?> &lt;<?= htmlspecialchars($email) ?>&gt;
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-group">
                    <label for="target_type"><strong>Recipient Audience</strong></label>
                    <select name="target_type" id="target_type" onchange="toggleCustomFields(this.value)" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
                        <option value="custom">Single Custom Recipient</option>
                        <option value="contacts">All Active Contacts (<?= count($recipientGroups['contacts']) ?> recipients)</option>
                        <option value="system_users">All System Users (<?= count($recipientGroups['system_users']) ?> recipients)</option>
                    </select>
                </div>
            </div>

            <div id="custom-recipient-fields" class="form-row">
                <div class="field-group">
                    <label for="custom_name"><strong>Recipient Name</strong></label>
                    <input type="text" name="custom_name" id="custom_name" placeholder="First Last" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
                </div>
                <div class="field-group">
                    <label for="custom_email"><strong>Recipient Email</strong></label>
                    <input type="email" name="custom_email" id="custom_email" placeholder="recipient@example.com" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
                </div>
            </div>

            <div class="field-group" style="margin-bottom: 1rem;">
                <label for="subject"><strong>Subject Line</strong></label>
                <input type="text" name="subject" id="subject" required placeholder="Ministry Announcement" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
            </div>

            <div class="field-group" style="margin-bottom: 1rem;">
                <label for="body_template"><strong>Email Body</strong></label>
                <textarea name="body_template" id="body_template" rows="8" required placeholder="Hello {{name}},&#10;&#10;We are pleased to inform you..." style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%; font-family: inherit;"></textarea>
                <div class="tag-hint">
                    Dynamic placeholders: <code>{{name}}</code>, <code>{{email}}</code>, <code>{{date}}</code>
                </div>
            </div>

            <div class="field-group" style="margin-bottom: 1.5rem;">
                <label for="attachments"><strong>Attachments (Optional)</strong></label>
                <input type="file" name="attachments[]" id="attachments" multiple style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; width: 100%;">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%;">Send Broadcast Email</button>
        </form>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script>
function toggleCustomFields(val) {
    document.getElementById('custom-recipient-fields').style.display = (val === 'custom') ? 'flex' : 'none';
}
</script>
</body>
</html>