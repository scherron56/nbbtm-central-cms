<?php
require_once 'config/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$defaultState = 'MN';

// 1. Fetch all distinct Minnesota cities
if ($action === 'getCities') {
    $stmt = $db->prepare("SELECT DISTINCT city FROM us_zip_codes WHERE state_code = ? ORDER BY city ASC");
    $stmt->bind_param("s", $defaultState);
    $stmt->execute();
    $res = $stmt->get_result();
    $cities = [];
    while ($row = $res->fetch_assoc()) {
        $cities[] = $row['city'];
    }
    $stmt->close();
    echo json_encode(['status' => 'success', 'cities' => $cities]);
    exit;
}

// 2. Fetch ZIP code for a selected Minnesota City
if ($action === 'getZip') {
    $city = trim($_GET['city'] ?? '');
    if (!empty($city)) {
        $stmt = $db->prepare("SELECT zip_code FROM us_zip_codes WHERE city = ? AND state_code = ? LIMIT 1");
        $stmt->bind_param("ss", $city, $defaultState);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'found'  => (bool)$row,
            'zipcode'=> $row['zip_code'] ?? ''
        ]);
        exit;
    }
}

// 3. Reverse Lookup: Fetch Minnesota City from a 5-digit ZIP
if ($action === 'getCityByZip') {
    $zip = trim($_GET['zip'] ?? '');
    if (!empty($zip)) {
        $stmt = $db->prepare("SELECT city FROM us_zip_codes WHERE zip_code = ? AND state_code = ? LIMIT 1");
        $stmt->bind_param("ss", $zip, $defaultState);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'found'  => (bool)$row,
            'city'   => $row['city'] ?? ''
        ]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action or parameters.']);

if (isset($db) && $db instanceof mysqli) {
    mysqli_close($db);
}