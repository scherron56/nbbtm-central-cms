/**
 * Fetches standardized dropdown data and populates session/teacher select elements.
 */
function populateDropdowns() {
  $.ajax({
    url: 'class_controller.php',
    type: 'GET',
    data: { action: 'get_dropdowns' },
    dataType: 'json',
    success: function(res) {
      if (!res || !res.success) {
        console.error("Failed to load dropdown data:", res ? res.message : "No response payload received.");
        return;
      }

      // 1. Populate Sessions Dropdown (Handles both #SessionID and #sessionSelect)
      const $sessionSelect = $('#SessionID, #sessionSelect');
      
      if ($sessionSelect.length && Array.isArray(res.sessions)) {
        // Clear existing non-default options
        $sessionSelect.find('option:not(:first)').remove();

        res.sessions.forEach(function(session) {
          let label = session.vbs_year;
          if (session.vbs_theme) {
            label += ' - ' + session.vbs_theme;
          }

          $sessionSelect.append(
            $('<option></option>')
              .val(session.vbs_sessions_id)
              .text(label)
          );
        });
      }

      // 2. Populate Teachers Dropdown
      const $teacherSelect = $('#vbs_class_teacher_id');
      
      if ($teacherSelect.length && Array.isArray(res.teachers)) {
        $teacherSelect.find('option:not(:first)').remove();

        res.teachers.forEach(function(teacher) {
          $teacherSelect.append(
            $('<option></option>')
              .val(teacher.vbs_class_teacher_id)
              .text(teacher.teacher_name)
          );
        });
      }
    },
    error: function(jqXHR, textStatus, errorThrown) {
      console.error("AJAX Error in populateDropdowns:", textStatus, errorThrown);
    }
  });
}

// Execute on document ready
$(document).ready(function() {
  populateDropdowns();
});