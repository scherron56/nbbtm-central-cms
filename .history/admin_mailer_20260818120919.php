<?php
session_start();

// --- 1. ADMIN AUTHORIZATION CHECK ---
// Adjust this session check to match your app's login/role logic
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // For demo/testing: Uncomment the line below to simulate being logged in as admin:
    // $_SESSION['user_role'] = 'admin';

    http_response_code(403);
    die('<h3 style="color: red; text-align: center; margin-top: 50px;">Only administrators can access this email dashboard.</h3>');
}

require_once __DIR__ . '/send_email.php';

// --- 2. CONFIGURATION & MOCK DATA (Replace with DB queries as needed) ---
$verifiedSenders = [
    'noreply@yourdomain.com' => 'System Notifications',
    'support@yourdomain.com' => 'Customer Support Team',
    'billing@yourdomain.com' => 'Billing Department',
    'updates@yourdomain.com' => 'Community & Updates'
];

$recipientGroups = [
    'newsletter' => [
        ['name' => 'Alice Johnson', 'email' => 'alice@example.com'],
        ['name' => 'Bob Smith',     'email' => 'bob@example.com'],
    ],
    'beta_testers' => [
        ['name' => 'Carol Williams', 'email' => 'carol@example.com'],
    ]
];

$message = '';
$statusClass = '';

// --- 3. HANDLE EMAIL DISPATCH ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senderEmail = filter_var($_POST['sender_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $senderName  = $verifiedSenders[$senderEmail] ?? $_ENV['FROM_NAME'];
    $subject     = trim($_POST['subject'] ?? '');
    $rawBody     = trim($_POST['body_template'] ?? '');
    $targetType  = $_POST['target_type'] ?? 'custom';

    // Handle File Attachments
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

    // Build recipient list
    $recipientsToSend = [];
    if ($targetType === 'custom') {
        $customEmail = filter_var($_POST['custom_email'] ?? '', FILTER_VALIDATE_EMAIL);
        $customName  = trim($_POST['custom_name'] ?? 'Valued User');
        if ($customEmail) {
            $recipientsToSend[] = ['name' => $customName, 'email' => $customEmail];
        }
    } elseif (isset($recipientGroups[$targetType])) {
        $recipientsToSend = $recipientGroups[$targetType];
    }

    // Process & Send
    if ($senderEmail && !empty($recipientsToSend) && $subject && $rawBody) {
        $successCount = 0;
        $failCount = 0;

        foreach ($recipientsToSend as $recipient) {
            // Replace placeholder template tags
            $personalizedBody = str_replace(
                ['{{name}}', '{{email}}', '{{date}}'],
                [htmlspecialchars($recipient['name']), htmlspecialchars($recipient['email']), date('F j, Y')],
                $rawBody
            );

            // Wrap in responsive HTML styling
            $htmlEmail = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;'>
                <h2 style='color: #333; border-bottom: 2px solid #0066cc; padding-bottom: 10px;'>" . htmlspecialchars($subject) . "</h2>
                <div style='font-size: 15px; line-height: 1.6; color: #444;'>" . nl2br($personalizedBody) . "</div>
                <hr style='border: none; border-top: 1px solid #eee; margin: 25px 0 10px 0;'>
                <p style='font-size: 12px; color: #888;'>Sent by " . htmlspecialchars($senderName) . "</p>
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

            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $message = "Broadcast completed: <strong>{$successCount}</strong> sent successfully, <strong>{$failCount}</strong> failed.";
        $statusClass = $failCount > 0 ? 'warning' : 'success';
    } else {
        $message = "Please verify all required fields and recipients.";
        $statusClass = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Email Dispatcher</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; margin: 0; padding: 30px; color: #1e293b; }
        .container { max-width: 760px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; font-size: 1.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; }
        .badge { background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; vertical-align: middle; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.95rem; }
        textarea { resize: vertical; min-height: 140px; font-family: inherit; }
        .tags { font-size: 0.8rem; color: #64748b; margin-top: 4px; }
        .tags code { background: #f1f5f9; padding: 2px 4px; border-radius: 4px; color: #0f172a; }
        .row { display: flex; gap: 15px; }
        .row .form-group { flex: 1; }
        button { background: #2563eb; color: white; border: none; padding: 12px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; width: 100%; font-size: 1rem; }
        button:hover { background: #1d4ed8; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem; }
        .alert.success { background: #dcfce7; color: #166534; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        .alert.warning { background: #fef9c3; color: #854d0e; }
    </style>
</head>
<body>

<div class="container">
    <h1>Email Broadcast Engine <span class="badge">ADMIN ONLY</span></h1>

    <?php if ($message): ?>
        <div class="alert <?= $statusClass ?>"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <!-- 1. SENDER SELECTION -->
        <div class="form-group">
            <label for="sender_email">Select Sender Identity</label>
            <select name="sender_email" id="sender_email" required>
                <?php foreach ($verifiedSenders as $email => $label): ?>
                    <option value="<?= htmlspecialchars($email) ?>">
                        <?= htmlspecialchars($label) ?> &lt;<?= htmlspecialchars($email) ?>&gt;
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 2. RECIPIENT SELECTION -->
        <div class="form-group">
            <label for="target_type">Recipient Audience</label>
            <select name="target_type" id="target_type" onchange="toggleCustomRecipient(this.value)">
                <option value="custom">Single Custom Recipient</option>
                <option value="newsletter">All Newsletter Subscribers (<?= count($recipientGroups['newsletter']) ?> users)</option>
                <option value="beta_testers">Beta Testing Group (<?= count($recipientGroups['beta_testers']) ?> users)</option>
            </select>
        </div>

        <!-- Custom Recipient Inputs (Toggled via JS) -->
        <div id="custom-recipient-fields" class="row">
            <div class="form-group">
                <label for="custom_name">Recipient Name</label>
                <input type="text" name="custom_name" id="custom_name" placeholder="John Doe">
            </div>
            <div class="form-group">
                <label for="custom_email">Recipient Email</label>
                <input type="email" name="custom_email" id="custom_email" placeholder="john@example.com">
            </div>
        </div>

        <!-- 3. SUBJECT -->
        <div class="form-group">
            <label for="subject">Subject Line</label>
            <input type="text" name="subject" id="subject" required placeholder="Important update regarding your account">
        </div>

        <!-- 4. TEMPLATE BODY BUILDER -->
        <div class="form-group">
            <label for="body_template">Email Body</label>
            <textarea name="body_template" id="body_template" required placeholder="Hi {{name}},&#10;&#10;We are writing to let you know..."></textarea>
            <div class="tags">
                Supported dynamic tags: <code>{{name}}</code>, <code>{{email}}</code>, <code>{{date}}</code>
            </div>
        </div>

        <!-- 5. ATTACHMENTS -->
        <div class="form-group">
            <label for="attachments">Add Attachments (Optional, Multi-select enabled)</label>
            <input type="file" name="attachments[]" id="attachments" multiple>
        </div>

        <button type="submit">Dispatch Broadcast</button>
    </form>
</div>

<script>
function toggleCustomRecipient(value) {
    const fields = document.getElementById('custom-recipient-fields');
    fields.style.display = (value === 'custom') ? 'flex' : 'none';
}
</script>

</body>
</html>