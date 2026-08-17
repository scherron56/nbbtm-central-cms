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
require_once __DIR__ . '/include/auth.php';
$adminUser = isAdmin();
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
    .alert-box {
      padding: 12px 16px;
      margin-bottom: 20px;
      border-radius: 6px;
      font-weight: 500;
      font-size: 0.95rem;
      display: none;
    }
    .alert-success { background-color: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
    .alert-error { background-color: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }

    .class-desc { font-size: 0.95rem; font-weight: 600; color: #1e293b; }
    
    table { width: 100%; border-collapse: collapse; margin-top: 1rem; background: #fff; }
    th, td { padding: 0.75rem; border: 1px solid #e2e8f0; text-align: left; }
    th { background: #f8fafc; font-weight: 700; }
    tr:nth-child(even) { background-color: #f9fafb; }
    
    .btn-action { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; color: white; font-size: 0.9rem; font-weight: 600; }
    .btn-checkin { background-color: #2563eb; }
    .btn-checkout { background-color: #16a34a; }
    .btn-action:disabled { background-color: #94a3b8; cursor: not-allowed; }

    .class-group-row {
      background-color: #e2e8f0 !important;
      font-weight: bold;
    }
    .class-group-row td {
      color: #0f172a;
      font-size: 1.05rem;
      padding: 0.85rem 0.75rem;
    }
  </style>

  <script>
$(document).ready(function() {
  const IS_ADMIN = <?php echo $adminUser ? 'true' : 'false'; ?>;
  let sessionsList = []; // Used to store session start and end dates locally

  function showStatusMessage(message, type = 'success') {
    let $box = $('#status-message');
    $box.removeClass('alert-success alert-error')
        .addClass(type === 'success' ? 'alert-success' : 'alert-error')
        .html(message)
        .stop(true, true)
        .fadeIn(200);

    if (type === 'success') {
      setTimeout(function() { $box.fadeOut(500); }, 5000);
    }
  }

  function clearStatusMessage() {
    $('#status-message').fadeOut(200).empty();
  }

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
          sessionsList = res.data;
          const select = $('#sessionSelect').empty().append('<option value="">-- Select Session First --</option>');
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

  // Populate dynamic dates dropdown based on session start/end
  function populateDateDropdown(startDateStr, endDateStr) {
    const dateSelect = $('#attendance_date').empty();
    
    if (!startDateStr || !endDateStr) {
      dateSelect.append('<option value="">-- No Dates Defined --</option>').prop('disabled', true);
      return;
    }

    dateSelect.prop('disabled', false);

    const startParts = startDateStr.split('-');
    const endParts = endDateStr.split('-');
    let current = new Date(startParts[0], startParts[1] - 1, startParts[2]);
    const end = new Date(endParts[0], endParts[1] - 1, endParts[2]);
    
    const todayObj = new Date();
    const todayFormatted = todayObj.getFullYear() + '-' + String(todayObj.getMonth() + 1).padStart(2, '0') + '-' + String(todayObj.getDate()).padStart(2, '0');

    let foundToday = false;
    let count = 0;

    // Loop through and generate an option for each day within the boundaries
    while (current <= end && count < 30) { 
      const yyyy = current.getFullYear();
      const mm = String(current.getMonth() + 1).padStart(2, '0');
      const dd = String(current.getDate()).padStart(2, '0');
      const val = `${yyyy}-${mm}-${dd}`;
      
      const display = current.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
      
      dateSelect.append($('<option>', { value: val, text: display }));
      
      if (val === todayFormatted) {
        foundToday = true;
      }

      current.setDate(current.getDate() + 1);
      count++;
    }

    // Auto-select today if it falls within the session block
    if (foundToday) {
      dateSelect.val(todayFormatted);
    }
  }

  // Session selection change handler
  $('#sessionSelect').on('change', function() {
    clearStatusMessage();
    const sessionId = $(this).val();
    
    if (sessionId) {
      const selectedSession = sessionsList.find(s => String(s.vbs_sessions_id) === String(sessionId));
      if (selectedSession) {
        populateDateDropdown(selectedSession.vbs_start_date, selectedSession.vbs_end_date);
      }
    } else {
      $('#attendance_date').empty().append('<option value="">-- Select Session First --</option>').prop('disabled', true);
    }

    loadRoster();
  });

  // Date selection change handler
  $('#attendance_date').on('change', function() {
    clearStatusMessage();
    loadRoster();
  });

  // Student filter dropdown change handler
  $('#studentSelect').on('change', function() {
    const selectedContactId = $(this).val();
    if (selectedContactId) {
      $('#studentTable tbody tr:not(.class-group-row)').hide();
      $('#studentTable tbody tr[data-id="' + selectedContactId + '"]').show();
    } else {
      $('#studentTable tbody tr').show();
    }
  });

  // Fetch VBS Roster Table with Session and Date filtering
  function loadRoster() {
    const selectedSession = $('#sessionSelect').val();
    const selectedDate = $('#attendance_date').val();

    // Prevent fetching all students if no session is selected
    if (!selectedSession) {
      renderRosterTable([]);
      populateStudentDropdown([]);
      return;
    }

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

      if (currentSelected) {
        studentSelect.val(currentSelected);
      }
    }
  }

  // Render Roster Table Grouped by Class
  function renderRosterTable(students) {
    const tbody = $('#studentTable tbody').empty();
    const selectedSession = $('#sessionSelect').val();

    if (!selectedSession) {
      tbody.append('<tr><td colspan="4" style="text-align:center; padding:1.5rem; color:#64748b;">Please select a Session Year above to view the attendance roster.</td></tr>');
      return;
    }

    if (!students || students.length === 0) {
      tbody.append('<tr><td colspan="4" style="text-align:center; padding:1.5rem; color:#64748b;">No VBS students found for this session/date.</td></tr>');
      return;
    }

    // Group students by class description
    const grouped = {};
    students.forEach(student => {
      const className = student.vbs_class_desc ? student.vbs_class_desc : ('Class #' + student.class_id);
      if (!grouped[className]) {
        grouped[className] = [];
      }
      grouped[className].push(student);
    });

    // Output class header rows and member records
    Object.keys(grouped).forEach(className => {
      tbody.append(`
        <tr class="class-group-row">
          <td colspan="4">🏫 ${escapeHtml(className)}</td>
        </tr>
      `);

      grouped[className].forEach(student => {
        const isCheckedIn = parseInt(student.is_checked_in) === 1;
        const studentName = student.student_full_name_formatted || student.student_display_name || (student.last_name + ', ' + student.first_name);

        const actionBtn = IS_ADMIN ? `
          <button class="btn-action ${isCheckedIn ? 'btn-checkout' : 'btn-checkin'} btn-toggle-checkin">
            ${isCheckedIn ? 'Check Out' : 'Check In'}
          </button>
        ` : `<button class="btn-action" disabled>Read Only</button>`;

        const rowHtml = `
          <tr data-id="${student.contact_id}" data-session-id="${student.vbs_sessions_id}">
            <td style="padding-left: 1.75rem;"><strong>${escapeHtml(studentName)}</strong></td>
            <td><span class="class-desc">${escapeHtml(className)}</span></td>
            <td>
              <span style="color:${isCheckedIn ? '#16a34a' : '#64748b'}; font-weight:bold;">
                ${isCheckedIn ? 'Checked In ✓' : 'Not Checked In'}
              </span>
            </td>
            <td>${actionBtn}</td>
          </tr>
        `;
        tbody.append(rowHtml);
      });
    });

    // Re-apply student dropdown filter if active
    const selectedContactId = $('#studentSelect').val();
    if (selectedContactId) {
      $('#studentTable tbody tr:not(.class-group-row)').hide();
      $('#studentTable tbody tr[data-id="' + selectedContactId + '"]').show();
    }
  }

  // Attendance Toggle passing the selected date
  $(document).on('click', '.btn-toggle-checkin', function() {
    if (!IS_ADMIN) return;
    clearStatusMessage();
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
          showStatusMessage("Error updating attendance: " + (res.message || "Operation failed."), 'error');
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
  <?php include 'include/header.php'; ?>

  <h1>VBS Daily Attendance Tracker</h1>

  <div id="status-message" class="alert-box"></div>

  <!-- Top Filters: Session, Date, and Student Select -->
  <fieldset id="session-set" style="display: flex; gap: 1.5rem; align-items: flex-end; margin-bottom: 1.5rem; width:100%; box-sizing:border-box;">
    <div class="field-group" style="flex: 1;">
      <label for="sessionSelect">
        <h3 style="color: blue; margin: 0;">Select Session Year</h3>
      </label>
      <select id="sessionSelect" style="width: 100%; padding: 0.4rem;">
        <option value="">-- Select Session First --</option>
      </select>
    </div>

    <!-- Dynamic Attendance Date Selector -->
    <div class="field-group" style="flex: 1;">
      <label for="attendance_date">
        <h3 style="color: blue; margin: 0;">Attendance Date</h3>
      </label>
      <select id="attendance_date" name="attendance_date" style="width: 100%; padding: 0.4rem;" disabled>
        <option value="">-- Select Session First --</option>
      </select>
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
  <table id="studentTable" style="width: 100%;">
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
<?php include_once 'include/footer.php'; ?>
</body>
</html>