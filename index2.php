<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Beginnings Baptist Tabernacle Ministries</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <script>

      $(document).ready(function() {

      // Initial Load of Dropdown Components
      updateFormLists();

      // FIXED: Added contactId parameter so the function knows what to request
      function updateMinistries(contactId) {
        $.ajax({
          url: "getMinistries.php",
          type: 'POST',
          dataType: 'json',
          data: { contact_id: contactId }, 
          success: function(data) {
            $('#checkbox-container').empty(); 
            $.each(data.ministries, function(index, item) {
              const checkboxId = 'ministry_id' + item.ministry_id; // FIXED: Unified naming
              const checkbox = $('<input>').attr({
                type: 'checkbox',
                id: checkboxId,
                name: 'ministries[]',
                value: item.ministry_id
              });
              const label = $('<label>').attr('for', checkboxId).text(item.ministry_name); // FIXED: Label match
              $('#checkbox-container').append(checkbox).append(label).append('<br>');
            });
          },
          error: function(xhr, status, error) {
            console.error("Error loading ministries: " + error);
          }
        });
      }

// 1. The helper function that toggles the section
function toggleMinistrySection(shouldShow) {
  if (shouldShow) {
    $('#ministry-section').show();
  } else {
    $('#ministry-section').hide();
  }
}

$('#is_member').change(function() {
  toggleMinistrySection($(this).is(':checked'));
});

$('#addNewContact, #resetBtn').click(function(e) {
  // If it's a real button submit action prevent default form actions if needed
  // Form fields get cleared...
  
  // CRITICAL: Force hide the ministry section because a new contact starts fresh
  toggleMinistrySection(false);
});

      function updateContactList() {
        $.ajax({
          url: "getLists.php",
          type: 'POST',
          dataType: 'json',
          success: function(data) {
           $('#contactID').empty();
            $('#contactID').html('<option value="">--select--</option>');
            $.each(data.contacts, function(index, item) {
              $('#contactID').append($('<option></option>').val(item.contact_id).text(item.fullname));
            });
          },
          error: function(xhr, status, error) {
            console.error("Error loading lists: " + error);
          }
        });
      }

      function updateFormLists() {
        $.ajax({
          url: "getLists.php",
          type: 'POST',
          dataType: 'json',
          success: function(data) {
            $('#contactID').empty();
            $('#contactID').html('<option value="">--select--</option>');
            $.each(data.contacts, function(index, item) {
              $('#contactID').append($('<option></option>').val(item.contact_id).text(item.fullname));
            });
            $('#title_id').empty();
            $('#title_id').html('<option value="">--select--</option>');
            $.each(data.titles, function(index, item) {
              $('#title_id').append($('<option></option>').val(item.title_id).text(item.titleabr));
            });
            $('#marital_status').empty();
            $('#marital_status').html('<option value="">--select--</option>');
            $.each(data.marital, function(index, item) {
              $('#marital_status').append($('<option></option>').val(item.marital_status_id).text(item.marital_status));
            });
            $('#phone_1_type').empty();
            $('#phone_2_type').empty();
            $('#phone_3_type').empty();
            $('#phone_1_type').html('<option value="">--select--</option>');
            $('#phone_2_type').html('<option value="">--select--</option>');
            $('#phone_3_type').html('<option value="">--select--</option>');
            $.each(data.phonetype, function(index, item) {
              $('#phone_1_type').append($('<option></option>').val(item.phone_type_id).text(item.phone_type_desc));
              $('#phone_2_type').append($('<option></option>').val(item.phone_type_id).text(item.phone_type_desc));
              $('#phone_3_type').append($('<option></option>').val(item.phone_type_id).text(item.phone_type_desc));
            });
          },
          error: function(xhr, status, error) {
            console.error("Error loading lists: " + error);
          }
        });
      }


      $('#addNewContact, #resetBtn').click(function(e) {
          // Clear checkboxes explicitly
          $('#is_member, #is_baptized, #is_active').prop('checked', false);
          
          // Hide the ministry section
          toggleMinistrySection(false);
          
          // Reset submit button text if needed
          $('#submitBtn').text('Save');
        });

      // 2. Fetch Contact/Member data on dropdown change
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

              let contact= data.contact || {};
              // --- POPULATE FORM FIELDS ---
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

              // --- FIX: PROPERLY SET CHECKBOX CHECKED STATES ---
              $('#is_baptized').prop('checked', String(contact.is_baptized) === "1" || contact.is_baptized === true);
              $('#is_active').prop('checked', String(contact.is_active) === "1" || contact.is_active === true);

              // Setting 'is_member' and triggering its change event automatically handles show/hide logic
              $('#is_member')
                .prop('checked', String(contact.is_member) === "1" || contact.is_member === true)
                .trigger('change');


              if (data.ministryList && data.ministryList.length > 0) {
                $('#checkbox-container').empty(); 
                
                $.each(data.ministryList, function(index, item) {
                  // 1. Look up if this contact has a saved allocation row for this specific ministry
                  let savedAlliance = data.ministries.find(function(minObj) {
                    return minObj.min_comm_id === item.min_comm_id;
                  });
                  
                  let isChecked = !!savedAlliance;
                  
                  // 2. This is the role_id from your member_alliance table!
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

                  // 3. Build the drop-down element
                  const roleSelect = $('<select>').attr({
                    id: 'role_id_' + item.min_comm_id, 
                    name: 'roles[' + item.min_comm_id + ']'
                  });
                  
                  roleSelect.append($('<option>').val('').text('--Select Role--'));

                  // 4. Loop through Master Roles to construct options
                  $.each(data.roleList, function(rIndex, roleOption) {
                    const option = $('<option>').attr('value', roleOption.role_id).text(roleOption.role_desc);
                    
                    // CRITICAL MATCHING STEP:
                    // Compares the master list role_id against the role_id retrieved from member_alliance
                    if (savedAlliance && String(roleOption.role_id) === String(allianceRoleId)) {
                      option.attr('selected', 'selected');
                    }
                    
                    roleSelect.append(option);
                  });                 

                  rowDiv.append(roleSelect);
                  $('#checkbox-container').append(rowDiv);
                });
              }
         // --- NEW INITIAL TOGGLE ACTION ---
        // Checks the database evaluation value directly to show/hide immediately on load
        toggleMinistrySection(String(contact.is_member) === "1");     
              
              $('#submitBtn').text('Update Contact');
              $('#resetBtn').show();
            },
            error: function(xhr, status, error) {
              console.error("Error fetching contact: " + error);
            }
          });
        }
      }); // FIXED: Properly closed the change handler, functions, script, and HTML body tags

