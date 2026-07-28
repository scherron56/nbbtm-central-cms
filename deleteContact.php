<?php
// $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';
header('Content-Type: application/json; charset=utf-8');

// CRITICAL FIX: Always initialize the errors array
$errors = [];

// 1. Verify Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errors[] = 'Invalid request method. Only POST is allowed.';
}

// 2. Validate Incoming Record ID
if (!isset($_POST['contact_id']) || empty(trim($_POST['contact_id']))) {
    $errors[] = 'Invalid or missing record ID.';
}

// 3. Halt and Return Errors if Validation Failed
if (!empty($errors)) {
    echo json_encode([
        'status' => 'error',
        'message' => implode(' ', $errors)
    ]);
    exit;
}

// Enforce strict integer casting for database safety
$contact_id = intval($_POST['contact_id']);

try {
    // 4. Execute Prepared Statement
    // Foreign key CASCADE will automatically delete linked ministry rows
    $stmt = $db->prepare("DELETE FROM contacts WHERE contact_id = ?");
    $stmt->bind_param("i", $contact_id);
    $stmt->execute();

    // 5. Check if the contact was successfully deleted
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Contact and associated records successfully deleted.'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Record not found or already deleted.'
        ]);
    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database operation failed: ' . $e->getMessage()
    ]);
}

exit;


