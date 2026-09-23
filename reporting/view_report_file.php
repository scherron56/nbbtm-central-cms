<?php
require_once __DIR__ . '/../include/auth.php';

if (!isAdmin()) {
    http_response_code(403);
    exit('Access denied.');
}

$fileName = basename($_GET['file'] ?? '');
$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($extension, ['jrxml', 'jasper'], true)) {
    http_response_code(404);
    exit('Report file not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($fileName) ?> - Report File Viewer</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    pre#file-content {
      background: #0f172a;
      color: #e2e8f0;
      padding: 1rem;
      border-radius: 6px;
      overflow: auto;
      white-space: pre-wrap;
      word-break: break-word;
      max-height: 75vh;
    }
  </style>
</head>
<body>
<main class="dashboard-container">
  <h2><?= htmlspecialchars($fileName) ?></h2>
  <p><a href="report_files.php">&larr; Back to Saved Reports</a></p>
  <pre id="file-content">Loading...</pre>
</main>
<script>
  fetch('report_file.php?file=<?= rawurlencode($fileName) ?>')
    .then(async response => {
      const text = await response.text();
      if (!response.ok) {
        throw new Error(text || 'Unable to load report file.');
      }
      return text;
    })
    .then(text => {
      document.getElementById('file-content').textContent = text;
    })
    .catch(error => {
      document.getElementById('file-content').textContent = 'Error: ' + (error.message || error);
    });
</script>
</body>
</html>
