$(document).ready(function() {
      // Initialize setup dropdown data fields
      populateDropdowns();

      // Track session drop-down updates
      $('#SessionID').on('change', function() {
        const sessionId = $(this).val();
        $('#vbs_class_session_id').val(sessionId);
        resetClassFormFields();

        if (sessionId) {
          $('#class-table-container').removeClass('hidden');
          fetchClasses(sessionId);
        } else {
          $('#class-table-container').addClass('hidden');
          $('#class-table-body').html('<tr><td colspan="4">Please choose a session year to examine records.</td></tr>');
        }
      });

      // Add Session automation router loop binding
      $('#addSession').on('click', function() {
        if (!confirm('Create a new configuration year milestone inside VBS Sessions?')) return;

        $.ajax({
          url: 'class_controller.php',
          type: 'POST',
          data: {
            action: 'add_session'
          },
          dataType: 'json',
          success: function(res) {
            if (res.success) {
              const optionText = res.year + " - " + res.theme;
              $('#SessionID').append(new Option(optionText, res.new_id));
              $('#SessionID').val(res.new_id).trigger('change');
            } else {
              alert('Could not configure new session: ' + res.message);
            }
          }
        });
      });

      // Form submission intercept mapping router channels
      $('#vbs-form').on('submit', function(e) {
        e.preventDefault();

        const sessionId = $('#vbs_class_session_id').val();
        if (!sessionId) {
          alert('Please select a valid Session Year before saving class records.');
          return;
        }

        const isUpdate = $('#vbs_class_id').val() !== '';
        const actionType = isUpdate ? 'update' : 'create';

        $.ajax({
          url: 'class_controller.php?action=' + actionType,
          type: 'POST',
          data: $('#vbs-form').serialize(),
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              resetClassFormFields();
              fetchClasses(sessionId);
            } else {
              alert('Operation failed: ' + response.message);
            }
          },
          error: function() {
            alert('An error occurred during communication processing pipelines.');
          }
        });
      });

      $('#cancel-edit-btn').on('click', function() {
        resetClassFormFields();
      });


    // Populate dynamic select structures seamlessly on boot sequence
    function populateDropdowns() {
      $.ajax({
        url: 'class_controller.php',
        type: 'GET',
        data: {
          action: 'get_dropdowns'
        },
        dataType: 'json',
        success: function(data) {
          const sessionSelect = $('#SessionID');
          data.sessions.forEach(function(sess) {
            const displayTxt = sess.vbs_year + " - " + sess.vbs_theme;
            sessionSelect.append(new Option(displayTxt, sess.vbs_sessions_id));
          });

          const teacherSelect = $('#vbs_class_teacher_id');
          data.teachers.forEach(function(teach) {
            const displayTxt = teach.last_name + ", " + teach.first_name;
            teacherSelect.append(new Option(displayTxt, teach.contact_id));
          });
        }
      });
    }

    // Dynamic processing engine fetching table row data
    function fetchClasses(sessionId) {
      $.ajax({
        url: 'class_controller.php',
        type: 'GET',
        data: {
          action: 'read',
          vbs_sessions_id: sessionId
        },
        dataType: 'json',
        success: function(classes) {
          const tbody = $('#class-table-body');
          tbody.empty();

          if (classes.length === 0) {
            tbody.html('<tr><td colspan="4">No classes setup found matching this session timeline parameters.</td></tr>');
            return;
          }

          classes.forEach(function(cls) {
            const escapedCls = JSON.stringify(cls).replace(/'/g, "&apos;");
            const row = `
                        <tr>
                            <td><b>${cls.vbs_class_desc}</b></td>
                            <td>Ages ${cls.vbs_class_age_start} - ${cls.vbs_class_age_end}</td>
                            <td>${cls.teacher_name || 'Unassigned'}</td>
                            <td class="row-actions">
                                <button type="button" class="btn-pulse" onclick="populateEditClass('${escapedCls}')">Edit</button>
                                <button type="button" class="btn-danger" onclick="deleteClass(${cls.vbs_class_id})">Delete</button>
                            </td>
                        </tr>
                    `;
            tbody.append(row);
          });
        }
      });
    }

    function populateEditClass(classJsonStr) {
      const cls = JSON.parse(classJsonStr);
      $('#vbs_class_id').val(cls.vbs_class_id);
      $('#vbs_class_desc').val(cls.vbs_class_desc);
      $('#vbs_class_age_start').val(cls.vbs_class_age_start);
      $('#vbs_class_age_end').val(cls.vbs_class_age_end);
      $('#vbs_class_teacher_id').val(cls.vbs_class_teacher_id || '');
      $('#class-form-title').text('Modify VBS Class Details');
      $('#submit-class-btn').text('Update Class');
      $('#cancel-edit-btn').removeClass('hidden');
    }

    function deleteClass(classId) {
      if (!confirm('Are you sure you want to permanently delete this class entry?')) return;
      const sessionId = $('#vbs_class_session_id').val();
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
        }
      });
    }

    function resetClassFormFields() {
      $('#vbs_class_id').val('');
      $('#vbs_class_desc').val('');
      $('#vbs_class_age_start').val('');
      $('#vbs_class_age_end').val('');
      $('#vbs_class_teacher_id').val('');
      $('#class-form-title').text('Add VBS Class');
      $('#submit-class-btn').text('Save Class');
      $('#cancel-edit-btn').addClass('hidden');
    }
});