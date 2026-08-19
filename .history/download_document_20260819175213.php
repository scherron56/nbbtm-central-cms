<?php
// download_document.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/auth.php';

$attachmentId = (int)($_GET['attachment_id'] ?? 0);
$entityType   = trim($_GET['entity_type'] ?? '');
$entityId     = (int)($_GET['entity_id'] ?? 0);
$categoryName = trim($_GET['category'] ?? '');

if ($attachmentId > 0) {
    $stmt = $db->prepare("
        SELECT a.document_name, a.document_mime, a.document_size, a.document_data 
        FROM app_attachments a
        WHERE a.attachment_id = ? AND a.document_data IS NOT NULL
    ");
    $stmt->bind_param("i", $attachmentId);
} elseif (!empty($entityType) && $entityId > 0 && !empty($categoryName)) {
    $stmt = $db->prepare("
        SELECT a.document_name, a.document_mime, a.document_size, a.document_data 
        FROM app_attachments a
        JOIN doc_categories c ON a.doc_category_id = c.doc_category_id
        WHERE a.entity_type = ? AND a.entity_id = ? AND c.category_name = ? AND a.document_data IS NOT NULL
        ORDER BY a.uploaded_at DESC LIMIT 1
    ");
    $stmt->bind_param("sis", $entityType, $entityId, $categoryName);
} else {
    http_response_code(400);
    exit('Invalid download parameters specified.');
}

$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(404);
    exit('Requested document was not found in the database.');
}

$stmt->bind_result($docName, $docMime, $docSize, $docData);
$stmt->fetch();
$stmt->close();

if (empty($docData)) {
    http_response_code(404);
    exit('Document payload is empty.');
}

if (ob_get_level()) {
    ob_end_clean();
}

$mimeType = !empty($docMime) ? $docMime : 'application/octet-stream';
$fileName = !empty($docName) ? basename($docName) : 'downloaded_document';

header('Content-Type: ' . $mimeType);
if ($docSize > 0) {
    header('Content-Length: ' . (int)$docSize);
}
header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $docData;
exit;