<?php
// api/get-report-parameters.php
header('Content-Type: application/json');

$reportKey = $_GET['report_key'] ?? '';

if (empty($reportKey)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing report_key parameter']);
    exit();
}

$mysqli = new mysqli('localhost', 'username', 'password', 'your_database');

if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$mysqli->set_charset('utf8mb4');

// Query report parameters joined with the reports table, ordered by display_order
$sql = "SELECT 
            p.parameter_name AS name,
            p.label,
            p.control_type AS type,
            p.data_source_url,
            p.value_field,
            p.label_field,
            p.static_options AS options,
            p.default_value,
            p.placeholder,
            p.is_required AS required
        FROM report_parameters p
        JOIN reports r ON p.report_id = r.report_id
        WHERE r.report_key = ? AND r.is_active = 1
        ORDER BY p.display_order ASC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $reportKey);
$stmt->execute();
$result = $stmt->get_result();

$parameters = [];
while ($row = $result->fetch_assoc()) {
    // Cast boolean values for JS compatibility
    $row['required'] = (bool)$row['required'];
    
    // Decode static JSON options if present into a native PHP array
    if (!empty($row['options'])) {
        $row['options'] = json_decode($row['options'], true);
    } else {
        unset($row['options']);
    }

    // Clean up empty null values
    $parameters[] = array_filter($row, function($value) {
        return $value !== null;
    });
}

$stmt->close();
$mysqli->close();

// Output formatted array ready for renderReportControls() JS function
echo json_encode([
    'report_key' => $reportKey,
    'parameters' => $parameters
]);