<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Beginnings Baptist Tabernacle Ministries</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* Absolute positioning container */
    .fieldset-relative {
      position: relative;
      /* Adds top padding inside fieldset so grid items are pushed down smoothly */
      padding-top: 35px !important;
    }
    
    .top-right-member {
      position: absolute;
      top: 10px;
      right: 20px;
      display: flex;
      align-items: center;
      gap: 6px;
      z-index: 10;
    }

    /* Bold styling exclusively for the top-right Member checkbox label */
    .top-right-member label {
      font-weight: 700;
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <script>
$(document).ready(function() {

  let globalMinistryList = [];
  let globalRoleList = [];

  // Initial Load of Dropdown Components
  updateFormLists();

  function toggleMemberSections(shouldShow) {
    if (shouldShow) {
      $('#membership-section, #ministry-section').show();
    } else {
      $('#membership-section, #ministry-section').hide();
    }
  }

  // Standalone function to render Ministry Checkboxes
  function buildMinistryCheckboxes(ministryList, roleList, savedMinistries = []) {
    $('#checkbox-container').empty(); 

    if (ministryList && ministryList.length > 0) {
      $.each(ministryList, function(index, item) {
        let savedAlliance = (savedMinistries && Array.isArray(savedMinistries)) ? savedMinistries.find(function(minObj) {
          return String(minObj.min_comm_id) === String(item.min_comm_id);
        }) : null;
        
        let isChecked = !!savedAlliance;
        let allianceRoleId = savedAlliance ? savedAlliance.role_id : ''; 

        const checkboxId = 'ministry_id' + item.min_comm_id;
        const checkbox = $('<input>').attr({
          type: 'checkbox',
          id: checkboxId,
          name: 'ministries[]',
          value: item.min_comm_id
        }).prop('checked', isChecked);
        
        const label = $('<label>').attr('for', checkboxId).text(' ' + item.min_comm_name);
        const rowDiv = $('<div>').css('margin-bottom', '10px');
        rowDiv.append(checkbox).append(label).append(' ');

        const roleSelect = $('<select>').attr({
          id: 'role_id_' + item.min_comm_id, 
          name: 'roles[' + item.min_comm_id + ']'
        });
        
        roleSelect.append($('<option>').val('').text('--Select Role--'));

        if (roleList) {
          $.each(roleList, function(rIndex, roleOption) {
            const option = $('<option>').attr('value', roleOption.role_id).text(roleOption.role_desc);
            if (savedAlliance && String(roleOption.role_id) === String(allianceRoleId)) {
              option.attr('selected', 'selected');
            }
            roleSelect.append(option);
          });                 
        }

        rowDiv.append(roleSelect);
        $('#checkbox-container').append(rowDiv);
      });
    }
  }

  $('#is_member').change(function() {
    toggleMemberSections($(this).is(':checked'));
  });

  // Automatically handle Head of Household selection
  $('#is_head').change(function() {
    if ($(this).is(':checked')) {
      let currentContactId = $('#contact_id').val();
      if (currentContactId) {
        $('#family_id').val(currentContactId);
      }
    }
  });

  function updateContactList() {
    let membersOnly = $('input[name="contact_filter"]:checked').val();
    let currentContactId = $('#contactID').val();

    $.ajax({
      url: "getLists.php",
      type: 'POST',
      data: { members_only: membersOnly },
      dataType: 'json',
      success: function(data) {
        $('#contactID').empty().append($('<option>', { value: '', text: '--Select--' }));
        if (data.contacts && Array.isArray(data.contacts)) {
          $.each(data.contacts, function(index, item) {
            $('#contactID').append($('<option>', { value: item.contact_id, text: item.fullname }));
          });
        }
        $('#contactID').val(currentContactId);
      },
      error: function(xhr, status, error) {
        console.error("Error loading contact list: ", error, xhr.responseText);
      }
    });
  }

  function updateFormLists(callback) {
    let membersOnly = $('input[name="contact_filter"]:checked').val();

    // Preserve selected values prior to rebuilding options
    let selContact  = $('#contactID').val();
    let selFamily   = $('#family_id').val();
    let selTitle    = $('#title_id').val();
    let selMarital  = $('#marital_status').val();
    let selPhone1   = $('#phone_1_type').val();
    let selPhone2   = $('#phone_2_type').val();
    let selPhone3   = $('#phone_3_type').val();

    $.ajax({
      url: "getLists.php",
      type: 'POST',
      data: { members_only: membersOnly },
      dataType: 'json',
      success: function(data) {
        // Cache Ministry & Role lists globally
        globalMinistryList = data.ministryList || [];
        globalRoleList = data.roleList || [];

        // Populate Contacts
        $('#contactID').empty().append($('<option>', { value: '', text: '--Select--' }));
        if (data.contacts && Array.isArray(data.contacts)) {
          $.each(data.contacts, function(index, item) {
            $('#contactID').append($('<option>', { value: item.contact_id, text: item.fullname }));
          });
        }

        // Populate Family Dropdown
        $('#family_id').empty().append($('<option>', { value: '', text: '--Select--' }));
        if (data.heads && Array.isArray(data.heads)) {
          $.each(data.heads, function(index, item) {
            $('#family_id').append($('<option>', { value: item.contact_id, text: item.fullname }));
          });
        }

        // Populate Titles
        $('#title_id').empty().append($('<option>', { value: '', text: '--Select--' }));
        if (data.titles && Array.isArray(data.titles)) {
          $.each(data.titles, function(index, item) {
            $('#title_id').append($('<option>', { value: item.title_id, text: item.titleabr }));
          });
        }

        // Populate Marital Status
        $('#marital_status').empty().append($('<option>', { value: '', text: '--Select--' }));
        if (data.marital && Array.isArray(data.marital)) {
          $.each(data.marital, function(index, item) {
            let mId = item.marital_id || item.maritial_id || item.marital_status_id;
            $('#marital_status').append($('<option>', { value: mId, text: item.marital_status }));
          });
        }

        // Populate Phone Types
        $('#phone_1_type, #phone_2_type, #phone_3_type').empty().append($('<option>', { value: '', text: '--Select--' }));
        if (data.phonetype && Array.isArray(data.phonetype)) {
          $.each(data.phonetype, function(index, item) {
            let opt = $('<option>', { value: item.phone_type_id, text: item.phone_type_desc });
            $('#phone_1_type').append(opt.clone());
            $('#phone_2_type').append(opt.clone());
            $('#phone_3_type').append(opt.clone());
          });
        }

        // Re-select prior selections
        if (selContact) $('#contactID').val(selContact);
        if (selFamily) $('#family_id').val(selFamily);
        if (selTitle) $('#title_id').val(selTitle);
        if (selMarital) $('#marital_status').val(selMarital);
        if (selPhone1) $('#phone_1_type').val(selPhone1);
        if (selPhone2) $('#phone_2_type').val(selPhone2);
        if (selPhone3) $('#phone_3_type').val(selPhone3);

        // Build empty checkboxes if brand new contact mode
        if (!$('#contact_id').val() && $('#checkbox-container').is(':empty')) {
          buildMinistryCheckboxes(globalMinistryList, globalRoleList, []);
        }

        if (typeof callback === 'function') {
          callback();
        }
      },
      error: function(xhr, status, error) {
        console.error("Error loading lists: ", error, xhr.responseText);
      }
    });
  }

  $(document).on('change', 'input[name="contact_filter"]', function() {
    updateContactList();
  });

  $('#addNewContact, #resetBtn').click(function(e) {
    $('#contact-form')[0].reset();
    $('#contact_id').val('');
    $('#contactID').val('');
    $('#is_member, #is_baptized, #is_active, #is_head').prop('checked', false);
    
    buildMinistryCheckboxes(globalMinistryList, globalRoleList, []);
    toggleMemberSections(false);
    $('#submitBtn').text('Save');
  });

  // Fetch Contact Data on Selection
  $('#contactID').change(function() {
    let contactid = $(this).val();

    if (contactid === "") {
      $('#contact-form')[0].reset();
      $('#contact_id').val('');
      buildMinistryCheckboxes(globalMinistryList, globalRoleList, []);
      toggleMemberSections(false);
      return;
    }

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

        let contact = data.contact || {};

        // Populate Form Fields
        $('#contact_id').val(contactid || '');
        $('#title_id').val(contact.title_id || '');
        $('#first_name').val(contact.first_name || '');
        $('#middle_name').val(contact.middle_name || '');
        $('#last_name').val(contact.last_name || '');
        $('#address_1').val(contact.address_1 || '');
        $('#city').val(contact.city || '');
        $('#state').val(contact.state || '');
        $('#zipcode').val(contact.zipcode || '');
        $('#date_of_birth').val(contact.date_of_birth || '');
        $('#gender').val(contact.gender || '');
        $('#marital_status').val(contact.marital_status || '');
        $('#anniv_date').val(contact.anniv_date || '');
        $('#phone_1').val(contact.phone_1 || '');
        $('#phone_2').val(contact.phone_2 || '');
        $('#emergency_contact').val(contact.emergency_contact || '');
        $('#phone_3').val(contact.phone_3 || '');
        $('#phone_1_type').val(contact.phone_1_type || '');
        $('#phone_2_type').val(contact.phone_2_type || '');
        $('#phone_3_type').val(contact.phone_3_type || '');
        $('#c_email').val(contact.c_email || '');
        $('#join_date').val(contact.join_date || '');
        $('#baptized_date').val(contact.baptized_date || '');

        let isHead = String(contact.is_head) === "1" || contact.is_head === true;
        $('#is_head').prop('checked', isHead);

        if (isHead) {
          $('#family_id').val(contactid);
        } else {
          $('#family_id').val(data.family_id || '');
        }

        $('#is_baptized').prop('checked', String(contact.is_baptized) === "1" || contact.is_baptized === true);
        $('#is_active').prop('checked', String(contact.is_active) === "1" || contact.is_active === true);

        let isMember = String(contact.is_member) === "1" || contact.is_member === true;
        $('#is_member').prop('checked', isMember);
        
        toggleMemberSections(isMember);

        // Build Ministry Checkboxes using fetched contact alliances
        buildMinistryCheckboxes(data.ministryList, data.roleList, data.ministries);
        
        $('#submitBtn').text('Update Contact');
        $('#resetBtn').show();
      },
      error: function(xhr, status, error) {
        console.error("Error fetching contact: ", error, xhr.responseText);
      }
    });
  });

  // Save handler
  $('#contact-form').on('submit', function(e) {
    e.preventDefault();

    let formData = $(this).serialize();
    let submitBtn = $('#submitBtn');
    submitBtn.prop('disabled', true).text('Processing...');

    $.ajax({
      url: 'saveContact.php',
      type: 'POST',
      data: formData,
      dataType: 'json',
      success: function(response) {
        if (response.status === 'success') {
          alert(response.message);
          let savedId = response.id;
          
          // Re-populate system lists and reload updated contact details
          updateFormLists(function() {
            if (savedId) {
              $('#contactID').val(savedId).trigger('change');
            }
          });
        }
      },
      error: function(xhr, status, error) {
        let response = xhr.responseJSON || {};
        if (response.errors) {
          let errorMsg = "Please address the following inputs:\n";
          $.each(response.errors, function(key, text) {
            errorMsg += "- " + text + "\n";
          });
          alert(errorMsg);
        } else {
          alert("An error occurred while saving. Please try again.");
        }
      },
      complete: function() {
        submitBtn.prop('disabled', false).text('Save');
      }
    });
  });

  // Delete handler
  $('#deleteBtn').click(function(e) {
    e.preventDefault();
    let contactId = $('#contact_id').val();

    if (!contactId) {
      alert("Please select a contact to delete.");
      return;
    }

    if (confirm("Are you sure you want to delete this contact? This action cannot be undone.")) {
      $.ajax({
        url: 'deleteContact.php',
        type: 'POST',
        data: { contact_id: contactId },
        dataType: 'json',
        success: function(response) {
          if (response.status === 'success') {
            alert(response.message);
            $('#addNewContact').click();
            updateFormLists();
          } else {
            alert(response.message || "Failed to delete contact.");
          }
        },
        error: function(xhr, status, error) {
          alert("An error occurred while attempting to delete the contact.");
        }
      });
    }
  });

  toggleMemberSections($('#is_member').is(':checked'));
});
 </script>
