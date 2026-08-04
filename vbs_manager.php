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
    .vbs-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 1.25rem;
      margin-top: 1rem;
    }
    .student-card {
      background: #ffffff;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      padding: 1rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .student-card.checked-in {
      border-color: #16a34a;
      background-color: #f0fdf4;
    }
    .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; }
    .card-header h3 { margin: 0; font-size: 1.15rem; color: #0f172a; }
    .badge-class { background: #e0e7ff; color: #3730a3; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 700; }
    .alert-badge { font-size: 0.85rem; padding: 0.4rem 0.6rem; border-radius: 4px; margin-top: 0.5rem; }
    .alert-badge.allergy { background-color: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
    .alert-badge.food { background-color: #fffbe6; color: #d48806; border: 1px solid #ffe58f; }
    .card-actions { display: flex; gap: 0.5rem; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #f1f5f9; }
    
    /* Modal Styling */
    .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; }
    .modal-content { background: white; padding: 1.5rem; border-radius: 8px; width: 100%; max-width: 550px; max-height: 90vh; overflow-y: auto; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 700; font-size: 0.875rem; margin-bottom: 0.25rem; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  </style>

  <script>
$(document).ready(function() {
  loadDropdownLists();
  loadRoster();

  // Load contacts into student/parent select fields
  function loadDropdownLists() {
    $.post("getLists.php", { members_only: 0 }, function(data) {
      $('#contact_id, #parent_id').empty().append($('<option>', { value: '', text: '--Select Contact--' }));
      if (data.contacts && Array.isArray(data.contacts)) {
        $.each(data.contacts, function(i, item) {
          $('#contact_id, #parent_id').append($('<option>', { value: item.contact_id, text: item.fullname }));
        });
      }
    }, 'json');
  }

  // Load VBS Roster Cards
  function loadRoster() {
    $.get('vbs_api.php', { action: 'fetch_roster' }, function(res) {
      if (res.success) {
        renderRoster(res.data);
      }
    }, 'json');
  }

  function renderRoster(students) {
    const grid = $('#studentGrid').empty();
    if (!students || students.length === 0) {
      grid.html('<p>No VBS students registered yet.</p>');
      return;
    }

    students.forEach(student => {
      const isCheckedIn = parseInt(student.is_checked_in) === 1;
      const allergyHtml = student.allergies ? `<div class="alert-badge allergy">⚠️ <strong>Allergies:</strong> ${escapeHtml(student.allergies)}</div>` : '';
      const foodHtml = student.food_restrictions ? `<div class="alert-badge food">🍎 <strong>Food:</strong> ${escapeHtml(student.food_restrictions)}</div>` : '';

      const cardHtml = `
        <div class="student-card ${isCheckedIn ? 'checked-in' : ''}" 
             data-id="${student.contact_id}" 
             data-vbs-id="${student.vbs_id}"
             data-session-id="${student.vbs_sessions_id}">
          <div>
            <div class="card-header">
              <h3>${escapeHtml(student.child_name)}</h3>
              <span class="badge-class">Class #${student.class_id}</span>
            </div>
            <div style="font-size:0.9rem; color:#475569;">
              <strong>Parent:</strong> ${escapeHtml(student.parent_name || 'N/A')}<br>
              ${student.parent_phone ? `<strong>Phone:</strong> ${escapeHtml(student.parent_phone)}` : ''}
            </div>
            ${allergyHtml}
            ${foodHtml}
          </div>
          <div class="card-actions">
            <button class="btn-pulse btn-toggle-checkin" style="background:${isCheckedIn ? '#16a34a' : '#2563eb'}; color:white;">
              ${isCheckedIn ? 'Checked In ✓' : 'Check In'}
            </button>
            <button class="btn-pulse btn-edit" style="background:#64748b; color:white;">Edit</button>
            <button class="delete-btn btn-delete" style="padding: 4px 8px;">✕</button>
          </div>
        </div>
      `;
      grid.append(cardHtml);
    });
  }

  // Check-In / Check-Out Toggle Action
  $(document).on('click', '.btn-toggle-checkin', function() {
    const card = $(this).closest('.student-card');
    const isCheckedIn = card.hasClass('checked-in');

    $.post('vbs_api.php', {
      action: 'toggle_attendance',
      student_id: card.data('id'),
      vbs_session_id: card.data('session-id'),
      attendance_action: isCheckedIn ? 'checkout' : 'checkin'
    }, function(res) {
      if (res.success) loadRoster();
      else alert("Error: " + res.message);
    }, 'json');
  });

  // Live Search Filter
  $('#searchInput').on('keyup', function() {
    const val = $(this).val().toLowerCase();
    $('.student-card').filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
    });
  });

  // Modal Handlers
  $('#addNewStudentBtn').click(function() {
    $('#vbsStudentForm')[0].reset();
    $('#vbs_id').val('');
    $('#modalTitle').text('Register Student for VBS');
    $('#vbsModal').css('display', 'flex');
  });

  $('#closeModalBtn').click(function() { $('#vbsModal').hide(); });

  // Load single student for edit
  $(document).on('click', '.btn-edit', function() {
    const vbsId = $(this).closest('.student-card').data('vbs-id');
    $.get('vbs_api.php', { action: 'get_student', vbs_id: vbsId }, function(res) {
      if (res.success) {
        const s = res.data;
        $('#vbs_id').val(s.vbs_id);
        $('#vbs_sessions_id').val(s.vbs_sessions_id);
        $('#contact_id').val(s.contact_id);
        $('#parent_id').val(s.parent_id);
        $('#class_id').val(s.class_id);
        $('#allergies').val(s.allergies);
        $('#food_restrictions').val(s.food_restrictions);
        $('#medical_notes').val(s.medical_notes);

        $('#modalTitle').text('Edit VBS Student Record');
        $('#vbsModal').css('display', 'flex');
      }
    }, 'json');
  });

  // Submit Save Student Form
  $('#vbsStudentForm').on('submit', function(e) {
    e.preventDefault();
    $.post('vbs_api.php', $(this).serialize() + '&action=save_student', function(res) {
      if (res.success) {
        $('#vbsModal').hide();
        loadRoster();
      } else {
        alert("Error saving: " + res.message);
      }
    }, 'json');
  });

  // Delete VBS Student Entry
  $(document).on('click', '.btn-delete', function() {
    if (!confirm("Are you sure you want to remove this student from VBS?")) return;
    const vbsId = $(this).closest('.student-card').data('vbs-id');
    $.post('vbs_api.php', { action: 'delete_student', vbs_id: vbsId }, function(res) {
      if (res.success) loadRoster();
    }, 'json');
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

  <h1>VBS Attendance & Roster Management</h1>

  <fieldset class="form-grid-section-short">
    <div style="display:flex; justify-content:space-between; align-items:center; gap: 1rem; width:100%;">
      <input type="text" id="searchInput" placeholder="Search child or parent name..." style="padding:0.6rem; width:60%;">
      <button type="button" id="addNewStudentBtn" class="btn-pulse nbtn">+ Add VBS Student</button>
    </div>
  </fieldset>

  <!-- Roster Grid -->
  <div id="studentGrid" class="vbs-grid"></div>

  <!-- Registration/Edit Modal -->
  <div class="modal" id="vbsModal">
    <div class="modal-content">
      <h2 id="modalTitle">Register VBS Student</h2>
      <form id="vbsStudentForm">
        <input type="hidden" name="vbs_id" id="vbs_id">
        
        <div class="form-group">
          <label for="vbs_sessions_id">VBS Session ID</label>
          <input type="number" name="vbs_sessions_id" id="vbs_sessions_id" value="1" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="contact_id">Child (Contact)</label>
            <select name="contact_id" id="contact_id" required></select>
          </div>
          <div class="form-group">
            <label for="parent_id">Parent/Guardian</label>
            <select name="parent_id" id="parent_id" required></select>
          </div>
        </div>

        <div class="form-group">
          <label for="class_id">Class ID</label>
          <input type="number" name="class_id" id="class_id" required>
        </div>

        <div class="form-group">
          <label for="allergies">Allergies</label>
          <textarea name="allergies" id="allergies" rows="2"></textarea>
        </div>

        <div class="form-group">
          <label for="food_restrictions">Food Restrictions</label>
          <textarea name="food_restrictions" id="food_restrictions" rows="2"></textarea>
        </div>

        <div class="form-group">
          <label for="medical_notes">Medical Notes</label>
          <textarea name="medical_notes" id="medical_notes" rows="2"></textarea>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
          <button type="button" id="closeModalBtn" class="btn-pulse" style="background:#94a3b8;">Cancel</button>
          <button type="submit" class="btn-pulse">Save Record</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>