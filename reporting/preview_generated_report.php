<?php
require_once __DIR__ . '/../include/auth.php';

if (!isAdmin()) {
    http_response_code(403);
    exit('Access denied.');
}

$fileName = basename($_GET['file'] ?? '');
if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(404);
    exit('Generated report not found.');
}
$streamUrl = 'view_generated_report.php?file=' . rawurlencode($fileName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($fileName) ?> - Generated Report Viewer</title>
  <link rel="stylesheet" href="../css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <style>
    .viewer-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.75rem;
      margin: 1rem 0;
      padding: 0.75rem 1rem;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
    }
    .viewer-toolbar .nav-group {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .viewer-toolbar button {
      padding: 0.4rem 0.75rem;
      font-size: 0.875rem;
      cursor: pointer;
      border-radius: 4px;
      border: 1px solid #cbd5e1;
      background: #fff;
    }
    .viewer-toolbar button:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    #viewer-container {
      width: 100%;
      min-height: 80vh;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      background: #64748b;
      overflow: auto;
      padding: 1.5rem;
      box-sizing: border-box;
      text-align: center;
    }
    .pdf-page-canvas {
      display: block;
      margin: 0 auto 1.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.25), 0 2px 4px -2px rgba(0, 0, 0, 0.25);
      background: #ffffff;
      max-width: 100%;
      height: auto;
    }
    #pdf-status {
      padding: 2rem;
      color: #ffffff;
      font-size: 1rem;
    }
  </style>
</head>
<body>
<main class="dashboard-container">
  <h2><?= htmlspecialchars($fileName) ?></h2>
  <p><a href="generated_reports.php">&larr; Back to Generated Reports</a></p>

  <div class="viewer-toolbar">
    <div class="nav-group">
      <button type="button" id="zoom-out" title="Zoom Out">&minus; Zoom</button>
      <span id="zoom-level" style="font-size: 0.875rem; min-width: 3.5rem; text-align: center;">100%</span>
      <button type="button" id="zoom-in" title="Zoom In">&plus; Zoom</button>
      <button type="button" id="zoom-fit">Fit Width</button>
    </div>
    <div style="margin-left: auto; display: flex; gap: 0.5rem;">
      <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars($streamUrl) ?>" target="_blank" download="<?= htmlspecialchars($fileName) ?>">Download PDF</a>
      <a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars($streamUrl) ?>" target="_blank">Open in Browser Tab</a>
    </div>
  </div>

  <div id="viewer-container">
    <div id="pdf-status">Loading report...</div>
  </div>
</main>

<script>
  pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

  const streamUrl = <?= json_encode($streamUrl) ?>;
  const container = document.getElementById('viewer-container');
  const statusEl = document.getElementById('pdf-status');
  const zoomLevelEl = document.getElementById('zoom-level');

  let pdfDoc = null;
  let currentScale = 1.25;
  let isRendering = false;

  async function renderPages() {
    if (!pdfDoc || isRendering) return;
    isRendering = true;

    // Clear existing canvases
    const canvases = container.querySelectorAll('.pdf-page-canvas');
    canvases.forEach(c => c.remove());
    statusEl.style.display = 'none';

    try {
      for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
        const page = await pdfDoc.getPage(pageNum);
        const viewport = page.getViewport({ scale: currentScale });

        const canvas = document.createElement('canvas');
        canvas.className = 'pdf-page-canvas';
        const context = canvas.getContext('2d');
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        container.appendChild(canvas);

        await page.render({
          canvasContext: context,
          viewport: viewport
        }).promise;
      }
    } catch (err) {
      console.error('Error rendering page:', err);
    } finally {
      isRendering = false;
    }
  }

  function updateZoom(newScale) {
    currentScale = Math.min(Math.max(newScale, 0.5), 3.0);
    zoomLevelEl.textContent = Math.round((currentScale / 1.25) * 100) + '%';
    renderPages();
  }

  document.getElementById('zoom-in').addEventListener('click', () => {
    updateZoom(currentScale + 0.25);
  });

  document.getElementById('zoom-out').addEventListener('click', () => {
    updateZoom(currentScale - 0.25);
  });

  document.getElementById('zoom-fit').addEventListener('click', async () => {
    if (!pdfDoc) return;
    const page = await pdfDoc.getPage(1);
    const unscaledViewport = page.getViewport({ scale: 1.0 });
    const availableWidth = container.clientWidth - 64; // accounting for padding
    const fitScale = availableWidth / unscaledViewport.width;
    updateZoom(fitScale);
  });

  // Load the PDF via PDF.js
  (async function loadPdf() {
    try {
      const response = await fetch(streamUrl);
      if (!response.ok) {
        throw new Error((await response.text()) || 'Failed to load report file.');
      }
      const data = await response.arrayBuffer();
      pdfDoc = await pdfjsLib.getDocument({ data: data }).promise;
      renderPages();
    } catch (error) {
      statusEl.textContent = 'Error: ' + (error.message || error);
      statusEl.style.color = '#fca5a5';
      statusEl.style.display = 'block';
    }
  })();
</script>
</body>
</html>