</head>

<body>
  <?php require_once("config/db.php") ?>
  <?php include 'header.php'?>

  <h1>Member/Contact Information</h1>

  <form id="contact-form" name="contact-form">
  
<fieldset id="contact-select" class="form-grid-section-short">

  <!-- Radio Button Filter Selection (Default: All Contacts) -->
  <div class="field-group" style="--colspan: 2;">
    <label><h3 style="color: blue; margin-bottom: 5px;">View Option</h3></label>
    <div style="display: flex; gap: 12px; align-items: center; margin-top: 4px;">
      <label for="filter_all" style="font-weight: normal; cursor: pointer;">
        <input type="radio" id="filter_all" name="contact_filter" value="0" checked>
        All Contacts
      </label>
      <label for="filter_members" style="font-weight: normal; cursor: pointer;">
        <input type="radio" id="filter_members" name="contact_filter" value="1">
        Members Only
      </label>
    </div>
  </div>

  <!-- Contact Dropdown -->
  <div class="field-group" style="--colspan: 2;">
    <label for="contactID"><h3 style="color: blue;">Select Member/Contact</h3></label>
    <select name="contactID" id="contactID">
      <option value="">--Select--</option>
    </select>
  </div>
  <div class="field-group" style="--colspan: 2; display: flex; justify-content: center; align-items: center;">
    <button type="button" id="addNewContact" class="btn-pulse nbtn">Add Contact</button>
  </div>

