<?php
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/db.php';

if (!isset($db) || !($db instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['error' => 'The report parameter data is unavailable because the database connection could not be initialized.']);
    exit;
}

$reportKey = $_GET['report'] ?? '';

if (!$reportKey) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT p.param_name, p.lbl_param_name, p.param_type, p.is_required, p.default_value 
        FROM app_report_parameters p
        INNER JOIN app_reports r ON r.id = p.report_id
        WHERE r.report_key = ? AND r.is_active = 1
        ORDER BY p.id ASC
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the report parameter query.');
    }

    $stmt->bind_param('s', $reportKey);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error querying parameter definitions.']);
}