<?php
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../include/auth.php';
requireAdmin();

if (!isset($db) || !($db instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['error' => 'The report parameter data is unavailable because the database connection could not be initialized.']);
    exit;
}

$reportKey = trim($_GET['report_key'] ?? '');

if ($reportKey === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing report_key parameter.']);
    exit;
}

try {
    $stmt = $db->prepare(
        "SELECT
            p.param_name AS name,
            COALESCE(NULLIF(p.lbl_param_name, ''), p.param_name) AS label,
            COALESCE(
                NULLIF(p.control_type, ''),
                CASE p.param_type
                    WHEN 'int' THEN 'number'
                    WHEN 'float' THEN 'number'
                    WHEN 'bool' THEN 'checkbox'
                    WHEN 'date' THEN 'date'
                    ELSE 'text'
                END
            ) AS type,
            p.param_type,
            p.static_options AS options,
            p.default_value,
            p.placeholder,
            p.is_required AS required
        FROM app_report_parameters p
        INNER JOIN app_reports r ON r.id = p.report_id
        WHERE r.report_key = ? AND r.is_active = 1
        ORDER BY p.id ASC"
    );
    $stmt->bind_param('s', $reportKey);
    $stmt->execute();
    $parameters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($parameters as &$parameter) {
        $parameter['required'] = (bool) $parameter['required'];

        if ($parameter['options'] !== null && $parameter['options'] !== '') {
            $parameter['options'] = json_decode($parameter['options'], true, 512, JSON_THROW_ON_ERROR);
        } else {
            unset($parameter['options']);
        }
    }
    unset($parameter);

    echo json_encode([
        'report_key' => $reportKey,
        'parameters' => $parameters,
    ]);
} catch (mysqli_sql_exception | JsonException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load report parameter definitions.']);
}