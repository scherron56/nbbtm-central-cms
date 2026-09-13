<?php
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/document_binary.php';

if (!canManageMemberDocuments()) {
    header('Location: ./index.php');
    exit;
}

$message = '';
$error = '';

if (isset($_SESSION['admin_documents_message'])) {
    $message = $_SESSION['admin_documents_message'];
    unset($_SESSION['admin_documents_message']);
}
if (isset($_SESSION['admin_documents_error'])) {
    $error = $_SESSION['admin_documents_error'];
    unset($_SESSION['admin_documents_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'delete') {
            $documentId = (int)($_POST['document_id'] ?? 0);
            if ($documentId <= 0) {
                $error = 'Invalid document selected.';
            } else {
                $stmt = $db->prepare("DELETE FROM document_lib WHERE document_id = ? AND entity_type = 'member'");
                $stmt->bind_param('i', $documentId);
                $stmt->execute();
                $message = 'Document deleted successfully.';
                $stmt->close();
            }
        } elseif ($action === 'upload') {
            $shortName = trim($_POST['document_short_name'] ?? '');
            $file = $_FILES['document'] ?? null;

            if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0) {
                $error = 'Please select a valid document to upload.';
            } elseif ($shortName === '' || strlen($shortName) > 50) {
                $error = 'Enter a document name between 1 and 50 characters.';
            } else {
                $documentName = basename($file['name']);
                $documentMime = mime_content_type($file['tmp_name']) ?: ($file['type'] ?: 'application/octet-stream');
                $documentSize = (int)$file['size'];
                $documentData = file_get_contents($file['tmp_name']);

                if ($documentData === false) {
                    $error = 'The document could not be read.';
                } else {
                    try {
                        if (isDocxDocument($documentName, $documentMime)) {
                            $documentData = normalizeDocxData($documentData);
                            $documentSize = strlen($documentData);
                        }
                        $stmt = $db->prepare("INSERT INTO document_lib (entity_type, entity_id, document_short_name, document_name, document_mime, document_size, document_data, uploaded_at) VALUES ('member', 0, ?, ?, ?, ?, ?, NOW())");
                        $nullDocumentData = null;
                        $stmt->bind_param('sssib', $shortName, $documentName, $documentMime, $documentSize, $nullDocumentData);
                        if (!$stmt->send_long_data(4, $documentData)) {
                            throw new RuntimeException('The document data could not be transferred to storage.');
                        }
                        $stmt->execute();
                        $documentId = $db->insert_id;
                        $stmt->close();

                        $verifyStmt = $db->prepare('SELECT OCTET_LENGTH(document_data) AS stored_size FROM document_lib WHERE document_id = ?');
                        $verifyStmt->bind_param('i', $documentId);
                        $verifyStmt->execute();
                        $storedDocument = $verifyStmt->get_result()->fetch_assoc();
                        $verifyStmt->close();
                        if (!$storedDocument || (int)$storedDocument['stored_size'] !== $documentSize) {
                            throw new RuntimeException('The uploaded document was truncated while being saved. Please try again.');
                        }
                        $message = 'Document uploaded successfully.';
                    } catch (Throwable $exception) {
                        error_log('Member document upload failed: ' . $exception->getMessage());
                        $error = 'The document could not be processed: ' . $exception->getMessage();
                    }
                }
            }
        }
    } catch (mysqli_sql_exception $exception) {
        $error = 'The document could not be saved: ' . $exception->getMessage();
    }

    if ($message !== '' || $error !== '') {
        $_SESSION['admin_documents_message'] = $message;
        $_SESSION['admin_documents_error'] = $error;
        header('Location: admin_documents.php');
        exit;
    }
}

$documents = [];
$result = $db->query("SELECT document_id, document_short_name, document_name, document_mime, document_size, uploaded_at FROM document_lib WHERE entity_type = 'member' ORDER BY uploaded_at DESC, document_id DESC");
if ($result) {
    $documents = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NBBTM Documents - Administration</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .document-actions {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      flex-wrap: wrap;
    }
    .document-actions form {
      display: inline-flex;
      width: auto;
      max-width: none;
      margin: 0;
      padding: 0;
      background: none;
      box-shadow: none;
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/include/header.php'; ?>
<main class="dashboard-container">
  <h2>NBBTM Documents</h2>
  <?php if ($message): ?><div class="card" style="color:#166534; margin-bottom:1rem;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="card" style="color:#991b1b; margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:1.5rem;">
    <h3>Upload Member Document</h3>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <label for="document_short_name">Document name</label>
      <input id="document_short_name" name="document_short_name" maxlength="50" required>
      <label for="document" style="display:block; margin-top:1rem;">File</label>
      <input id="document" type="file" name="document" required>
      <button type="submit" class="btn btn-primary" style="margin-top:1rem;">Upload Document</button>
    </form>
  </div>

  <div class="card">
    <h3>Available Member Documents</h3>
    <?php if (!$documents): ?>
      <p>No documents have been uploaded.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Name</th><th>Size</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($documents as $document): ?>
          <tr>
            <td><?= htmlspecialchars($document['document_short_name']) ?></td>
            <td><?= number_format(((int)$document['document_size']) / 1024, 1) ?> KB</td>
            <td class="document-actions">
              <a class="btn btn-sm btn-secondary" href="include/document_reader.php?document_id=<?= (int)$document['document_id'] ?>" target="_blank">Read</a>
              <a class="btn btn-sm btn-primary" href="include/download_document.php?document_id=<?= (int)$document['document_id'] ?>">Download</a>
              <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="document_id" value="<?= (int)$document['document_id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
