<?php
// download_document.php
require_once __DIR__ . '/include/auth.php';

$eventId = (int)($_GET['prg_evnt_id'] ?? 0);
if ($eventId <= 0) {
    http_response_code(400);
    exit('Invalid Event ID.');
}

$stmt = $db->prepare("
    SELECT document_name, document_mime, document_size, document_data 
    FROM programs_events 
    WHERE prg_evnt_id = ? AND document_data IS NOT NULL
");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(404);
    exit('Document not found.');
}

$stmt->bind_result($docName, $docMime, $docSize, $docData);
$stmt->fetch();
$stmt->close();

// Clear any buffered output
if (ob_get_level()) {
    ob_end_clean();
}

// Send appropriate download/preview headers
header('Content-Type: ' . ($docMime ?: 'application/octet-stream'));
header('Content-Length: ' . $docSize);
header('Content-Disposition: inline; filename="' . basename($docName) . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output raw file bytes
echo $docData;
exit;