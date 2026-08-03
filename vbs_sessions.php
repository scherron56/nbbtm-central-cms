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
  </style>

  <script>
    $(document).ready(function() {
      // Initialize setup dropdown data fields 
      populateDropdowns();

      function getActiveSessionId() {
        return $('#SessionID').val() || $('#vbs_class_session_id').val() || '';
      }

      function setActiveSessionId(id) {
        $('#SessionID').val(id);
        $('#vbs_class_session_id').val(id);
        $('#vbs_sessions_id').val(id);
      }

      // Clear error styles when a user fixes the content typing
      $('#vbs-form').on('input change', 'input, select', function() {
        $(this).removeClass('input-error');
        $(this).siblings('.error-message-text').remove();
      });

      // Track session drop-down updates 
      $('#SessionID').on('change', function() {
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
        }
      });

      // Fetch Session details via AJAX
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
              $('#vbs_year').val(res.data.vbs_year || '');
              $('#vbs_start_date').val(res.data.vbs_start_date || '');
              $('#vbs_end_date').val(res.data.vbs_end_date || '');
              $('#vbs_theme').val(res.data.vbs_theme || '');
              $('#vbs_theme_scripture').val(res.data.vbs_theme_scripture || '');
            }
          },
          error: function(xhr, status, error) {
            console.error("Fetch session error:", xhr.responseText);
          }
        });
      }

      function resetSessionFormFields() {
        $('#vbs_year').val('');
        $('#vbs_start_date').val('');
        $('#vbs_end_date').val('');
        $('#vbs_theme').val('');
        $('#vbs_theme_scripture').val('');
      }

      function saveSessionDetails() {
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
            vbs_start_date: $('#vbs_start_date').val(),
            vbs_end_date: $('#vbs_end_date').val(),
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
        saveSessionDetails();
      });

      $('#saveSessionBtn').on('click', function() {
        saveSessionDetails();
        alert('Session details updated successfully!');
      });

      // Add Session button handler
      $('#addSession').on('click', function() {
        const currentYear = new Date().getFullYear().toString();

        resetSessionFormFields();
        resetClassFormFields();

        $('#class-table-body').empty().html('<tr><td colspan="4">No classes added yet for this new session. Fill out the form below to add one.</td></tr>');

        $('#vbs_year').val(currentYear);

        $.ajax({
          url: 'class_controller.php',
          type: 'POST',
          data: {
            action: 'save_session',
            vbs_sessions_id: 0,
            vbs_year: currentYear,
            vbs_start_date: '',
            vbs_end_date: '',
            vbs_theme: 'New Session',
            vbs_theme_scripture: ''
          },
          dataType: 'json',
          success: function(res) {
            if (res.success && res.vbs_sessions_id) {
              const newId = res.vbs_sessions_id;

              if ($(`#SessionID option[value="${newId}"]`).length === 0) {
                $('#SessionID').append(new Option(currentYear + ' - New Session', newId));
              }

              setActiveSessionId(newId);
              $('#class-table-container').removeClass('hidden');
              $('#vbs_year').focus();
            } else {
              alert('Could not initialize new session: ' + (res.message || 'Unknown error'));
            }
          },
          error: function(xhr, status, error) {
            console.error("Add session error:", xhr.responseText);
            alert('Failed to initialize session record.');
          }
        });
      });

      // Submit class form
      $('#vbs-form').on('submit', function(e) {
        e.preventDefault();

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
          alert('Please select or create a valid Session Year before saving class records.');
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
              resetClassFormFields();
              fetchClasses(sessionId);
            } else {
              alert('Operation failed: ' + response.message);
            }
          },
          error: function(xhr, status, error) {
            console.error("Save class error:", xhr.responseText);
            alert('An error occurred during communication processing pipelines.');
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
          data: { action: 'get_dropdowns' },
          dataType: 'json',
          success: function(data) {
            const sessionSelect = $('#SessionID');
            if (data.sessions) {
              data.sessions.forEach(function(sess) {
                sessionSelect.append(new Option(sess.vbs_year, sess.vbs_sessions_id));
              });
            }

            const teacherSelect = $('#vbs_class_teacher_id');
            if (data.teachers) {
              data.teachers.forEach(function(teach) {
                teacherSelect.append(new Option(teach.teacher_name, teach.vbs_class_teacher_id));
              });
            }
          },
          error: function(xhr, status, error) {
            console.error("Dropdown load error:", xhr.responseText);
            alert('Failed to load initial dropdown data.');
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
              $tr.append($actionsTd);
              tbody.append($tr);
            });
          },
          error: function(xhr, status, error) {
            console.error("Fetch classes error:", xhr.responseText);
            $('#class-table-body').html('<tr><td colspan="4" style="color:red;">Error fetching class data.</td></tr>');
          }
        });
      }

      function populateEditClass(cls) {
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
      }

      function deleteClass(classId) {
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
              fetchClasses(sessionId);
            } else {
              alert('Delete failed: ' + response.message);
            }
          },
          error: function(xhr, status, error) {
            console.error("Delete error:", xhr.responseText);
            alert('Server error occurred while deleting.');
          }
        });
      }

      function resetClassFormFields() {
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
  <?php include 'header.php'; ?>

  <h1>Vacation Bible School Session</h1>

  <form id="vbs-form" name="vbs-form">
    <fieldset class="form-grid-section-short">
      <div class="field-group" style="--colspan: 1;">
        <button type="button" id="addSession" class="btn-pulse nbtn">Add Session</button>
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="SessionID">
          <h3 style="color: blue; margin: 0;">Select Session Year</h3>
        </label>
        <select name="sessionID" id="SessionID">
          <option value="">--Select--</option>
        </select>
      </div>
    </fieldset>

    <fieldset class="form-grid-section-9">
      <input type="hidden" id="vbs_sessions_id" name="vbs_sessions_id">
      <div class="field-group" style="--colspan: 1;">
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
      <div class="field-group" style="--colspan: 2;">
        <label for="vbs_theme">Session Theme</label>
        <input type="text" id="vbs_theme" name="vbs_theme">
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="vbs_theme_scripture">Theme Scripture</label>
        <input type="text" id="vbs_theme_scripture" name="vbs_theme_scripture">
      </div>
      <div class="field-group" style="--colspan: 9; margin-top: 10px;">
        <button type="button" id="saveSessionBtn" class="btn-primary nbtn">Save Session Details</button>
      </div>
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

    <fieldset class="form-grid-section-9">
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

    <div class="action-flex">
      <button type="submit" class="btn-primary nbtn" id="submit-class-btn">Save Class</button>
      <button type="button" class="btn-secondary nbtn hidden" id="cancel-edit-btn">Cancel Edit</button>
    </div>
  </form>
</body>

</html>