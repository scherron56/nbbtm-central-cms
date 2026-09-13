<?php
require_once __DIR__ . '/config/db.php';

// Ensure this maintenance script cannot update a similarly named backup database.
$db->select_db(DB_NAME);
$databaseResult = $db->query('SELECT DATABASE() AS database_name');
$database = $databaseResult->fetch_assoc()['database_name'] ?? null;

if ($database !== DB_NAME) {
    http_response_code(500);
    exit('Refusing to update an unexpected database.');
}

$username = 'admin';
$newPassword = 'Password123!';
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $db->prepare("UPDATE nbbtm_users SET password_hash = ?, must_change_password = 0 WHERE username = ?");
$stmt->bind_param("ss", $newHash, $username);

if ($stmt->execute() && $stmt->affected_rows === 1) {
    $verifyStmt = $db->prepare("SELECT password_hash, is_active FROM nbbtm_users WHERE username = ?");
    $verifyStmt->bind_param("s", $username);
    $verifyStmt->execute();
    $user = $verifyStmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($newPassword, $user['password_hash'])) {
        http_response_code(500);
        exit('Password was updated, but verification failed.');
    }

    echo "Password updated and verified for {$username} in " . DB_NAME . ".";
} elseif ($stmt->affected_rows === 0) {
    http_response_code(404);
    echo "No user named '{$username}' was found.";
} else {
    http_response_code(500);
    echo "Error updating password.";
}
?>