<?php
require_once 'config/db.php';

$username = 'admin';
$newPassword = 'Password123!';
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $db->prepare("UPDATE system_users SET password_hash = ? WHERE username = ?");
$stmt->bind_param("ss", $newHash, $username);

if ($stmt->execute()) {
    echo "Password updated successfully!";
} else {
    echo "Error updating password.";
}
?>