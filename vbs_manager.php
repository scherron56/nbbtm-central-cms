<?php
// Enforce persistent cookie scope before session start
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, // 24 Hours
        'path'     => '/',   // Root path ensures session spans all sub-folders
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Require auth helper
require_once __DIR__ . '/include/auth.php';
$canEdit = canEdit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VBS Attendance & Student Management</title>
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
    .btn-edit { background-color: #64748b; }
    .btn-delete { background-color: #ef4444; }
    .add-contact-link { font-size: 0.75rem; color: #2563eb; text-decoration: none; font-weight: 600; }
    .add-contact-link:hover { text-decoration: underline; }
    .read-only-banner {
      background-color: #f1f5f9;
      border-left: 4px solid #0ea5e9;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
      color: #334155;
    }
  </style>

  <script>
$(document).ready(function() {
  const CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;

  // Initial Page Load
  loadSessions();
  loadClassesForSession('');
  loadStudentDropdown();
  loadRoster();

  // Load Sessions into top dropdown using $.ajax
  function loadSessions() {
    $.ajax({
      url: 'vbs_api.php',
      type: 'GET',
      data: { action: 'fetch_sessions' },
      dataType: 'json',
      success: function(res) {
        if (res.success && Array.isArray(res.data)) {
          const select = $('#sessionSelect').empty().append('<option value="">--Select--</option>');
          res.data.forEach(session => {
            select.append($('<option>', { 
              value: session.vbs_sessions_id, 
              text: session.vbs_year + ' (Session #' + session.vbs_sessions_id + ')' 
            }));
          });
        } else {
          console.error("fetch_sessions API error:", res.message);
        }
      },
      error: function(jqXHR, textStatus) {
        console.error("AJAX Error in loadSessions:", textStatus);
      }
    });
  }

  // Session selection change handler
  $('#sessionSelect').on('change', function() {
    const sessionId = $(this).val();
    $('#form_vbs_sessions_id').val(sessionId);
    loadClassesForSession(sessionId);
    loadRoster();
  });

  // Load classes dropdown based on vbs_sessions_id using $.ajax
  function loadClassesForSession(sessionId, callback) {
    $.ajax({
      url: 'vbs_api.php',
      type: 'GET',
      data: { action: 'fetch_classes', vbs_sessions_id: sessionId },
      dataType: 'json',
      success: function(res) {
        const select = $('#vbs_class_id').empty().append('<option value="">--Select--</option>');
        if (res.success && Array.isArray(res.data)) {
          res.data.forEach(cls => {
            select.append($('<option>', { value: cls.vbs_class_id, text: cls.vbs_class_desc }));
          });
        } else {
          console.error("fetch_classes API error:", res.message);
        }
        if (typeof callback === 'function') {
          callback();
        }
      },
      error: function(jqXHR, textStatus) {
        console.error("AJAX Error in loadClassesForSession:", textStatus);
      }
    });
  }

  // Load contacts into student select field using $.ajax
  function loadStudentDropdown() {
    $.ajax({
      url: 'getLists.php',
      type: 'POST',
      data: { members_only: 0 },
      dataType: 'json',
      success: function(data) {
        const select = $('#contact_id').empty().append('<option value="">--Select Contact--</option>');
        
        let list = [];
        if (Array.isArray(data)) {
          list = data;
        } else if (data && Array.isArray(data.contacts)) {
          list = data.contacts;
        } else if (data && Array.isArray(data.data)) {
          list = data.data;
        }

        if (list.length > 0) {
          $.each(list, function(i, item) {
            const id = item.contact_id || item.id;
            const text = item.fullname || item.name || ((item.first_name || '') + ' ' + (item.last_name || '')).trim();
            if (id && text) {
              select.append($('<option>', { value: id, text: text }));
            }
          });
        }
      },
      error: function(jqXHR, textStatus) {
        console.error("Failed to load contacts from getLists.php:", textStatus);
      }
    });
  }

  // Fetch VBS Roster Table using $.ajax
  function loadRoster() {
    const selectedSession = $('#sessionSelect').val();
    $.ajax({
      url: 'vbs_api.php',
      type: 'GET',
      data: { action: 'fetch_roster', vbs_sessions_id: selectedSession },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          renderRosterTable(res.data);
        } else {
          console.error("fetch_roster API error:", res.message);
        }
      },
      error: function(jqXHR, textStatus) {
        console.error("AJAX Error in loadRoster:", textStatus);
      }
    });
  }

  // Render Roster Table
  function renderRosterTable(students) {
    const tbody = $('#studentTable tbody').empty();
    if (!students || students.length === 0) {
      tbody.append('<tr><td colspan="4" style="text-align:center; padding:1.5rem; color:#64748b;">No VBS students registered for this session.</td></tr>');
      return;
    }

    students.forEach(student => {
      const isCheckedIn = parseInt(student.is_checked_in) === 1;
      const classDesc = student.vbs_class_desc ? student.vbs_class_desc : ('Class #' + student.class_id);

      let actionButtons = CAN_EDIT ? `
        <div style="display:flex; gap:0.4rem;">
          <button class="btn-action ${isCheckedIn ? 'btn-checkout' : 'btn-checkin'} btn-toggle-checkin">
            ${isCheckedIn ? 'Check Out' : 'Check In'}
          </button>
          <button class="btn-action btn-edit">Edit</button>
          <button class="btn-action btn-delete">✕</button>
        </div>
      ` : '<em>Read Only</em>';

      const rowHtml = `
        <tr data-id="${student.contact_id}" data-vbs-id="${student.vbs_id}" data-session-id="${student.vbs_sessions_id}" data-class-id="${student.class_id}">
          <td><strong>${escapeHtml(student.child_name)}</strong></td>
          <td><span class="class-desc">${escapeHtml(classDesc)}</span></td>
          <td>
            <span style="color:${isCheckedIn ? '#16a34a' : '#64748b'}; font-weight:bold;">
              ${isCheckedIn ? 'Checked In ✓' : 'Not Checked In'}
            </span>
          </td>
          <td>${actionButtons}</td>
        </tr>
      `;
      tbody.append(rowHtml);
    });
  }

  // Attendance Toggle using $.ajax
  $(document).on('click', '.btn-toggle-checkin', function() {
    if (!CAN_EDIT) return;
    const row = $(this).closest('tr');
    const isCheckedIn = row.find('.btn-toggle-checkin').hasClass('btn-checkout');

    $.ajax({
      url: 'vbs_api.php',
      type: 'POST',
      data: {
        action: 'toggle_attendance',
        student_id: row.data('id'),
        vbs_session_id: row.data('session-id'),
        attendance_action: isCheckedIn ? 'checkout' : 'checkin'
      },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          loadRoster();
        } else {
          showInlineMessage("Error updating attendance: " + res.message, "error");
        }
      },
      error: function() {
        showInlineMessage("Server error during attendance toggle.", "error");
      }
    });
  });

  // Edit Action using $.ajax
  $(document).on('click', '.btn-edit', function() {
    if (!CAN_EDIT) return;
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
          
          loadClassesForSession(s.vbs_sessions_id, function() {
            $('#vbs_class_id').val(s.class_id);
          });

          $('#allergies').val(s.allergies);
          $('#food_restrictions').val(s.food_restrictions);
          $('#medical_notes').val(s.medical_notes);

          $('#formTitle').text('Edit VBS Student Record');
          $('html, body').animate({ scrollTop: $("#inlineFormContainer").offset().top }, 'fast');
        } else {
          showInlineMessage("Failed to retrieve student record.", "error");
        }
      }
    });
  });

  // Delete Action using $.ajax
  $(document).on('click', '.btn-delete', function() {
    if (!CAN_EDIT) return;
    const vbsId = $(this).closest('tr').data('vbs-id');
    if (!confirm('Are you sure you want to remove this student from VBS?')) return;

    $.ajax({
      url: 'vbs_api.php',
      type: 'POST',
      data: { action: 'delete_student', vbs_id: vbsId },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          showInlineMessage("Record removed.", "success");
          loadRoster();
        } else {
          showInlineMessage("Error deleting record.", "error");
        }
      }
    });
  });

  // Submit Form using $.ajax
  $('#vbsStudentForm').on('submit', function(e) {
    e.preventDefault();
    if (!CAN_EDIT) return;
    if (!$('#form_vbs_sessions_id').val()) {
      showInlineMessage("Please select a Session Year from the dropdown above.", "error");
      return;
    }

    $.ajax({
      url: 'vbs_api.php',
      type: 'POST',
      data: $(this).serialize() + '&action=save_student',
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          resetForm();
          showInlineMessage("Student record saved successfully.", "success");
          loadRoster();
        } else {
          showInlineMessage("Error: " + res.message, "error");
        }
      },
      error: function() {
        showInlineMessage("Server error while saving student.", "error");
      }
    });
  });

  $('#resetFormBtn').click(function() {
    resetForm();
  });

  function resetForm() {
    $('#vbsStudentForm')[0].reset();
    $('#vbs_id').val('');
    $('#form_vbs_sessions_id').val($('#sessionSelect').val());
    $('#formTitle').text('Register VBS Student');
  }

  function showInlineMessage(msg, type) {
    const box = $('#inlineStatus');
    box.text(msg)
       .css('background-color', type === 'error' ? '#fef2f2' : '#f0fdf4')
       .css('color', type === 'error' ? '#991b1b' : '#166534')
       .css('border', type === 'error' ? '1px solid #fca5a5' : '1px solid #86efac')
       .show();
    setTimeout(() => box.fadeOut(), 4000);
  }

  // Search Filter
  $('#searchInput').on('keyup', function() {
    const val = $(this).val().toLowerCase();
    $('#studentTable tbody tr').filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
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

  <h1>VBS Attendance & Roster Management</h1>

  <!-- Top Filters & Session Selection -->
  <fieldset id="session-set" class="form-grid-section-short-40">
    <div class="field-group" style="--colspan: 2;">
      <label for="sessionSelect">
        <h3 style="color: blue; margin: 0;">Select Session Year</h3>
      </label>
      <select id="sessionSelect">
        <option value="">--Select--</option>
      </select>
    </div>
  </fieldset>

  <?php if ($canEdit): ?>
    <!-- Inline Registration & Edit Form -->
    <div id="inlineFormContainer" style="background:#ffffff; padding:1.5rem; border:2px solid #e2e8f0; border-radius:8px; margin: 1.5rem 0;">
      <h2 id="formTitle" style="margin-top:0;">Register VBS Student</h2>
      
      <div id="inlineStatus" style="display:none; padding:0.75rem; border-radius:4px; margin-bottom:1rem;"></div>

      <form id="vbsStudentForm">
        <input type="hidden" name="vbs_id" id="vbs_id">
        <input type="hidden" name="vbs_sessions_id" id="form_vbs_sessions_id">

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
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
              <option value="">--Select--</option>
            </select>
          </div>
        </div>

        <fieldset id="student-details" style="margin-top:1rem;">
          <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
            <div class="form-group">
              <label for="allergies">Allergies</label>
              <textarea name="allergies" id="allergies" rows="2" style="width:100%;"></textarea>
            </div>
            <div class="form-group">
              <label for="food_restrictions">Food Restrictions</label>
              <textarea name="food_restrictions" id="food_restrictions" rows="2" style="width:100%;"></textarea>
            </div>
            <div class="form-group">
              <label for="medical_notes">Medical Notes</label>
              <textarea name="medical_notes" id="medical_notes" rows="2" style="width:100%;"></textarea>
            </div>
          </div>
        </fieldset>

        <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
          <button type="button" id="resetFormBtn" class="btn-pulse" style="background:#94a3b8; color:white;">Clear / Cancel</button>
          <button type="submit" class="btn-pulse" style="background:#2563eb; color:white;">Save Record</button>
        </div>
      </form>
    </div>
  <?php else: ?>
    <div class="read-only-banner" style="margin-top: 1.5rem;">
      <strong>Read-Only Mode:</strong> You must be signed in as staff or an administrator to register students or modify attendance records.
    </div>
  <?php endif; ?>

  <div style="margin-bottom:1rem;">
    <input type="text" id="searchInput" placeholder="Search student name..." style="padding:0.6rem; width:100%; max-width:400px;">
  </div>

  <!-- Student Roster Table -->
  <table id="studentTable">
    <thead>
      <tr>
        <th>Student Name</th>
        <th>Class</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
<?php include_once 'include/footer.php'; ?>
</body>
</html>