// --- DELETE CONTACT HANDLER ---
$('#deleteBtn').click(function(e) {
  e.preventDefault(); // Stop standard form submission or page reloads

  // Get the contact ID from the hidden field or select dropdown
  let contactId = $('#contact_id').val();

  // 1. Validation: Ensure a contact is selected before trying to delete
  if (!contactId) {
    alert("Please select a contact to delete.");
    return;
  }

  // 2. Safety Prompt: Always confirm destructive actions
  let contactName = $('#first_name').val() + " " + $('#last_name').val();
  if (!confirm("Are you sure you want to delete " + contactName.trim() + "? This action cannot be undone.")) {
    return;
  }

  let deleteBtn = $(this);
  deleteBtn.prop('disabled', true); // Prevent double clicks

  // 3. Send Ajax request to deleteContact.php
  $.ajax({
    url: 'deleteContact.php',
    type: 'POST',
    data: { contact_id: contactId },
    dataType: 'json',
    success: function(response) {
      if (response.status === 'success') {
        alert(response.message || "Contact deleted successfully.");

        // Clear/Reset the form fields
        $('#contact-form')[0].reset();
        $('#contact_id').val('');
        toggleMinistrySection(false);

        // Reset submit button text back to default if it was set to "Update Contact"
        $('#submitBtn').text('Save');

        // Refresh dropdown lists so the deleted contact is removed
        updateContactList();
      } else {
        alert(response.message || "Failed to delete contact.");
      }
    },
    error: function(xhr, status, error) {
      let response = xhr.responseJSON || {};
      alert(response.message || "An error occurred while attempting to delete the contact.");
      console.error("Delete Exception: " + error);
    },
    complete: function() {
      deleteBtn.prop('disabled', false);
    }
  });
});

// Listen for form submissions
$('#contact-form').on('submit', function(e) {
  e.preventDefault(); // Stop standard page redirects

  // 1. Gather all inputs (handles our array formatting natively)
  let formData = $(this).serialize();

  // Disable button to prevent double-clicks
  let submitBtn = $('#submitBtn');
  submitBtn.prop('disabled', true).text('Processing...');

  // 2. Dispatch data payload to back-end controllers
  $.ajax({
    url: 'saveContact.php', // Replace with the actual filename of your save script
    type: 'POST',
    data: formData,
    dataType: 'json',
    success: function(response) {
      if (response.status === 'success') {
        alert(response.message);
        
        // If it was a new contact, save the returned target ID to the hidden input fields
        if (response.id) {
          $('#contact_id').val(response.id);
        }
        
        // Refresh the main choice dropdown array lists to reflect new metrics
        updateContactList();
      }
    },
    error: function(xhr, status, error) {
      let response = xhr.responseJSON || {};
      
      if (response.errors) {
        // Validation Errors matching your PHP array trackers
        let errorMsg = "Please address the following inputs:\n";
        $.each(response.errors, function(key, text) {
          errorMsg += "- " + text + "\n";
        });
        alert(errorMsg);
      } else {
        // System / Database level tracking connection drops
        console.error("Critical Runtime System Exception: " + error);
        alert("An error occurred while saving. Please try again.");
      }
    },
    complete: function() {
      // Re-enable interactive elements when complete execution settles
      submitBtn.prop('disabled', false);
        submitBtn.text('Save');
    }
  });
});

    });
  </script>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Beginnings Baptist Tabernacle Ministries</title>
