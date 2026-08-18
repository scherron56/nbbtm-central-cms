<?php
// download_document.php
require_once __DIR__ . '/include/auth.php';

$eventId = (int)($_GET['prg_evnt_id'] ?? 0);
if ($eventId <= 0) {
    http_response_code(400);
    exit('Invalid Event ID.');
}

// Fetch binary data and metadata from MySQL
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
    exit('Document not found in database.');
}

$stmt->bind_result($docName, $docMime, $docSize, $docData);
$stmt->fetch();
$stmt->close();

// Clear any buffered output to avoid corrupting the binary download
if (ob_get_level()) {
    ob_end_clean();
}

$mimeType = !empty($docMime) ? $docMime : 'application/octet-stream';
$fileName = !empty($docName) ? basename($docName) : 'event_document';

// Send file headers
header('Content-Type: ' . $mimeType);
if ($docSize > 0) {
    header('Content-Length: ' . $docSize);
}
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output binary stream
echo $docData;
exit;