<?php
// Enforce persistent cookie scope before session start
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400, // 24 Hours
        'path'     => '/',   // Root path ensures session spans all sub-folders
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Require auth helper
require_once __DIR__ . '/auth.php';

// Optional: Restrict page to logged-in users
// requireRole(['admin', 'staff', 'browse']); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VBS Daily Attendance Tracker</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <style>
    .class-desc { font-size: 0.95rem; font-weight: 600; color: #1e293b; }
    
    table { width: 100%; border-collapse: collapse; margin-top: 1rem; background: #fff; }
    th, td { padding: 0.75rem; border: 1px solid #e2e8f0; text-align: left; }
    th { background: #f8fafc; font-weight: 700; }
    tr:nth-child(even) { background-color: #f9fafb; }
    
    .btn-action { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; color: white; font-size: 0.9rem; font-weight: 600; }
    .btn-checkin { background-color: #2563eb; }
    .btn-checkout { background-color: #16a34a; }
  </style>

  <script>
$(document).ready(function() {
  // Default attendance date to today (YYYY-MM-DD)
  const today = new Date().toISOString().split('T')[0];
  $('#attendance_date').val(today);

  // Initial Page Load
  loadSessions();
  loadRoster();

  // Load Sessions into dropdown
  function loadSessions() {
    $.ajax({
      url: 'vbs_api.php',
      type: 'GET',
      data: { action: 'fetch_sessions' },
      dataType: 'json',
      success: function(res) {
        if (res.success && Array.isArray(res.data)) {
          const select = $('#sessionSelect').empty().append('<option value="">-- All Sessions --</option>');
          res.data.forEach(session => {
            select.append($('<option>', { 
              value: session.vbs_sessions_id, 
              text: session.vbs_year + ' (Session #' + session.vbs_sessions_id + ')' 
            }));
          });
        }
      }
    });
  }

  // Session selection or date selection change handler
  $('#sessionSelect, #attendance_date').on('change', function() {
    loadRoster();
  });

  // Student filter dropdown change handler
  $('#studentSelect').on('change', function() {
    const selectedContactId = $(this).val();
    if (selectedContactId) {
      $('#studentTable tbody tr').hide();
      $('#studentTable tbody tr[data-id="' + selectedContactId + '"]').show();
    } else {
      $('#studentTable tbody tr').show();
    }
  });

  // Fetch VBS Roster Table with Session and Date filtering
  function loadRoster() {
    const selectedSession = $('#sessionSelect').val();
    const selectedDate = $('#attendance_date').val();

    $.ajax({
      url: 'vbs_api.php',
      type: 'GET',
      data: { 
        action: 'fetch_roster', 
        vbs_sessions_id: selectedSession,
        checkin_date: selectedDate
      },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          populateStudentDropdown(res.data);
          renderRosterTable(res.data);
        }
      }
    });
  }

  // Populate student dropdown formatted as "LastName, FirstName"
  function populateStudentDropdown(students) {
    const currentSelected = $('#studentSelect').val();
    const studentSelect = $('#studentSelect').empty().append('<option value="">-- All Registered Students --</option>');
    
    if (students && students.length > 0) {
      students.forEach(student => {
        const formattedName = student.student_full_name_formatted || (student.last_name + ', ' + student.first_name);

        studentSelect.append($('<option>', {
          value: student.contact_id,
          text: formattedName
        }));
      });

      // Preserve existing dropdown selection after table reload
      if (currentSelected) {
        studentSelect.val(currentSelected);
      }
    }
  }

  // Render Roster Table
  function renderRosterTable(students) {
    const tbody = $('#studentTable tbody').empty();
    if (!students || students.length === 0) {
      tbody.append('<tr><td colspan="4" style="text-align:center; padding:1.5rem; color:#64748b;">No VBS students found for this session/date.</td></tr>');
      return;
    }

    students.forEach(student => {
      const isCheckedIn = parseInt(student.is_checked_in) === 1;
      const classDesc = student.vbs_class_desc ? student.vbs_class_desc : ('Class #' + student.class_id);
      const studentName = student.student_full_name_formatted || (student.last_name + ', ' + student.first_name);

      const rowHtml = `
        <tr data-id="${student.contact_id}" data-session-id="${student.vbs_sessions_id}">
          <td><strong>${escapeHtml(studentName)}</strong></td>
          <td><span class="class-desc">${escapeHtml(classDesc)}</span></td>
          <td>
            <span style="color:${isCheckedIn ? '#16a34a' : '#64748b'}; font-weight:bold;">
              ${isCheckedIn ? 'Checked In ✓' : 'Not Checked In'}
            </span>
          </td>
          <td>
            <button class="btn-action ${isCheckedIn ? 'btn-checkout' : 'btn-checkin'} btn-toggle-checkin">
              ${isCheckedIn ? 'Check Out' : 'Check In'}
            </button>
          </td>
        </tr>
      `;
      tbody.append(rowHtml);
    });

    // Re-apply student dropdown filter if one was selected
    const selectedContactId = $('#studentSelect').val();
    if (selectedContactId) {
      $('#studentTable tbody tr').hide();
      $('#studentTable tbody tr[data-id="' + selectedContactId + '"]').show();
    }
  }

  // Attendance Toggle passing the selected date
  $(document).on('click', '.btn-toggle-checkin', function() {
    const row = $(this).closest('tr');
    const isCheckedIn = row.find('.btn-toggle-checkin').hasClass('btn-checkout');
    const selectedDate = $('#attendance_date').val();

    $.ajax({
      url: 'vbs_api.php',
      type: 'POST',
      data: {
        action: 'toggle_attendance',
        student_id: row.data('id'),
        vbs_session_id: row.data('session-id'),
        attendance_action: isCheckedIn ? 'checkout' : 'checkin',
        checkin_date: selectedDate
      },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          loadRoster();
        } else {
          alert("Error updating attendance: " + res.message);
        }
      }
    });
  });

  function escapeHtml(str) {
    return str ? String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') : '';
  }
});
  </script>
</head>
<body>
  <?php require_once("config/db.php"); ?>
  <?php include 'header.php'; ?>

  <h1>VBS Daily Attendance Tracker</h1>

  <!-- Top Filters: Session, Date, and Student Select -->
  <fieldset id="session-set" class="form-grid-section-short-40" style="display: flex; gap: 1.5rem; align-items: flex-end; margin-bottom: 1.5rem;">
    <div class="field-group" style="flex: 1;">
      <label for="sessionSelect">
        <h3 style="color: blue; margin: 0;">Select Session Year</h3>
      </label>
      <select id="sessionSelect" style="width: 100%; padding: 0.4rem;">
        <option value="">-- All Sessions --</option>
      </select>
    </div>

    <!-- Attendance Date Selector -->
    <div class="field-group" style="flex: 1;">
      <label for="attendance_date">
        <h3 style="color: blue; margin: 0;">Attendance Date</h3>
      </label>
      <input type="date" id="attendance_date" name="attendance_date" style="width: 100%; padding: 0.35rem;">
    </div>

    <!-- Registered Student Dropdown Filter -->
    <div class="field-group" style="flex: 1;">
      <label for="studentSelect">
        <h3 style="color: blue; margin: 0;">Select Student</h3>
      </label>
      <select id="studentSelect" style="width: 100%; padding: 0.4rem;">
        <option value="">-- All Registered Students --</option>
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
        <th>Action</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>

</body>
</html>