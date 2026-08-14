<?php
// Enable detailed error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/send_email.php';
require_once __DIR__ . '/email_templates.php';

$message = '';
$statusClass = '';

// Check if form was submitted safely (handles missing REQUEST_METHOD in CLI/edge environments)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

    if ($name && $email) {
        // 1. Generate the HTML template
        $emailHtml = getWelcomeEmailTemplate($name);

        // 2. Send the Welcome Email via Brevo
        $result = sendEmail($email, $name, 'Welcome to Our App!', $emailHtml);

        if ($result['success']) {
            $message = "Registration successful! Check your inbox ($email) for a welcome message.";
            $statusClass = "success";
        } else {
            $message = "Account created, but email delivery failed. Error: " . $result['error'];
            $statusClass = "error";
        }
    } else {
        $message = "Please provide a valid name and email address.";
        $statusClass = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create an Account</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f0f2f5; }
        .card { background: white; padding: 2rem; border-radius: 8px; width: 100%; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: .5rem; font-weight: bold; }
        input { width: 100%; padding: .75rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: .75rem; background: #0066cc; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        button:hover { background: #0052a3; }
        .alert { padding: 1rem; margin-bottom: 1rem; border-radius: 4px; font-size: 0.9rem; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="card">
    <h2>Sign Up</h2>

    <?php if ($message): ?>
        <div class="alert <?= $statusClass ?>"><?= $message ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" required placeholder="Jane Doe">
        </div>
        
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required placeholder="jane@example.com">
        </div>

        <button type="submit">Create Account</button>
    </form>
</div>

</body>
</html>