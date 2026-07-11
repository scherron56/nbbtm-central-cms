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

  <script>
    $(document).ready(function() {
  
    // Initial Load of Dropdown Components
      updateFormLists();

      function updateMinistries(){
        
        $.ajax({
          url: "getMinistries.php",
          type: 'POST',
          dataType: 'json',
          data: { contact_id: contactId }, // Sends the selected contact ID to PHP
          success: function(data) {
            $('#checkbox-container').empty(); // Clear existing checkboxes
            $.each(data.ministries, function(index, item) {
              const checkbox = $('<input>').attr({
                type: 'checkbox',
                id: 'ministry_id' + item.ministry_id,
                name: 'ministries[]',
                value: item.ministry_id
              });
              const label = $('<label>').attr('for', 'ministry_' + item.ministry_id).text(item.ministry_name);
              $('#checkbox-container').append(checkbox).append(label).append('<br>');
            });
          },
          error: function(xhr, status, error) {
            console.error("Error loading ministries: " + error);
          }
        });
      }
      
      function updateContactList(){
        $.ajax({
          url: "getLists.php",
          type: 'POST',
          dataType: 'json',
          success: function(data) {
            $.each(data.contacts, function(index, item) {
              $('#contactID').append($('<option></option>').val(item.contact_id).text(item.fullname));
            });
          },
          error: function(xhr, status, error) {
            console.error("Error loading lists: " + error);
          }
        });
      }

      function updateFormLists(){
        $.ajax({
          url: "getLists.php",
          type: 'POST',
          dataType: 'json',
          success: function(data) {
            $.each(data.contacts, function(index, item) {
              $('#contactID').append($('<option></option>').val(item.contact_id).text(item.fullname));
            });
            $.each(data.titles, function(index, item) {
              $('#title_id').append($('<option></option>').val(item.title_id).text(item.titleabr));
            });
            $.each(data.marital, function(index, item) {
              $('#marital_status').append($('<option></option>').val(item.marital_status_id).text(item.marital_status));
            });
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
              // Populate form fields
              $('#contact_id').val(contactid || '');
              $('#title_id').val(data.title_id || '');
              $('#first_name').val(data.first_name || '');
              $('#middle_name').val(data.middle_name || '');
              $('#last_name').val(data.last_name || '');
              $('#address_1').val(data.address_1 || '');
              $('#city').val(data.city || '');
              $('#state').val(data.state || '');
              $('#zipcode').val(data.zipcode || '');
              $('#date_of_birth').val(data.date_of_birth || '');
              $('#gender').val(data.gender || '');
              $('#marital_status').val(data.marital_status || '');
              $('#anniv_date').val(data.anniv_date || '');
              $('#phone_1').val(data.phone_1 || '');
              $('#phone_2').val(data.phone_2 || '');
              $('#emergency_contact').val(data.emergency_contact || '');
              $('#phone_3').val(data.phone_3 || '');
              $('#phone_1_type').val(data.phone_1_type || '');
              $('#phone_2_type').val(data.phone_2_type || '');
              $('#phone_3_type').val(data.phone_3_type || '');
              $('#c_email').val(data.c_email || '');
              $('#is_member').val(data.is_member || '');
              $('#join_date').val(data.join_date || '');
              $('#is_baptized').val(data.is_baptized || '');
              $('#baptized_date').val(data.baptized_date || '');
              $('#is_active').val(data.is_active || '');
              
                if ($('#is_member').val() === "1") {
                  if (!empty(data.ministries)) {
                    // Populate ministries checkboxes
                    $('#checkbox-container').empty(); // Clear existing checkboxes
                    $.each(data.ministryList, function(index, item) {
                      const checkbox = $('<input>').attr({
                        type: 'checkbox',
                        id: 'ministry_id' + item.group_id,
                        name: 'ministries[]',
                        value: item.group_id,
                        checked: recordsArray.some(function(data.ministries) {
                          return data.ministries.group_id === item.group_id});//find group_id in $ministries if found check box
                      });
                      const label = $('<label>').attr('for', 'ministry_' + item.group_id).text(item.group_name);
                      $('#checkbox-container').append(checkbox).append(label).append('<br>');
                      
                      //add dropdown for role selectiion and match ministries.role_id to ministryLis.role_
                      $.each(data.roleList, function(index, roleItem) {
                        const roleSelect = $('<select>').attr({
                          id: 'role_id' + item.group_id,
                          name: 'roles[' + item.group_id + ']'
                        });
                        $.each(data.roleList, function(index, roleOption) {
                          const option = $('<option>').attr('value', roleOption.role_id).text(roleOption.role_name);
                          if (data.ministries.some(function(ministry) {
                            return ministry.group_id === item.group_id && ministry.role_id === roleOption.role_id;
                          })) {
                            option.attr('selected', 'selected');
                          }
                          roleSelect.append(option);
                        });
                        $('#checkbox-container').append(roleSelect).append('<br>');
                      });
                    });
   
                  }
                  $('#submitBtn').text('Save & Continue');
                } else {
                  $('#submitBtn').text('Update Contact');
                }
              }
              // updateContactList(); // Refresh the contact list after loading a contact
              // Update button text and show reset button
              $('#submitBtn').text('Update Contact/Member');
              $('#resetBtn').show();
            }
          });
        } else {
          // Reset form if New member chosen
          $('#contact-form')[0].reset();
          $('#contact_id').val('');
          $('#submitBtn').text('Add Contact/Member');
          $('#resetBtn').hide();
        }
      });

      // 3. Reset button functionality
      $('#resetBtn').click(function() {
        $('#contact-form')[0].reset();
        updateContactList();
        $('#contact_id').val('');
        $('#submitBtn').text('Add Contact/Member');
        $(this).hide();
      });