</fieldset>

    <!-- PERSONAL INFORMATION FIELDSET -->
    <fieldset class="form-grid-section-8 fieldset-relative">
      <legend>
        <h2>Personal Information</h2>
      </legend>

      <!-- Member Checkbox styled bold and positioned upper-right -->
      <div class="top-right-member">
        <label for="is_member">Member</label>
        <input type="hidden" name="is_member" value="0">
        <input type="checkbox" id="is_member" name="is_member" value="1">
      </div>

      <input type="hidden" id="contact_id" name="contact_id">

      <!-- Fieldset grid fields shifted down by container padding -->
      <div class="field-group" style="--colspan: 2;">
        <label for="title_id">Salutation</label>
        <select name="title_id" id="title_id">
          <option value="">--Select--</option>
        </select>
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="first_name">First Name</label>
        <input type="text" id="first_name" name="first_name">
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="middle_name">Middle Name</label>
        <input type="text" id="middle_name" name="middle_name" />
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="last_name">Last Name</label>
        <input type="text" id="last_name" name="last_name"/>
      </div>
      <div class="field-group" style="--colspan: 2; --rowspan: 1;">
        <label for="date_of_birth">Date of Birth</label>
        <input type="date" id="date_of_birth" name="date_of_birth">
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="gender">Gender</label>
        <select id="gender" name="gender" size="1">
          <option value="">Select</option>
          <option value="F">Female</option>
          <option value="M">Male</option>
        </select>
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="marital_status">Marital Status</label>
        <select id="marital_status" name="marital_status" size="1">
          <option value="">Select</option>
        </select>
      </div>
      <div class="field-group" style="--colspan: 2; --rowspan: 1;">
        <label for="anniv_date">Anniversary Date</label>
        <input type="date" id="anniv_date" name="anniv_date" />
      </div>
      <div class="field-group" style="--colspan: 2; --rowspan: 3;">
        <label for="is_head">Head of Household</label>
        <input type="hidden" name="is_head" value="0">
        <input type="checkbox" id="is_head" name="is_head" value="1">
      </div>  

      <div class="field-group" style="--colspan: 2;" >
        <label for="family_id">Select Family</label>
        <select name="family_id" id="family_id">
          <option value="">--Select--</option>
        </select>
      </div>     
      
    </fieldset>

    <!-- CONTACT INFORMATION FIELDSET -->
    <fieldset class="form-grid-section-9">
      <legend>
        <h2>Contact Information</h2>
      </legend>
      <div class="field-group" style="--colspan: 9;">
        <label for="address_1">Address</label>
        <input type="text" id="address_1" name="address_1" autocomplete="off" />
      </div>
      <div class="field-group" style="--colspan: 3; --rowspan: 1;">
        <label for="city">City</label>
        <input type="text" id="city" name="city" autocomplete="off" />
      </div>
      <div class="field-group" style="--colspan: 3;">
        <label for="state">State</label>
        <input type="text" id="state" name="state" autocomplete="off" />
      </div>
      <div class="field-group" style="--colspan: 3;">
        <label for="zipcode">Zip Code</label>
        <input type="text" id="zipcode" name="zipcode" autocomplete="off" />
      </div>
      <div class="field-group" style="--colspan: 2; --rowspan: 1;">
        <label for="phone_1">Primary Phone</label>
        <input type="tel" id="phone_1" name="phone_1">
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="phone_1_type">Phone Type</label>
        <select name="phone_1_type" id="phone_1_type">
          <option value="">--Select--</option>
        </select>
      </div>
      <div class="field-group" style="--colspan: 2; --rowspan: 1;">
        <label for="phone_2">Secondary Phone</label>
        <input type="tel" id="phone_2" name="phone_2">
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="phone_2_type">Phone Type</label>
        <select name="phone_2_type" id="phone_2_type">
          <option value="">--Select--</option>
        </select>
      </div>
      <div class="field-group" style="--colspan: 4; --rowspan: 1;">
        <label for="emergency_contact">Emergency Contact Name</label>
        <input type="text" id="emergency_contact" name="emergency_contact">
      </div>
      <div class="field-group" style="--colspan: 2; --rowspan: 1;">
        <label for="phone_3">Emergency Contact Phone</label>
        <input type="tel" id="phone_3" name="phone_3">
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="phone_3_type">Phone Type</label>
        <select name="phone_3_type" id="phone_3_type">
          <option value="">--Select--</option>
        </select>
      </div>
      <div class="field-group" style="--colspan: 9;">
        <label for="c_email">E-mail Address</label>
        <input type="email" id="c_email" name="c_email" autocomplete="off">
        <div id="c_emailError" class="nborder"></div>
      </div>
    </fieldset>

    <!-- MEMBERSHIP INFORMATION FIELDSET -->
    <fieldset id="membership-section" class="form-grid-section-short">
      <legend>
        <h2>Membership Information</h2>
      </legend>
      <div class="field-group" style="--colspan: 1;">
        <label for="is_baptized">Baptized</label>
        <input type="hidden" name="is_baptized" value="0">
        <input type="checkbox" id="is_baptized" name="is_baptized" value="1">
      </div> 
      
      <div class="field-group" style="--colspan: 2;">
        <label for="baptized_date">Date Baptized</label>
        <input type="date" id="baptized_date" name="baptized_date">
      </div>
      
      <div class="field-group" style="--colspan: 2;">
        <label for="join_date">Date Joined</label>
        <input type="date" id="join_date" name="join_date">
      </div>
      
      <div class="field-group" style="--colspan: 1; --rowspan: 1;">
        <label for="is_active">Active</label>
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" id="is_active" name="is_active" value="1">
      </div>

      <input type="hidden" name="is_child" value="0">
    </fieldset>

    <!-- DYNAMIC MINISTRY ALLIANCES AREA -->
    <fieldset id="ministry-section">
      <legend>
        <h2>Ministry Involvement</h2>
      </legend>
      <div id="checkbox-container">
        <!-- jQuery dynamically drops card rows in here -->
      </div>
    </fieldset>

    <fieldset class="form-grid-section-short-rght">
      <div class="field-group" style="--colspan: 1;">
        <button type="submit" id="submitBtn" class="btn-pulse">Save</button>
      </div>
      <div class="field-group" style="--colspan: 1;">
        <button type="button" id="deleteBtn" class="delete-btn"> 
          <span class="btn-text">Delete</span>
          <svg class="spinner" viewBox="0 0 50 50" stroke="currentColor" stroke-width="5" fill="none">
            <circle cx="25" cy="25" r="20" stroke-dasharray="80, 200"></circle>
          </svg>
        </button>
      </div>
      <div class="field-group" style="--colspan: 1;">
        <button type="reset" id="resetBtn" class="btn-pulse">Reset</button>
      </div>
    </fieldset> 
  </form>
</body>
</html>