<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/auth.php';
requireMemberDocuments();

$documents = [];
$result = $db->query("SELECT document_id, document_short_name, document_name, document_mime, document_size FROM document_lib WHERE entity_type = 'member' ORDER BY document_short_name, document_id");
if ($result) {
    $documents = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NBBTM Documents</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .document-list {
      display: grid;
      gap: 0.75rem;
    }
    .document-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: 1rem 1.25rem;
      border-left: 5px solid #2563eb;
      border-radius: 6px;
      background: #ffffff;
      box-shadow: 0 2px 8px rgba(37, 99, 235, 0.12);
    }
    .document-details {
      min-width: 0;
    }
    .document-label {
      display: block;
      color: #2563eb;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }
    .document-name {
      color: #475569;
      overflow-wrap: anywhere;
    }
    .document-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      flex-shrink: 0;
    }
    @media (max-width: 640px) {
      .document-row {
        align-items: flex-start;
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/include/header.php'; ?>
<main class="dashboard-container">
  <h2>NBBTM Documents</h2>
  <?php if (!$documents): ?>
    <div class="card"><p>No member documents are currently available.</p></div>
  <?php else: ?>
    <div class="card">
      <div class="document-list">
        <?php foreach ($documents as $document): ?>
          <div class="document-row">
            <div class="document-details">
              <span class="document-label"><?= htmlspecialchars($document['document_short_name']) ?></span>
              <span class="document-name"><?= htmlspecialchars($document['document_name']) ?></span>
            </div>
            <div class="document-actions">
              <a class="btn btn-sm btn-secondary" href="include/document_reader.php?document_id=<?= (int)$document['document_id'] ?>" target="_blank">Read Online</a>
              <a class="btn btn-sm btn-primary" href="include/download_document.php?document_id=<?= (int)$document['document_id'] ?>">Download</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
