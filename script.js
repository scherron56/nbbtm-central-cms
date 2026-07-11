$(document).ready(function() {
  
  // 1. Load Dropdowns dynamically on system initialization
  updateFormLists();

  // Helper Visibility function mapping member visibility requirements 
  function toggleMinistrySection() {
    if ($('#is_member').val() === "1") {
      $('#ministry-section').show();
    } else {
      $('#ministry-section').hide();
      $('#checkbox-container').empty(); // Evacuate values if unassigned
    }
  }

  // Bind visibility rules directly to direct customer click interaction changes
  $('#is_member').change(function() {
    toggleMinistrySection();
  });

  // 2. Fetch Contact data on selection changes
  $('#contactID').change(function() {
    let contactid = $(this).val();

    if (contactid !== "") {
      $.ajax({
        url: 'getContact.php',
        type: 'POST',
        data: { contactid: contactid },
        dataType: 'json',
        success: function(data) {
          if (data.error) {
            console.error("Server Error: " + data.error);
            return;
          }
          
          // Populate core base tracking identifiers
          $('#contact_id').val(contactid || '');
          $('#first_name').val(data.first_name || '');
          $('#last_name').val(data.last_name || '');
          $('#c_email').val(data.c_email || '');
          
          // Map membership control and update UI layout visibility tracks instantly
          $('#is_member').val(data.is_member || '0');
          toggleMinistrySection();

          // Render checkboxes only if active church membership is flag-matched
          if ($('#is_member').val() === "1" && data.ministryList && data.ministryList.length > 0) {
            $('#checkbox-container').empty();

            $.each(data.ministryList, function(index, item) {
              // Read relational arrays to see if checkbox should be pre-checked
              let isChecked = data.ministries.some(function(m) {
                return m.group_id === item.group_id;
              });

              // Construct card block wrapper to enforce 2-column structure formatting bounds
              const $ministryItem = $('<div>').addClass('ministry-card').css({
                "display": "flex",
                "flex-direction": "column",
                "gap": "4px",
                "margin-bottom": "5px"
              });

              const $checkbox = $('<input>').attr({
                type: 'checkbox',
                id: 'ministry_id_' + item.group_id,
                name: 'ministries[]',
                value: item.group_id,
                checked: isChecked
              });

              const $label = $('<label>').attr('for', 'ministry_id_' + item.group_id).text(' ' + item.group_name);
              const $rowHeader = $('<div style="display:flex; align-items:center; gap:8px;"></div>').append($checkbox).append($label);

              $ministryItem.append($rowHeader);

              // Construct nested dynamic drop-down selector wrapper
              const $roleSelect = $('<select>').attr({
                id: 'role_id_' + item.group_id,
                name: 'roles[' + item.group_id + ']'
              }).css({ "margin-left": "24px", "max-width": "200px" });

              // Iterate master lists to drop functional authorization criteria in place
              $.each(data.roleList, function(rIndex, roleOption) {
                const $option = $('<option>').attr('value', roleOption.role_id).text(roleOption.role_name);
                
                let isSelected = data.ministries.some(function(ministry) {
                  return ministry.group_id === item.group_id && ministry.role_id === roleOption.role_id;
                });

                if (isSelected) { $option.attr('selected', 'selected'); }
                $roleSelect.append($option);
              });

              $ministryItem.append($roleSelect);
              $('#checkbox-container').append($ministryItem);
            });
          }

          $('#submitBtn').text('Update Contact/Member');
          $('#resetBtn').show();
        }
      });
    } else {
      resetFormState();
    }
  });

  // 3. Complete Secure Unified Ajax Form Submission Logic Hook
  $('#contact-form').submit(function(e) {
    e.preventDefault();

    $('#firstError, #lastError, #emailError, #responseMsg').text('').removeClass('text-danger');

    let contactId = $('#contact_id').val();
    let isAdding = (contactId === "" || contactId === "0" || contactId === null);
    let $submitBtn = $('#submitBtn');

    $submitBtn.prop('disabled', true).text('Processing...');

    $.ajax({
      url: 'saveContact.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(response) {
        $submitBtn.prop('disabled', false);

        if (response.status === 'success') {
          $('#responseMsg').html('<p style="color:green;">' + response.message + '</p>');
          
          if (isAdding) {
            let fullName = $('#last_name').val().trim() + ", " + $('#first_name').val().trim();
            if ($('#is_member').val() === "1") { fullName += " (Member)"; }
            
            $('#contactID').append(new Option(fullName, response.id)).val(response.id);
            $('#contact_id').val(response.id);
          }
          
          $('#submitBtn').text('Update Contact/Member');
          $('#resetBtn').show();
        }
      },
      error: function(xhr) {
        $submitBtn.prop('disabled', false);
        $submitBtn.text(isAdding ? 'Save & Continue' : 'Update Contact/Member');

        let response = xhr.responseJSON;
        if (response && response.errors) {
          if (response.errors.firstname) $('#firstError').text(response.errors.firstname).addClass('text-danger');
          if (response.errors.lastname) $('#lastError').text(response.errors.lastname).addClass('text-danger');
          if (response.errors.c_email) $('#emailError').text(response.errors.c_email).addClass('text-danger');
        } else {
          $('#responseMsg').text(response && response.message ? response.message : "Server error encountered.").addClass('text-danger');
        }
      }
    });
  });

  // 4. Form Reset Action Controller Wrapper
  $('#resetBtn').click(function() {
    resetFormState();
  });

  function resetFormState() {
    $('#contact-form')[0].reset();
    $('#contact_id').val('');
    $('#contactID').val('');
    $('#firstError, #lastError, #emailError, #responseMsg').text('');
    toggleMinistrySection();
    $('#resetBtn').hide();
    $('#submitBtn').text('Save & Continue');
  }

  function updateFormLists() {
    $.ajax({
      url: "getLists.php",
      type: 'POST',
      dataType: 'json',
      success: function(data) {
        $.each(data.contacts, function(i, item) {
          $('#contactID').append($('<option>').val(item.contact_id).text(item.fullname));
        });
      }
    });
  }
});
