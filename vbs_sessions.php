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
  <title>New Beginnings Baptist Tabernacle Ministries</title>
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

    .alert-success {
      background-color: #d1fae5;
      border: 1px solid #6ee7b7;
      color: #065f46;
    }

    .alert-error {
      background-color: #fee2e2;
      border: 1px solid #fca5a5;
      color: #991b1b;
    }

    .read-only-banner {
      background-color: #f1f5f9;
      border-left: 4px solid #0ea5e9;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
      color: #334155;
    }

    /* Data grid container formatting */
    .table-container {
      width: 100%;
      max-width: 1000px;
      margin: 2rem auto;
      background: #fff;
      padding: 1.5rem;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      color: #28089a;
    }

    .data-table th,
    .data-table td {
      padding: 12px;
      border-bottom: 1px solid #cbd5e1;
    }

    .data-table th {
      background-color: #043b8f;
      color: #fff;
    }

    .action-flex {
      display: flex;
      gap: 8px;
      margin-top: 1rem;
    }

    .row-actions {
      display: flex;
      gap: 5px;
    }

    /* Dashboard UI Layout Styling */
    .session-dashboard {
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }

    .dashboard-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 2px solid #e2e8f0;
      padding-bottom: 12px;
      margin-bottom: 15px;
    }

    .dashboard-header h2 {
      margin: 0;
      color: #043b8f;
      font-size: 1.4rem;
    }

    .dashboard-badge {
      background-color: #0284c7;
      color: #ffffff;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .dashboard-badge.completed {
      background-color: #059669;
    }

    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 15px;
    }

    .dashboard-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      padding: 12px 16px;
    }

    .dashboard-card .card-label {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #64748b;
      margin-bottom: 4px;
      font-weight: 700;
    }

    .dashboard-card .card-value {
      font-size: 1.05rem;
      font-weight: 600;
      color: #1e293b;
    }

    .dashboard-card.full-width {
      grid-column: 1 / -1;
    }
  </style>

  <script>
    $(document).ready(function() {
      const CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;
      let isSessionCompleted = false;

      function showStatusMessage(message, type = 'success') {
        let $box = $('#status-message');
        $box.removeClass('alert-success alert-error')
          .addClass(type === 'success' ? 'alert-success' : 'alert-error')
          .html(message)
          .stop(true, true)
          .fadeIn(200);

        if (type === 'success') {
          setTimeout(function() {
            $box.fadeOut(500);
          }, 5000);
        }
      }

      function clearStatusMessage() {
        $('#status-message').fadeOut(200).empty();
      }

      populateDropdowns();

      function getActiveSessionId() {
        return $('#SessionID').val() || $('#vbs_class_session_id').val() || '';
      }

      function setActiveSessionId(id) {
        $('#SessionID').val(id);
        $('#vbs_class_session_id').val(id);
        $('#vbs_sessions_id').val(id);
      }

      $('#vbs-form').on('input change', 'input, select', function() {
        $(this).removeClass('input-error');
        $(this).siblings('.error-message-text').remove();
      });

      $('#SessionID').on('change', function() {
        clearStatusMessage();
        const sessionId = $(this).val();
        setActiveSessionId(sessionId);

        resetClassFormFields();

        if (sessionId) {
          $('#class-table-container').removeClass('hidden');
          fetchClasses(sessionId);
          fetchSessionDetails(sessionId);
        } else {
          $('#class-table-container').addClass('hidden');
          $('#class-table-body').html('<tr><td colspan="4">Please choose a session year to examine records.</td></tr>');
          resetSessionFormFields();
          toggleViewMode(false);
        }
      });

      function fetchSessionDetails(sessionId) {
        $.ajax({
          url: 'class_controller.php',
          type: 'GET',
          data: {
            action: 'get_session',
            vbs_sessions_id: sessionId
          },
          dataType: 'json',
          success: function(res) {
            if (res.success && res.data) {
              isSessionCompleted = (parseInt(res.data.vbs_sessions_completed, 10) === 1);

              $('#vbs_year').val(res.data.vbs_year || '');
              $('#vbs_start_date').val(res.data.vbs_start_date || '');
              $('#vbs_end_date').val(res.data.vbs_end_date || '');
              $('#vbs_theme').val(res.data.vbs_theme || '');
              $('#vbs_theme_scripture').val(res.data.vbs_theme_scripture || '');

              // Update Dashboard Labels
              $('#dash-title').text((res.data.vbs_year || '') + ' - ' + (res.data.vbs_theme || 'VBS Session'));
              $('#dash-year').text(res.data.vbs_year || 'N/A');
              
              const startDate = res.data.vbs_start_date ? res.data.vbs_start_date : 'N/A';
              const endDate = res.data.vbs_end_date ? res.data.vbs_end_date : 'N/A';
              $('#dash-dates').text(`${startDate} to ${endDate}`);
              
              $('#dash-theme').text(res.data.vbs_theme || 'N/A');
              $('#dash-scripture').text(res.data.vbs_theme_scripture || 'N/A');

              if (isSessionCompleted) {
                $('#dash-status-badge').addClass('completed').text('Completed (Read-Only)');
              } else {
                $('#dash-status-badge').removeClass('completed').text('Active Session');
              }

              // Evaluate display: Show dashboard if non-admin OR session is completed
              const showDashboard = !CAN_EDIT || isSessionCompleted;
              toggleViewMode(showDashboard);
            }
          },
          error: function(xhr, status, error) {
            console.error('Failed to load session details:', xhr.responseText || error);
            const message = status === 'parsererror'
              ? 'The server returned invalid session data.'
              : 'Failed to load the selected session.';
            showStatusMessage(message, 'error');
          }
        });
      }

      function toggleViewMode(showDashboard) {
        if (showDashboard) {
          $('#session-fieldset-edit').addClass('hidden');
          $('#session-dashboard-view').removeClass('hidden');
          $('#add-class-fieldset, #add-class-actions').addClass('hidden');
        } else {
          $('#session-fieldset-edit').removeClass('hidden');
          $('#session-dashboard-view').addClass('hidden');
          if (CAN_EDIT) {
            $('#add-class-fieldset, #add-class-actions').removeClass('hidden');
          }
        }
      }

      function resetSessionFormFields() {
        isSessionCompleted = false;
        $('#vbs_year').val('');
        $('#vbs_start_date').val('');
        $('#vbs_end_date').val('');
        $('#vbs_theme').val('');
        $('#vbs_theme_scripture').val('');
        
        $('#dash-title').text('Session Overview');
        $('#dash-year').text('N/A');
        $('#dash-dates').text('N/A');
        $('#dash-theme').text('N/A');
        $('#dash-scripture').text('N/A');
      }

      function saveSessionDetails() {
        if (!CAN_EDIT || isSessionCompleted) return;
        clearStatusMessage();
        const sessionId = getActiveSessionId();
        const yearVal = $('#vbs_year').val().trim();

        if (!yearVal) return;

        $.ajax({
          url: 'class_controller.php',
          type: 'POST',
          data: {
            action: 'save_session',
            vbs_sessions_id: sessionId,
            vbs_year: yearVal,
            vbs_session_start: $('#vbs_start_date').val() || null,
            vbs_session_end: $('#vbs_end_date').val() || null,
            vbs_theme: $('#vbs_theme').val(),
            vbs_theme_scripture: $('#vbs_theme_scripture').val()
          },
          dataType: 'json',
          success: function(res) {
            if (res.success && res.vbs_sessions_id) {
              const newId = res.vbs_sessions_id;

              if ($(`#SessionID option[value="${newId}"]`).length === 0) {
                $('#SessionID').append(new Option(yearVal, newId));
              }

              setActiveSessionId(newId);
              $('#class-table-container').removeClass('hidden');
            }
          }
        });
      }

      $('#vbs_year, #vbs_start_date, #vbs_end_date, #vbs_theme, #vbs_theme_scripture').on('change', function() {
        if (CAN_EDIT && !isSessionCompleted) saveSessionDetails();
      });

      $('#saveSessionBtn').on('click', function() {
        if (!CAN_EDIT || isSessionCompleted) return;
        saveSessionDetails();
        showStatusMessage('Session details updated successfully!', 'success');
      });

      $('#addSession').on('click', function() {
        if (!CAN_EDIT) return;
        clearStatusMessage();
        const currentYear = new Date().getFullYear().toString();

        resetSessionFormFields();
        resetClassFormFields();

        $('#class-table-body').empty().html('<tr><td colspan="4">No classes added yet for this new session. Fill out the form below to add one.</td></tr>');

        $('#vbs_year').val(currentYear);

        $.ajax({
          url: 'class_controller.php',
          type: 'POST',
          data: {
            action: 'add_session',
            vbs_theme_title: 'New Session'
          },
          dataType: 'json',
          success: function(res) {
            if (res.success && res.new_id) {
              const newId = res.new_id;
              const displayName = res.combined_name || (currentYear + ' - New Session');

              if ($(`#SessionID option[value="${newId}"]`).length === 0) {
                $('#SessionID').append(new Option(displayName, newId));
              }

              setActiveSessionId(newId);
              toggleViewMode(false);
              $('#class-table-container').removeClass('hidden');
              $('#vbs_year').focus();
              showStatusMessage('New session initialized.', 'success');
            } else {
              showStatusMessage('Could not initialize new session: ' + (res.message || 'Unknown error'), 'error');
            }
          }
        });
      });

      $('#vbs-form').on('submit', function(e) {
        e.preventDefault();
        if (!CAN_EDIT || isSessionCompleted) return;
        clearStatusMessage();

        $('.input-error').removeClass('input-error');
        $('.error-message-text').remove();

        const sessionId = getActiveSessionId();
        const $descField = $('#vbs_class_desc');
        const $startAgeField = $('#vbs_class_age_start');
        const $endAgeField = $('#vbs_class_age_end');

        const classDesc = $descField.val().trim();
        const ageStart = parseInt($startAgeField.val(), 10);
        const ageEnd = parseInt($endAgeField.val(), 10);
        let hasError = false;

        if (!sessionId || sessionId === "0") {
          showStatusMessage('Please select or create a valid Session Year before saving class records.', 'error');
          return;
        }
        if (classDesc === '') {
          showFieldError($descField, 'Class Description is required.');
          hasError = true;
        }
        if (isNaN(ageStart)) {
          showFieldError($startAgeField, 'Please enter a valid start age.');
          hasError = true;
        }
        if (isNaN(ageEnd)) {
          showFieldError($endAgeField, 'Please enter a valid end age.');
          hasError = true;
        }
        if (!isNaN(ageStart) && !isNaN(ageEnd) && ageStart > ageEnd) {
          showFieldError($startAgeField, 'Start age cannot exceed end age bounds.');
          showFieldError($endAgeField, 'End age cannot be smaller than start age bounds.');
          hasError = true;
        }

        if (hasError) return;

        const classId = $('#vbs_class_id').val();
        const actionType = classId ? 'update' : 'create';

        $.ajax({
          url: 'class_controller.php',
          type: 'POST',
          data: {
            action: actionType,
            vbs_class_id: classId,
            vbs_class_session_id: sessionId,
            vbs_class_desc: classDesc,
            vbs_class_age_start: ageStart,
            vbs_class_age_end: ageEnd,
            vbs_class_teacher_id: $('#vbs_class_teacher_id').val()
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              showStatusMessage(classId ? 'Class updated successfully.' : 'Class created successfully.', 'success');
              resetClassFormFields();
              fetchClasses(sessionId);
            } else {
              showStatusMessage('Operation failed: ' + response.message, 'error');
            }
          }
        });
      });

      $('#cancel-edit-btn').on('click', function() {
        resetClassFormFields();
      });

      function showFieldError($element, message) {
        $element.addClass('input-error');
        $element.after(`<span class="error-message-text">${message}</span>`);
      }

      function populateDropdowns() {
        $.ajax({
          url: 'class_controller.php',
          type: 'GET',
          data: {
            action: 'get_dropdowns'
          },
          dataType: 'json',
          success: function(data) {
            if (!data.success && data.message) {
              showStatusMessage('Error: ' + data.message, 'error');
              return;
            }

            const sessionSelect = $('#SessionID');
            sessionSelect.find('option:not(:first)').remove();

            if (data.sessions && Array.isArray(data.sessions)) {
              data.sessions.forEach(function(sess) {
                let label = sess.vbs_year;
                if (sess.vbs_theme && sess.vbs_theme.trim() !== '') {
                  label += ' - ' + sess.vbs_theme;
                }
                if (parseInt(sess.vbs_sessions_completed, 10) === 1) {
                  label += ' (Completed)';
                }
                sessionSelect.append(new Option(label, sess.vbs_sessions_id));
              });
            }

            const teacherSelect = $('#vbs_class_teacher_id');
            if (teacherSelect.length) {
              teacherSelect.find('option:not(:first)').remove();
              if (data.teachers && Array.isArray(data.teachers)) {
                data.teachers.forEach(function(teach) {
                  teacherSelect.append(new Option(teach.teacher_name, teach.vbs_class_teacher_id));
                });
              }
            }
          },
          error: function(xhr, status, error) {
            console.error('Failed to fetch dropdown options:', xhr.responseText || error);
            let message = 'Failed to load session options from server.';
            if (status === 'parsererror') {
              message += ' The server returned invalid JSON.';
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
              message += ' ' + xhr.responseJSON.message;
            }
            showStatusMessage(message, 'error');
          }
        });
      }

      function fetchClasses(sessionId) {
        $.ajax({
          url: 'class_controller.php',
          type: 'GET',
          data: {
            action: 'read',
            vbs_sessions_id: sessionId
          },
          dataType: 'json',
          success: function(res) {
            const tbody = $('#class-table-body');
            tbody.empty();

            const classesList = Array.isArray(res) ? res : [];

            if (classesList.length === 0) {
              tbody.html('<tr><td colspan="4">No classes found for this session.</td></tr>');
              return;
            }

            classesList.forEach(function(cls) {
              const $tr = $('<tr>');
              $tr.append(`<td><b>${cls.vbs_class_desc || ''}</b></td>`);
              $tr.append(`<td>Ages ${cls.vbs_class_age_start || ''} - ${cls.vbs_class_age_end || ''}</td>`);
              $tr.append(`<td>${cls.teacher_name || 'Unassigned'}</td>`);

              const $actionsTd = $('<td class="row-actions">');

              if (CAN_EDIT && !isSessionCompleted) {
                const $editBtn = $('<button type="button" class="btn-pulse">Edit</button>')
                  .data('classData', cls)
                  .on('click', function() {
                    populateEditClass($(this).data('classData'));
                  });

                const $deleteBtn = $('<button type="button" class="btn-danger">Delete</button>')
                  .on('click', function() {
                    deleteClass(cls.vbs_class_id);
                  });

                $actionsTd.append($editBtn).append($deleteBtn);
              } else {
                $actionsTd.append('<em>Read Only</em>');
              }

              $tr.append($actionsTd);
              tbody.append($tr);
            });
          },
          error: function(xhr, status, error) {
            console.error('Failed to fetch classes:', xhr.responseText || error);
            showStatusMessage(
              status === 'parsererror' ? 'The server returned invalid class data.' : 'Failed to load classes for this session.',
              'error'
            );
          }
        });
      }

      function populateEditClass(cls) {
        if (!CAN_EDIT || isSessionCompleted) return;
        clearStatusMessage();

        $('.input-error').removeClass('input-error');
        $('.error-message-text').remove();

        $('#vbs_class_id').val(cls.vbs_class_id);
        $('#vbs_class_desc').val(cls.vbs_class_desc);
        $('#vbs_class_age_start').val(cls.vbs_class_age_start);
        $('#vbs_class_age_end').val(cls.vbs_class_age_end);
        $('#vbs_class_teacher_id').val(cls.vbs_class_teacher_id || '');

        const currentSession = cls.vbs_class_session_id || getActiveSessionId();
        setActiveSessionId(currentSession);

        $('#class-form-title').text('Modify VBS Class Details');
        $('#submit-class-btn').text('Update Class');
        $('#cancel-edit-btn').removeClass('hidden');

        $('html, body').animate({
          scrollTop: $('#vbs-form').offset().top - 20
        }, 'fast');
      }

      function deleteClass(classId) {
        if (!CAN_EDIT || isSessionCompleted) return;
        clearStatusMessage();

        if (!confirm('Are you sure you want to permanently delete this class entry?')) return;
        const sessionId = getActiveSessionId();
        $.ajax({
          url: 'class_controller.php',
          type: 'POST',
          data: {
            action: 'delete',
            vbs_class_id: classId
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              showStatusMessage('Class deleted successfully.', 'success');
              fetchClasses(sessionId);
            } else {
              showStatusMessage('Delete failed: ' + response.message, 'error');
            }
          }
        });
      }

      function resetClassFormFields() {
        clearStatusMessage();
        const activeSessionId = getActiveSessionId();

        $('#vbs_class_id').val('');
        $('#vbs_class_desc').val('');
        $('#vbs_class_age_start').val('');
        $('#vbs_class_age_end').val('');
        $('#vbs_class_teacher_id').val('');
        $('#class-form-title').text('Add VBS Class');
        $('#submit-class-btn').text('Save Class');
        $('#cancel-edit-btn').addClass('hidden');

        $('#vbs_class_session_id').val(activeSessionId);
      }
    });
  </script>
