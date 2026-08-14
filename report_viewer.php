<?php
//THIS IS THE REPORT VIEWER FOR VBS EXAMPLE CONTROLLER
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/include/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Reports - NBBTM</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <style>
    .report-frame-container {
      width: 100%;
      height: 750px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      background: #f8fafc;
      margin-top: 20px;
    }
    iframe {
      width: 100%;
      height: 100%;
      border: none;
      border-radius: 8px;
    }
  </style>

  <script>
    $(document).ready(function() {
      // Populate dynamic session options
      $.ajax({
        url: 'class_controller.php',
        type: 'POST',
        data: { action: 'get_dropdowns' },
        dataType: 'json',
        success: function(data) {
          if (data.sessions) {
            $.each(data.sessions, function(i, item) {
              $('#vbs_sessions_id').append($('<option>', {
                value: item.vbs_sessions_id,
                text: item.vbs_year + ' - ' + item.vbs_theme
              }));
            });
          }
        }
      });

      // Handle Generate Button
      $('#btnGenerateReport').click(function() {
        let reportName = $('#report_select').val();
        let sessionId = $('#vbs_sessions_id').val();

        if (!reportName) {
          alert('Please select a report type.');
          return;
        }

        let reportUrl = `report_controller.php?action=export_pdf&report_name=${reportName}&vbs_sessions_id=${sessionId}`;
        
        // Render PDF directly inside the iframe viewer
        $('#reportFrame').attr('src', reportUrl);
      });
    });
  </script>
</head>
<body>
  <?php require_once("config/db.php"); ?>
  <?php include 'include/header.php'; ?>

  <div class="dashboard-container">
    <h1>Reports & Analytics Portal</h1>

    <!-- REPORT SELECTION CONTROL PANEL -->
    <div class="card">
      <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        
        <div style="flex: 1; min-width: 200px;">
          <label style="font-weight: bold; color: #28089a;">Select Report:</label>
          <select id="report_select" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 10px;">
            <option value="">-- Select Report Type --</option>
            <option value="session_summary">VBS Session Summary Report</option>
            <option value="contact_roster">Contact & Membership Directory</option>
          </select>
        </div>

        <div style="flex: 1; min-width: 200px;">
          <label style="font-weight: bold; color: #28089a;">Session Filter (Optional):</label>
          <select id="vbs_sessions_id" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 10px;">
            <option value="0">All Sessions</option>
          </select>
        </div>

        <div style="align-self: flex-end;">
          <button type="button" id="btnGenerateReport" class="btn-pulse" style="height: 38px; padding: 0 20px;">
            Generate Report
          </button>
        </div>

      </div>
    </div>

    <!-- EMBEDDED REPORT PREVIEW IFRAME -->
    <div class="report-frame-container">
      <iframe id="reportFrame" name="reportFrame" src="about:blank"></iframe>
    </div>
  </div>

  <?php include_once 'include/footer.php'; ?>
</body>
</html>