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
try {
    $stmt = $db->prepare("
        SELECT contact_id, first_name, last_name
        FROM contacts 
        WHERE first_name LIKE ? 
           OR last_name LIKE ? 
           OR CONCAT(first_name, ' ', last_name) LIKE ?
        ORDER BY last_name ASC, first_name ASC
        LIMIT 10
    ");
    
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $contacts = [];
    while ($row = $result->fetch_assoc()) {
        $contacts[] = [
            'id'         => $row['contact_id'],
            'first_name' => $row['first_name'],
            'last_name'  => $row['last_name']
        ];
    }
    
    echo json_encode($contacts);
} catch (Exception $e) {
    echo json_encode([]);
}