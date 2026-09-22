<?php
require_once __DIR__ . '/auth.php';
/** @var mysqli $db Provided globally by include/auth.php -> config/db.php */
require_once __DIR__ . '/document_binary.php';
requireDocumentAccess();

$documentId = (int)($_GET['document_id'] ?? 0);
if ($documentId <= 0) {
    http_response_code(400);
    exit('Invalid document ID.');
}

$scope = isMember() ? " AND entity_type = 'member'" : '';
$stmt = $db->prepare("SELECT document_name, document_mime FROM document_lib WHERE document_id = ?" . $scope);
$stmt->bind_param('i', $documentId);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();
$stmt->close();

if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

$documentName = $document['document_name'];
$documentMime = strtolower((string)$document['document_mime']);
$isDocx = isDocxDocument($documentName, $documentMime);
$streamUrl = 'file_loader.php?document_id=' . $documentId;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($documentName) ?> - NBBTM Documents</title>

  <!-- DOCX-to-HTML conversion -->
  <script src="https://unpkg.com/mammoth@1.8.0/mammoth.browser.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

  <style>
    #viewer-container {
      width: 100%;
      height: 750px;
      border: 1px solid #ccc;
      margin-top: 15px;
      overflow: auto;
      background-color: #f9f9f9;
    }
    iframe {
      width: 100%;
      height: 100%;
      border: none;
    }
  </style>
</head>
<body>

  <h2><?= htmlspecialchars($documentName) ?></h2>
  <div id="viewer-container"></div>

  <script>
    async function loadFromDatabase() {
      const container = document.getElementById('viewer-container');
      container.innerHTML = ''; // Clear viewer

      const streamUrl = <?= json_encode($streamUrl) ?>;
      const isDocx = <?= $isDocx ? 'true' : 'false' ?>;
      const isPdf = <?= isPdfDocument($documentName, $documentMime) ? 'true' : 'false' ?>;

      if (isPdf) {
        try {
          pdfjsLib.GlobalWorkerOptions.workerSrc =
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
          const response = await fetch(streamUrl);
          if (!response.ok) throw new Error('Failed to load PDF');
          const pdf = await pdfjsLib.getDocument({data: await response.arrayBuffer()}).promise;
          for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
            const page = await pdf.getPage(pageNumber);
            const viewport = page.getViewport({scale: 1.25});
            const canvas = document.createElement('canvas');
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            canvas.style.display = 'block';
            canvas.style.margin = '0 auto 16px';
            container.appendChild(canvas);
            await page.render({canvasContext: canvas.getContext('2d'), viewport}).promise;
          }
        } catch (error) {
          container.innerHTML = `<p style="color:red; padding:20px;">Error rendering PDF: ${error.message}</p>`;
        }
      } else if (!isDocx) {
        // Native iframe can directly execute and view the streamed endpoint
        const iframe = document.createElement('iframe');
        iframe.src = streamUrl;
        container.appendChild(iframe);

      } else {
        // Fetch the DOCX and convert it to browser-renderable HTML.
        try {
          const response = await fetch(streamUrl);
          if (!response.ok) throw new Error('Failed to load document');
          
          const documentData = await response.arrayBuffer();
          const result = await mammoth.convertToHtml({ arrayBuffer: documentData });
          container.innerHTML = result.value;
          if (result.messages.length > 0) {
            console.warn('DOCX conversion messages:', result.messages);
          }
        } catch (error) {
          container.innerHTML = `<p style="color:red; padding:20px;">Error rendering document: ${error.message}</p>`;
        }
      }
    }

    loadFromDatabase();
  </script>

</body>
</html>