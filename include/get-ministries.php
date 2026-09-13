<?php
// api/get-ministries.php
header('Content-Type: application/json');

$mysqli = new mysqli('localhost', 'username', 'password', 'your_database');

if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $mysqli->connect_error]);
    exit();
}

$mysqli->set_charset('utf8mb4');

// Query options for the dropdown
$sql = "SELECT ministry_id, ministry_name FROM ministries ORDER BY ministry_name ASC";
$result = $mysqli->query($sql);

if ($result) {
    $data = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($data);
    $result->free();
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Query error: ' . $mysqli->error]);
}

$mysqli->close();