<link href="https://api.fontshare.com/v2/css?f[]=bespoke-serif@301,400,500,501,700,701&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <?php require_once("config/db.php") ?>
    <?php include 'header.php'?>
    <h1>Member/Contact Information</h1>
    <form id="contact-form" name="contact-form">
      <fieldset class="form-grid-section-short">
        <div class="field-group" style="--colspan: 1;">
          <button id="addNewContact" class="btn-pulse nbtn">Add Contact </button>
        </div>
      <div class="field-group" style="--colspan: 2; ">
        <label for="contactID" ><h3 style="color :blue;">Select Member/Contact</h3></label>
        <select name="contactID" id="contactID">
          <option value="">--Select--</option>
        </select>
        </div>

    </fieldset>
    <fieldset class="form-grid-section-8">
    <legend>
      <h2>Personal Information</h2>
    </legend>
    <input type="hidden" id="contact_id" name="contact_id">
    <div class="field-group" style="--colspan: 2;">
      <label for=" title_id">Salutation</label>
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
    <div class="field-group" style="--colspan: 2; --rowspan: 2;">
          <label for="date_of_birth">Date of Birth</label>
          <input type="date" id="date_of_birth" name="date_of_birth">
    </div>
    <div class="field-group" style="--colspan: 2; ">
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
       </fieldset>
  
     <fieldset class="form-grid-section-9">
    <legend>
      <h2>Contact Information</h2>
    </legend><!-- Row 1: Full width (Spans all columns) -->
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
        <label for="phone_1" name="phone_1">Primary Phone</label>
        <input type="tel" id="phone_1" name="phone_1">
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="phone_1_type">Phone Type</label>
        <select name="phone_1_type" id="phone_1_type">
          <option value="">--Select--</option>
        </select>
      </div>
      <div class="field-group" style="--colspan: 2; --rowspan: 1;">
        <label for="phone_2" name="phone_2">Secondary Phone</label>
        <input type="tel" id="phone_2" name="phone_2">
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="phone_2_type">Phone Type</label>
        <select name="phone_2_type" id="phone_2_type">
          <option value="">--Select--</option>
        </select>
      </div>
   <div class="field-group" style="--colspan: 4; --rowspan: 1;">
        <label for="emergency_contact" name="emergency_contact">Emergency Contact Name</label>
        <input type="text" id="emergency_contact" name="emergency_contact">
      </div>
        <div class="field-group" style="--colspan: 2; --rowspan: 1;">
        <label for="phone_3" name="phone_3">Emergency Contact Phone</label>
        <input type="tel" id="phone_3" name="phone_3">
      </div>
      <div class="field-group" style="--colspan: 2;">
        <label for="phone_3_type">Phone Type</label>
        <select name="phone_3_type" id="phone_3_type">
          <option value="">--Select--</option>
        </select>
      </div>
      <div class="field-group" style="--colspan: 9;">
        <label for="c_email" name="c_email">E-mail Address</label>
        <input type="email" id="c_email" name="c_email" autocomplete="off">
        <div id="c_emailError" class="nborder"></div>
      </div>
    </fieldset>
 <fieldset class="form-grid-section-short">
   <legend>
    <h2>Membership Information</h2>
  </legend>
  
  <!-- Hidden fallbacks guarantee 0 is sent if checkbox is unchecked -->
  <div class="field-group" style="--colspan: 1;">
    <label for="is_baptized">Baptized</label>
    <input type="hidden" name="is_baptized" value="0">
    <input type="checkbox" id="is_baptized" name="is_baptized" value="1">
  </div> 
  
  <div class="field-group" style="--colspan: 2;">
    <label for="baptized_date">Date Baptized</label>
    <input type="date" id="baptized_date" name="baptized_date">
  </div>
  
  <div class="field-group" style="--colspan: 1;">
    <label for="is_member">Member</label>
    <input type="hidden" name="is_member" value="0">
    <input type="checkbox" id="is_member" name="is_member" value="1">
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

  <!-- Hidden fields to fulfill PHP prepared statement parameter binding requirements -->
  <input type="hidden" name="is_child" value="0">
  <input type="hidden" name="is_head" value="0">
</fieldset>
    <!-- DYNAMIC MINISTRY ALLIANCES AREA (Two-Column Layout Layout) -->
<fieldset id="ministry-section">
  <legend>
    <h2>Ministry Involvement </h2>
    </legend>
       <!-- CSS Grid Container splitting items into 2 equal-width tracks -->
    <div id="checkbox-container" >
      <!-- jQuery dynamically drops card rows in here -->
    </div>
  </fieldset>
      <fieldset class="form-grid-section-short-rght ">
        <div class="field-group" style="--colspan: 1;">
          <button type="submit" id="submitBtn" class="btn-pulse">Save</button>
        </div>
         <div class="field-group" style="--colspan: 1;">
          <button type="button" id="deleteBtn" class="delete-btn"> 
            <span class="btn-text">Delete</span>
                        <!-- SVG Loading Spinner -->
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