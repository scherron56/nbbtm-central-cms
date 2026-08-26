<?php
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

$canEdit = canEdit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VBS Attendance Management</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <style>
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 700; font-size: 0.875rem; margin-bottom: 0.25rem; }
    .class-desc { font-size: 0.95rem; font-weight: 600; color: #1e293b; }
    
    table { width: 100%; border-collapse: collapse; margin-top: 1rem; background: #fff; }
    th, td { padding: 0.75rem; border: 1px solid #e2e8f0; text-align: left; }
    th { background: #f8fafc; font-weight: 700; }
    tr:nth-child(even) { background-color: #f9fafb; }
    
    .btn-action { padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; color: white; font-size: 0.85rem; }
    .btn-checkin { background-color: #2563eb; }
    .btn-checkout { background-color: #16a34a; }
    .completed-banner { background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #92400e; }
    .hidden { display: none !important; }
  </style>

  <script>
$(document).ready(function() {
  const CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;
  let currentSessionCompleted = false;
  let activeStartDate = '';
  let activeEndDate = '';

  loadSessions();

  function loadSessions() {
    $.ajax({
      url: 'vbs_api.php',
      type: 'GET',
      data: { action: 'fetch_sessions' },
      dataType: 'json',
      success: function(res) {
        if (res.success && Array.isArray(res.data)) {
          const select = $('#sessionSelect').empty().append('<option value="">--Select Session Year--</option>');
          res.data.forEach(session => {
            const dateSpan = (session.vbs_start_date && session.vbs_end_date) 
              ? ` (${session.vbs_start_date} to ${session.vbs_end_date})` 
              : '';
            select.append($('<option>', { 
              value: session.vbs_sessions_id, 
              text: session.vbs_year + ' - ' + (session.vbs_theme || 'Session #' + session.vbs_sessions_id) + dateSpan + (parseInt(session.vbs_sessions_completed) === 1 ? ' [Completed]' : ''),
              'data-completed': session.vbs_sessions_completed,
              'data-start': session.vbs_start_date,
              'data-end': session.vbs_end_date
            }));
          });
        }
      }
    });
  }

  $('#sessionSelect').on('change', function() {
    const selectedOption = $(this).find('option:selected');
    currentSessionCompleted = parseInt(selectedOption.data('completed')) === 1;
    activeStartDate = selectedOption.data('start') || '';
    activeEndDate   = selectedOption.data('end') || '';

    if (currentSessionCompleted) {
      $('#session-completed-banner').removeClass('hidden');
      $('#col-actions-head').addClass('hidden');
    } else {
      $('#session-completed-banner').addClass('hidden');
      $('#col-actions-head').removeClass('hidden');
    }

    loadRoster();
  });

  function loadRoster() {
    const selectedSession = $('#sessionSelect').val();
    if (!selectedSession) {
      renderRosterTable([]);
      return;
    }

    $.ajax({
      url: 'vbs_api.php',
      type: 'GET',
      data: { 
        action: 'fetch_roster', 
        vbs_sessions_id: selectedSession
      },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          renderRosterTable(res.data);
        } else {
          renderRosterTable([]);
        }
      },
      error: function() {
        renderRosterTable([]);
      }
    });
  }

  function renderRosterTable(students) {
    const tbody = $('#studentTable tbody').empty();
    
    // Dynamic colspan calculation based on currently visible <th> columns
    if (!students || students.length === 0) {
      const visibleCols = $('#studentTable thead th:visible').length || 4;
      tbody.append(`<tr><td colspan="${visibleCols}" style="text-align:center; padding:1.5rem; color:#64748b;">No VBS students registered for this session.</td></tr>`);
      return;
    }

    students.forEach(student => {
      const isCheckedIn = parseInt(student.is_checked_in) === 1;
      const classDesc = student.vbs_class_desc ? student.vbs_class_desc : ('Class #' + student.class_id);

      let actionBtnsCell = '';
      if (!currentSessionCompleted) {
        let actionButtons = CAN_EDIT ? `
          <div style="display:flex; gap:0.4rem;">
            <button class="btn-action ${isCheckedIn ? 'btn-checkout' : 'btn-checkin'} btn-toggle-checkin">
              ${isCheckedIn ? 'Check Out' : 'Check In'}
            </button>
          </div>
        ` : '<em>Read Only</em>';
        actionBtnsCell = `<td>${actionButtons}</td>`;
      }

      const rowHtml = `
        <tr data-id="${student.contact_id}" data-vbs-id="${student.vbs_id}" data-session-id="${student.vbs_sessions_id}" data-class-id="${student.class_id}">
          <td><strong>${escapeHtml(student.student_display_name || student.student_full_name_formatted)}</strong></td>
          <td><span class="class-desc">${escapeHtml(classDesc)}</span></td>
          <td>
            <span style="color:${isCheckedIn ? '#16a34a' : '#64748b'}; font-weight:bold;">
              ${isCheckedIn ? 'Checked In ✓' : 'Not Checked In'}
            </span>
          </td>
          ${actionBtnsCell}
        </tr>
      `;
      tbody.append(rowHtml);
    });
  }

  $(document).on('click', '.btn-toggle-checkin', function() {
    if (!CAN_EDIT || currentSessionCompleted) return;
    const row = $(this).closest('tr');
    const isCheckedIn = $(this).hasClass('btn-checkout');

    $.ajax({
      url: 'vbs_api.php',
      type: 'POST',
      data: {
        action: 'toggle_attendance',
        student_id: row.data('id'),
        vbs_session_id: row.data('session-id'),
        attendance_action: isCheckedIn ? 'checkout' : 'checkin',
        checkin_date: activeStartDate
      },
      dataType: 'json',
      success: function(res) {
        if (res.success) loadRoster();
      }
    });
  });

  function escapeHtml(str) {
    return str ? String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace/>/g, '&gt;').replace(/"/g, '&quot;') : '';
  }
});
  </script>
</head>
<body>
  <?php include 'include/header.php'; ?>

  <h1>VBS Attendance Management</h1>

  <div id="session-completed-banner" class="completed-banner hidden">
    <strong>Completed Session (Read-Only):</strong> Attendance toggles are locked for completed sessions.
  </div>

  <fieldset id="session-set" style="width: 100%; box-sizing: border-box; margin-bottom: 1rem;">
    <div class="field-group" style="width: 100%;">
      <label for="sessionSelect"><h3 style="color: blue; margin: 0;">Select Session Year</h3></label>
      <select id="sessionSelect" style="width: 100%; padding: 0.4rem;">
        <option value="">--Select Session Year--</option>
      </select>
    </div>
  </fieldset>

  <!-- Student Roster Table -->
  <table id="studentTable">
    <thead>
      <tr>
        <th>Student Name</th>
        <th>Class</th>
        <th>Status</th>
        <th id="col-actions-head">Actions</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>

<?php include_once 'include/footer.php'; ?>
</body>
</html>