</head>

<body>
  <?php require_once("config/db.php"); ?>
  <?php include 'include/header.php'; ?>

  <h1>Vacation Bible School Session</h1>

  <div id="status-message" class="alert-box"></div>

  <fieldset class="form-grid-section-short">
    <?php if ($canEdit): ?>
      <div class="field-group" style="--colspan: 1;">
        <button type="button" id="addSession" class="btn-pulse nbtn">Add Session</button>
      </div>
    <?php endif; ?>
    <div class="field-group" style="--colspan: 2;">
      <label for="SessionID">
        <h3 style="color: blue; margin: 0;">Select Session Year</h3>
      </label>
      <select name="sessionID" id="SessionID">
        <option value="">--Select--</option>
      </select>
    </div>
  </fieldset>

  <form id="vbs-form" name="vbs-form">
    <input type="hidden" id="vbs_sessions_id" name="vbs_sessions_id">

    <!-- Non-Admin or Completed Session Dashboard Display -->
    <div id="session-dashboard-view" class="session-dashboard hidden">
      <div class="dashboard-header">
        <h2 id="dash-title">Session Overview</h2>
        <span id="dash-status-badge" class="dashboard-badge">Read-Only</span>
      </div>
      <div class="dashboard-grid">
        <div class="dashboard-card">
          <div class="card-label">Session Year</div>
          <div class="card-value" id="dash-year">N/A</div>
        </div>
        <div class="dashboard-card">
          <div class="card-label">Dates</div>
          <div class="card-value" id="dash-dates">N/A</div>
        </div>
        <div class="dashboard-card">
          <div class="card-label">Theme</div>
          <div class="card-value" id="dash-theme">N/A</div>
        </div>
        <div class="dashboard-card full-width">
          <div class="card-label">Theme Scripture</div>
          <div class="card-value" id="dash-scripture">N/A</div>
        </div>
      </div>
    </div>

    <!-- Admin Form Edit Fieldset -->
    <fieldset id="session-fieldset-edit" class="form-grid-section-9">
      <!-- Line 1: Year, Dates, and Theme -->
      <div class="field-group" style="--colspan: 2;">
        <label for="vbs_year">Session Year</label>
        <input type="text" id="vbs_year" name="vbs_year">
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="vbs_start_date">Start Date</label>
        <input type="date" id="vbs_start_date" name="vbs_start_date">
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="vbs_end_date">End Date</label>
        <input type="date" id="vbs_end_date" name="vbs_end_date">
      </div>
      <div class="field-group" style="--colspan: 3;">
        <label for="vbs_theme">Session Theme</label>
        <input type="text" id="vbs_theme" name="vbs_theme">
      </div>

      <!-- Line 2: Theme Scripture (6 cols) + Save Button pushed right (3 cols) -->
      <div class="field-group" style="--colspan: 6;">
        <label for="vbs_theme_scripture">Theme Scripture</label>
        <input type="text" id="vbs_theme_scripture" name="vbs_theme_scripture">
      </div>
      <?php if ($canEdit): ?>
        <div class="field-group" style="--colspan: 3; display: flex; align-items: flex-end; justify-content: flex-end;">
          <button type="button" id="saveSessionBtn" class="btn-primary nbtn" style="width: auto;">Save Session Details</button>
        </div>
      <?php endif; ?>
    </fieldset>

    <div id="class-table-container" class="table-container hidden">
      <h3>Active Classes for Selected Session</h3>
      <table class="data-table">
        <thead>
          <tr>
            <th>Description</th>
            <th>Ages</th>
            <th>Teacher</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="class-table-body">
          <tr>
            <td colspan="4">Please choose a session year to examine records.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <fieldset id="add-class-fieldset" class="form-grid-section-9 hidden">
      <legend>
        <h2 id="class-form-title">Add VBS Class</h2>
      </legend>

      <input type="hidden" id="vbs_class_session_id" name="vbs_class_session_id">
      <input type="hidden" id="vbs_class_id" name="vbs_class_id">

      <div class="field-group" style="--colspan: 3">
        <label for="vbs_class_desc">Class Description</label>
        <input type="text" id="vbs_class_desc" name="vbs_class_desc">
      </div>

      <div class="field-group" style="--colspan: 2">
        <label for="vbs_class_age_start">Class Starting Age</label>
        <input type="text" id="vbs_class_age_start" name="vbs_class_age_start">
      </div>

      <div class="field-group" style="--colspan: 2">
        <label for="vbs_class_age_end">Class Ending Age</label>
        <input type="text" id="vbs_class_age_end" name="vbs_class_age_end">
      </div>

      <div class="field-group" style="--colspan: 2">
        <label for="vbs_class_teacher_id">Class Teacher</label>
        <select name="vbs_class_teacher_id" id="vbs_class_teacher_id">
          <option value="">--Select--</option>
        </select>
      </div>
    </fieldset>

    <div id="add-class-actions" class="action-flex hidden">
      <button type="submit" class="btn-primary nbtn" id="submit-class-btn">Save Class</button>
      <button type="button" class="btn-secondary nbtn hidden" id="cancel-edit-btn">Cancel Edit</button>
    </div>
  </form>

  <?php include_once 'include/footer.php'; ?>
</body>

</html>