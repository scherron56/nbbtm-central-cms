<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/document_binary.php';
requireDocumentAccess();

$documentId = isset($_GET['document_id']) ? (int)$_GET['document_id'] : 0;

if ($documentId <= 0) {
    http_response_code(400);
    die('Invalid Document ID.');
}

$scope = isMember() ? " AND entity_type = 'member'" : '';
$stmt = $db->prepare("SELECT document_name, document_mime, document_size, document_data FROM document_lib WHERE document_id = ?" . $scope);
$stmt->bind_param("i", $documentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    die('Document not found.');
}

$document = $result->fetch_assoc();
$stmt->close();
$docName = $document['document_name'];
$docMime = $document['document_mime'];
$docData = $document['document_data'];
if (isDocxDocument($docName, $docMime)) {
    $docData = normalizeDocxData($docData);
    $docMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Description: File Transfer');
header('Content-Type: ' . ($docMime ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($docName) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
if ($docData !== null) {
    header('Content-Length: ' . strlen($docData));
}

echo $docData;
exit;