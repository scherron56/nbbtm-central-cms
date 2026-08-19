<?php
// reset_admin.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

$username = 'admin';
$password = 'admin123';
$email    = 'nbbtmadmin@claricecraftedsolutions.org';
$fullName = 'System Administrator';
$role     = 'admin';

// Generate valid Bcrypt hash on the live server
$hash = password_hash($password, PASSWORD_BCRYPT);

// Check if user already exists
$check = $db->prepare("SELECT user_id FROM nbbtm_users WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
$res = $check->get_result();

if ($row = $res->fetch_assoc()) {
    // Update existing admin
    $stmt = $db->prepare("UPDATE nbbtm_users SET password_hash = ?, full_name = ?, email = ?, role = ?, is_active = 1, updated_at = NOW() WHERE username = ?");
    $stmt->bind_param("sssss", $hash, $fullName, $email, $role, $username);
    $stmt->execute();
    echo "<h2 style='color:green;'>✓ Admin account UPDATED successfully.</h2>";
} else {
    // Insert new admin
    $stmt = $db->prepare("INSERT INTO nbbtm_users (username, password_hash, full_name, email, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");
    $stmt->bind_param("sssss", $username, $hash, $fullName, $email, $role);
    $stmt->execute();
    echo "<h2 style='color:green;'>✓ Admin account CREATED successfully.</h2>";
}

// Verification check
$verify = password_verify($password, $hash);
echo "<p><strong>Username:</strong> <code>{$username}</code></p>";
echo "<p><strong>Password:</strong> <code>{$password}</code></p>";
echo "<p><strong>Stored Hash:</strong> <code>{$hash}</code></p>";
echo "<p><strong>Internal Hash Verification Test:</strong> " . ($verify ? "<span style='color:green;'>PASSED ✓</span>" : "<span style='color:red;'>FAILED ✗</span>") . "</p>";
echo "<p style='color:red;'><strong>Important:</strong> Delete this file (<code>reset_admin.php</code>) after logging in.</p>";