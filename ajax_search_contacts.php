<?php
// ajax_search_contacts.php
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$search_term = "%{$query}%";
$digits_only = preg_replace('/\D/', '', $query);
$phone_term  = "%{$digits_only}%";

try {
    $stmt = $db->prepare("
        SELECT contact_id, first_name, last_name, phone_1, c_email 
        FROM contacts 
        WHERE first_name LIKE ? 
           OR last_name LIKE ? 
           OR CONCAT(first_name, ' ', last_name) LIKE ?
           OR (c_email IS NOT NULL AND c_email LIKE ?)
           OR (? != '' AND phone_1 LIKE ?)
        ORDER BY last_name ASC, first_name ASC
        LIMIT 10
    ");
    
    $stmt->bind_param("ssssss", $search_term, $search_term, $search_term, $search_term, $digits_only, $phone_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $contacts = [];
    while ($row = $result->fetch_assoc()) {
        $contacts[] = [
            'id'         => $row['contact_id'],
            'first_name' => $row['first_name'],
            'last_name'  => $row['last_name'],
            'phone_1'    => $row['phone_1'],
            'c_email'    => $row['c_email'] ?? ''
        ];
    }
    
    echo json_encode($contacts);
} catch (Exception $e) {
    echo json_encode([]);
}