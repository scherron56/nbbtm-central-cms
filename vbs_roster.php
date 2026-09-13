<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/include/auth.php';
$adminUser = isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VBS Student Registration & Roster</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <style>
    .alert-box { padding: 12px 16px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; font-size: 0.95rem; display: none; }
    .alert-success { background-color: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
    .alert-error { background-color: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
    .completed-banner { background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #92400e; }
    
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 700; font-size: 0.875rem; margin-bottom: 0.25rem; }
    .class-desc { font-size: 0.95rem; font-weight: 600; color: #1e293b; }
    
    table { width: 100%; border-collapse: collapse; margin-top: 1rem; background: #fff; }
    th, td { padding: 0.75rem; border: 1px solid #e2e8f0; text-align: left; }
    th { background: #f8fafc; font-weight: 700; }
    tr:nth-child(even) { background-color: #f9fafb; }
    
    .btn-action { padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; color: white; font-size: 0.85rem; }
    .btn-edit { background-color: #64748b; }
    .btn-delete { background-color: #ef4444; }
    .add-contact-link { font-size: 0.75rem; color: #2563eb; text-decoration: none; font-weight: 600; }

    .full-width-container { width: 100% !important; max-width: 100% !important; box-sizing: border-box; margin: 1.5rem 0; }
    .class-group-row { background-color: #e2e8f0 !important; font-weight: bold; }
    .class-group-row td { color: #0f172a; font-size: 1.05rem; padding: 0.85rem 0.75rem; }
    .hidden { display: none !important; }
  </style>

  <script>
$(document).ready(function() {
  const IS_ADMIN = <?php echo $adminUser ? 'true' : 'false'; ?>;
  let currentSessionCompleted = false;

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

  loadSessions();
  loadClassesForSession('');
  loadStudentDropdown();
  renderRosterTable([]);

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
            select.append($('<option>', { 
              value: session.vbs_sessions_id, 
              text: session.vbs_year + ' (Session #' + session.vbs_sessions_id + ')' + (parseInt(session.vbs_sessions_completed) === 1 ? ' [Completed]' : ''),
              'data-completed': session.vbs_sessions_completed
            }));
          });
        }
      },
      error: function(xhr, status, error) {
        console.error('Failed to load VBS sessions:', xhr.responseText || error);
        showStatusMessage(status === 'parsererror' ? 'The server returned invalid session data.' : 'Failed to load VBS sessions.', 'error');
      }
    });
  }

  $('#sessionSelect').on('change', function() {
    const selectedOption = $(this).find('option:selected');
    currentSessionCompleted = parseInt(selectedOption.data('completed')) === 1;
    const sessionId = $(this).val();

    $('#form_vbs_sessions_id').val(sessionId);
    loadClassesForSession(sessionId);

    if (currentSessionCompleted) {
      $('#session-completed-banner').removeClass('hidden');
      $('#inlineFormContainer').addClass('hidden');
      $('#col-actions-head').addClass('hidden');
    } else {
      $('#session-completed-banner').addClass('hidden');
      if (IS_ADMIN) $('#inlineFormContainer').removeClass('hidden');
      $('#col-actions-head').removeClass('hidden');
    }

    if (sessionId) {
      loadRoster();
    } else {
      renderRosterTable([]);
    }
  });

  function loadClassesForSession(sessionId, callback) {
    $.ajax({
      url: 'vbs_api.php',
      type: 'GET',
      data: { action: 'fetch_classes', vbs_sessions_id: sessionId },
      dataType: 'json',
      success: function(res) {
        const select = $('#vbs_class_id').empty().append('<option value="">--Select Class--</option>');
        if (res.success && Array.isArray(res.data)) {
          res.data.forEach(cls => {
            select.append($('<option>', { value: cls.vbs_class_id, text: cls.vbs_class_desc }));
          });
        }
        if (typeof callback === 'function') callback();
      },
      error: function(xhr, status, error) {
        console.error('Failed to load VBS classes:', xhr.responseText || error);
        showStatusMessage(status === 'parsererror' ? 'The server returned invalid class data.' : 'Failed to load VBS classes.', 'error');
      }
    });
  }

  function loadStudentDropdown() {
    $.ajax({
      url: 'getLists.php',
      type: 'POST',
      data: { members_only: 0 },
      dataType: 'json',
      success: function(data) {
        const select = $('#contact_id').empty().append('<option value="">--Select Contact--</option>');
        let list = Array.isArray(data) ? data : (data.contacts || data.data || []);
        list.forEach(item => {
          const id = item.contact_id || item.id;
          const text = item.fullname || item.name || ((item.first_name || '') + ' ' + (item.last_name || '')).trim();
          if (id && text) select.append($('<option>', { value: id, text: text }));
        });
      },
      error: function(xhr, status, error) {
        console.error('Failed to load contacts:', xhr.responseText || error);
        showStatusMessage(status === 'parsererror' ? 'The server returned invalid contact data.' : 'Failed to load contacts.', 'error');
      }
    });
  }

  function loadRoster() {
    const selectedSession = $('#sessionSelect').val();
    if (!selectedSession) { renderRosterTable([]); return; }

    $.ajax({
      url: 'vbs_api.php',
      type: 'GET',
      data: { action: 'fetch_roster', vbs_sessions_id: selectedSession },
      dataType: 'json',
      success: function(res) {
        if (res.success) renderRosterTable(res.data);
      }
    });
  }

  function renderRosterTable(students) {
    const tbody = $('#studentTable tbody').empty();
    const selectedSession = $('#sessionSelect').val();

    if (!selectedSession) {
      tbody.append('<tr><td colspan="3" style="text-align:center; padding:1.5rem; color:#64748b;">Please select a Session Year above to view registered students.</td></tr>');
      return;
    }

    if (!students || students.length === 0) {
      tbody.append('<tr><td colspan="3" style="text-align:center; padding:1.5rem; color:#64748b;">No VBS students registered for this session.</td></tr>');
      return;
    }

    const grouped = {};
    students.forEach(student => {
      const className = student.vbs_class_desc ? student.vbs_class_desc : ('Class #' + student.class_id);
      if (!grouped[className]) grouped[className] = [];
      grouped[className].push(student);
    });

    Object.keys(grouped).forEach(className => {
      tbody.append(`<tr class="class-group-row"><td colspan="3">🏫 ${escapeHtml(className)}</td></tr>`);

      grouped[className].forEach(student => {
        const studentDisplayName = student.student_full_name_formatted || student.student_display_name || student.child_name;

        let actionBtnsCell = '';
        if (!currentSessionCompleted) {
          const actionBtns = IS_ADMIN ? `
            <button class="btn-action btn-edit">Edit</button>
            <button class="btn-action btn-delete">✕</button>
          ` : '<em>Read Only</em>';
          actionBtnsCell = `<td><div style="display:flex; gap:0.4rem;">${actionBtns}</div></td>`;
        }

        const rowHtml = `
          <tr data-id="${student.contact_id}" data-vbs-id="${student.vbs_id}" data-session-id="${student.vbs_sessions_id}" data-class-id="${student.class_id}">
            <td style="padding-left: 1.75rem;"><strong>${escapeHtml(studentDisplayName)}</strong></td>
            <td><span class="class-desc">${escapeHtml(className)}</span></td>
            ${actionBtnsCell}
          </tr>
        `;
        tbody.append(rowHtml);
      });
    });
  }

  $(document).on('click', '.btn-edit', function() {
    if (!IS_ADMIN || currentSessionCompleted) return;
    const vbsId = $(this).closest('tr').data('vbs-id');

    $.ajax({
      url: 'vbs_api.php',
      type: 'GET',
      data: { action: 'get_student', vbs_id: vbsId },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          const s = res.data;
          $('#vbs_id').val(s.vbs_id);
          $('#form_vbs_sessions_id').val(s.vbs_sessions_id);
          $('#sessionSelect').val(s.vbs_sessions_id);
          $('#contact_id').val(s.contact_id);
          loadClassesForSession(s.vbs_sessions_id, function() { $('#vbs_class_id').val(s.class_id); });
          $('#allergies').val(s.allergies);
          $('#food_restrictions').val(s.food_restrictions);
          $('#medical_notes').val(s.medical_notes);
          $('#formTitle').text('Edit VBS Student Record');
        }
      }
    });
  });

  $(document).on('click', '.btn-delete', function() {
    if (!IS_ADMIN || currentSessionCompleted) return;
    const vbsId = $(this).closest('tr').data('vbs-id');
    if (!confirm("Are you sure you want to remove this student registration?")) return;

    $.ajax({
      url: 'vbs_api.php',
      type: 'POST',
      data: { action: 'delete_student', vbs_id: vbsId },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          showStatusMessage("Record removed successfully.", "success");
          loadRoster();
        }
      }
    });
  });

  $('#vbsStudentForm').on('submit', function(e) {
    e.preventDefault();
    if (!IS_ADMIN || currentSessionCompleted) return;

    $.ajax({
      url: 'vbs_api.php',
      type: 'POST',
      data: $(this).serialize() + '&action=save_student',
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          $('#vbsStudentForm')[0].reset();
          $('#vbs_id').val('');
          showStatusMessage("Student record saved successfully.", "success");
          loadRoster();
        } else {
          showStatusMessage("Error: " + res.message, "error");
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

  <h1>VBS Registration & Student Management</h1>

  <div id="status-message" class="alert-box"></div>

  <div id="session-completed-banner" class="completed-banner hidden">
    <strong>Completed Session (Read-Only):</strong> Registration forms and edit actions are hidden for completed VBS sessions.
  </div>

  <fieldset id="session-set" style="width: 100%; box-sizing: border-box; margin-bottom: 1rem;">
    <div class="field-group" style="width: 100%;">
      <label for="sessionSelect">
        <h3 style="color: blue; margin: 0;">Select Session Year</h3>
      </label>
      <select id="sessionSelect" style="width: 100%; padding: 0.4rem;">
        <option value="">--Select Session Year--</option>
      </select>
    </div>
  </fieldset>

  <!-- DATA ENTRY FORM (Hidden if session is completed) -->
  <?php if ($adminUser): ?>
    <div id="inlineFormContainer" class="full-width-container" style="background:#ffffff; padding:1.5rem; border:2px solid #e2e8f0; border-radius:8px;">
      <h2 id="formTitle" style="margin-top:0;">Register VBS Student</h2>

      <form id="vbsStudentForm" style="width: 100%;">
        <input type="hidden" name="vbs_id" id="vbs_id">
        <input type="hidden" name="vbs_sessions_id" id="form_vbs_sessions_id">

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem; width:100%;">
          <div class="form-group">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <label for="contact_id">Student</label>
              <a href="contacts.php?type=child" class="add-contact-link" target="_blank">+ Add Student to Contacts</a>
            </div>
            <select name="contact_id" id="contact_id" required style="width:100%; padding:0.4rem;"></select>
          </div>

          <div class="form-group">
            <label for="vbs_class_id">Registered Class</label>
            <select name="vbs_class_id" id="vbs_class_id" required style="width:100%; padding:0.4rem;">
              <option value="">--Select Class--</option>
            </select>
          </div>
        </div>

        <fieldset id="student-details" style="margin-top:1rem; width:100%; box-sizing:border-box;">
          <legend><strong>Medical & Dietary Notes</strong></legend>
          <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:1.5rem; width:100%;">
            <div class="form-group">
              <label for="allergies">Allergies</label>
              <textarea name="allergies" id="allergies" rows="2" style="width:100%; box-sizing:border-box;"></textarea>
            </div>
            <div class="form-group">
              <label for="food_restrictions">Food Restrictions</label>
              <textarea name="food_restrictions" id="food_restrictions" rows="2" style="width:100%; box-sizing:border-box;"></textarea>
            </div>
            <div class="form-group">
              <label for="medical_notes">Medical Notes</label>
              <textarea name="medical_notes" id="medical_notes" rows="2" style="width:100%; box-sizing:border-box;"></textarea>
            </div>
          </div>
        </fieldset>

        <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
          <button type="button" id="resetFormBtn" class="btn-pulse" style="background:#94a3b8; color:white;">Clear / Cancel</button>
          <button type="submit" class="btn-pulse" style="background:#2563eb; color:white;">Save Record</button>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <!-- ROSTER DASHBOARD -->
  <h2 style="margin-top: 2rem;">Registered Student Dashboard</h2>
  <table id="studentTable" style="width:100%;">
    <thead>
      <tr>
        <th>Student Name</th>
        <th>Class</th>
        <th id="col-actions-head">Actions</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
<?php include_once 'include/footer.php'; ?>
</body>
</html>