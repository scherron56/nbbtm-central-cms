<?php
// lookupZip.php
require_once 'config/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// Configured default multi-state region
$defaultStates = ['MN', 'WI'];

// 1. Fetch cities for a requested state or all default states
if ($action === 'getCities') {
    $requestedState = trim($_GET['state'] ?? '');

    if (!empty($requestedState)) {
        // Specific single state[cite: 5]
        $stmt = $db->prepare("SELECT DISTINCT city, state_code FROM nbbtm_zipcodes WHERE state_code = ? ORDER BY city ASC");
        $stmt->bind_param("s", $requestedState);
    } else {
        // Multi-state fallback[cite: 5]
        $placeholders = implode(',', array_fill(0, count($defaultStates), '?'));
        $types = str_repeat('s', count($defaultStates));
        $stmt = $db->prepare("SELECT DISTINCT city, state_code FROM nbbtm_zipcodes WHERE state_code IN ($placeholders) ORDER BY state_code ASC, city ASC");
        $stmt->bind_param($types, ...$defaultStates);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $cities = [];
    while ($row = $res->fetch_assoc()) {
        $cities[] = [
            'city'  => $row['city'],
            'state' => $row['state_code']
        ];
    }
    $stmt->close();
    echo json_encode(['status' => 'success', 'cities' => $cities]);
    exit;
}

// 2. Fetch ZIP code for a selected City & State
if ($action === 'getZip') {
    $city  = trim($_GET['city'] ?? '');
    $state = trim($_GET['state'] ?? '');

    if (!empty($city) && !empty($state)) {
        $stmt = $db->prepare("SELECT zipcodes FROM nbbtm_zipcodes WHERE city = ? AND state_code = ? LIMIT 1");
        $stmt->bind_param("ss", $city, $state);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        $zipcode = '';
        if ($row && !empty($row['zipcodes'])) {
            $zips = explode(',', $row['zipcodes']);
            $zipcode = trim($zips[0]);
        }

        echo json_encode([
            'status'  => 'success',
            'found'   => (bool)$row,
            'zipcode' => $zipcode
        ]);
        exit;
    }
}

// 3. Reverse Lookup: Fetch City & State by entering a 5-digit ZIP
if ($action === 'getDetailsByZip') {
    $zip = trim($_GET['zip'] ?? '');
    if (!empty($zip)) {
        $searchTerm = '%' . $zip . '%';
        $stmt = $db->prepare("SELECT city, state_code, state_name, zipcodes FROM nbbtm_zipcodes WHERE zipcodes LIKE ? LIMIT 1");
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        $data = null;
        if ($row) {
            $data = [
                'city'       => $row['city'],
                'state_code' => $row['state_code'],
                'state_name' => $row['state_name'],
                'zipcode'    => $zip
            ];
        }

        echo json_encode([
            'status' => 'success',
            'found'  => (bool)$row,
            'data'   => $data
        ]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action or parameters.']);

if (isset($db) && $db instanceof mysqli) {
    mysqli_close($db);
}