$(document).on('click', '.delete-btn', function() {
    const button = $(this);
    
    // Pull the active ID directly from the form field
    const contact_id = $('#contact_id').val();

    // Guard rail: Stop if no record is currently loaded
    if (!contact_id || contact_id === "0" || contact_id === "") {
        alert("No contact is currently selected to delete.");
        return;
    }

    // Prevent double-clicking if already loading
    if (button.hasClass('loading')) return;
    if (!confirm("Are you sure you want to delete this record?")) return;

    // UI Feedback: Show spinner, change text, and disable button
    button.addClass('loading').prop('disabled', true);
    button.find('.btn-text').text('Deleting...');

    $.ajax({
        url: 'deleteContact.php',
        type: 'POST',
        data: { contact_id: contact_id },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Clear the form elements since the record is gone
                $('#contact-form')[0].reset();
                $('#contact_id').val('');
                $('#submitBtn').text('Add Contact/Member');
                $('#resetBtn').hide();
                
                // Remove the item from the dropdown list
                $("#contactID option[value='" + contact_id + "']").remove();
                
                alert("Record deleted successfully.");
            } else {
                alert("Error: " + response.message);
            }
        },
        error: function() {
            alert("An unexpected error occurred.");
        },
        complete: function() {
            // Remove loading state when request finishes
            button.removeClass('loading').prop('disabled', false);
            button.find('.btn-text').text('Delete'); // Or whatever your original text is
        }
    });
});



      // 4. Handle Insert/Updates via AJAX (FIXED BRACKETS)
      $('#contact-form').submit(function(e) {
        e.preventDefault(); 

        $('#firstError, #lastError, #emailError, #responseMsg').text('');

        let contactId = $('#contact_id').val();
        let curAction = (contactId === "" || contactId === "0") ? 'Adding' : 'Updating';
        let targetUrl = (contactId === "" || contactId === "0") ? 'saveContact.php' : 'updateContact.php';

        $.ajax({
          url: targetUrl,
          type: 'POST',
          data: $(this).serialize(),
          dataType: 'json',
          success: function(response) {
            if (response.status === 'success') {
              $('#responseMsg').html('<p style="color:green;">' + response.message + '</p>');
              
              if (curAction === 'Adding') {
                const fullName = $('#last_name').val() + ", " + $('#first_name').val();
                const $newOption = new Option(fullName, response.id);
                const $is_member = $('#is_member').val() === "1" ? " (Member)" : "";

                $('#contactID').append($newOption).val(response.id);
                $('#contact_id').val(response.id);
                // if (response.is_member === "1") { $(submitBtn).text('Continue'); } else { $(submitBtn).text('Update Contact'); }
                $('#submitBtn').text('Update Contact/Member');
                $('#resetBtn').show();
              }
            } else {
              $('#responseMsg').html('<p style="color:red;">' + response.message + '</p>');
            }
          },
          error: function(xhr) {
            let response = xhr.responseJSON;
            if (response && response.errors) {
              if (response.errors.firstname) $('#firstError').text(response.errors.firstname).addClass('text-danger');
              if (response.errors.lastname) $('#lastError').text(response.errors.lastname).addClass('text-danger');
              if (response.errors.c_email) $('#emailError').text(response.errors.c_email).addClass('text-danger');
            } else {
              $('#responseMsg').text("An error occurred on the server.").addClass('text-danger');
            }
          }
        });
      });
    });
  </script>
</head>
<body>
    <?php require_once("config/db.php") ?>
  <header class="site-header">
    <div class="logo-container">
      <svg viewBox="0 0 250 250" width="100%" height="auto" class="scaled-svg" alt="Logo">
        <use href="assets/img/nbbtm-logo-white.svg" alt="#logo" />
      </svg>
    </div>
    <h1>New Beginnings Baptist Tabernacle Ministries</h1>
      <h2 class="break-row">Central Management System</h2>
  </header>
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
      <fieldset class="form-grid-section-short-rght ">
        <div class="field-group" style="--colspan: 1;">
          <button type="submit" id="submitBtn" class="btn-pulse">Save</button>
        </div>
         <div class="field-group" style="--colspan: 1;">
          <button id="deleteBtn" class="delete-btn"> <span class="btn-text">Delete</span>
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