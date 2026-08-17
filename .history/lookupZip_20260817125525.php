<?php
require_once 'config/db.php';

header('Content-Type: application/json; charset=utf-8');

$zip  = isset($_GET['zip']) ? trim($_GET['zip']) : '';
$city = isset($_GET['city']) ? trim($_GET['city']) : '';

if (!empty($zip)) {
    // 1. Lookup by ZIP Code (fills City and State)
    $stmt = $db->prepare("SELECT city, state_code, state_name FROM us_zip_codes WHERE zip_code = ? LIMIT 1");
    $stmt->bind_param("s", $zip);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    echo json_encode($data ? ['found' => true, 'data' => $data] : ['found' => false]);
    exit;
}

if (!empty($city)) {
    // 2. Lookup by City autocomplete (returns matching cities, states, and zips)
    $citySearch = $city . '%';
    $stmt = $db->prepare("SELECT DISTINCT city, state_code, zip_code FROM us_zip_codes WHERE city LIKE ? ORDER BY city, state_code LIMIT 10");
    $stmt->bind_param("s", $citySearch);
    $stmt->execute();
    $result = $stmt->get_result();
    $matches = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['found' => count($matches) > 0, 'data' => $matches]);
    exit;
}

echo json_encode(['found' => false]);