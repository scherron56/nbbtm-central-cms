<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bootstrap 5 Template</title>

  <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->
  <!-- <link href="https://simplemaps.com/data/us-zips" > -->
  <link rel="stylesheet" href="css/style.css">

  <!-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script> -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <script>
    //    const express = require('express');
    // const cors = require('cors'); // Install via: npm install cors
    // const app = express();

    // app.use(cors({ origin: 'http://localhost:3000' })); // Allow your VS Code frontend port 

    $(document).ready(function() {
          // Fire off a single request to get data for both dropdown components
          $.ajax({
            url: 'getLists.php',
            type: 'POST',
            dataType: 'json',
            success: function(data) {

              //  $('#contacts').empty();
              //  $('#title').empty()

              $.each(data.contacts, function(index, item) {
                $('#contactID').append(
                  $('<option></option>').val(item.contact_id).text(item.fullname)
                );
              });
              // 2. Process and append title records
              $.each(data.titles, function(index, item) {
                $('#title_id').append(
                  $('<option></option>').val(item.title_id).text(item.titleabr)
                );
              });
              $.each(data.marital, function(index, item) {
                $('#marital_status').append(
                  $('<option></option>').val(item.marital_status_id).text(item.marital_status)
                );
              });
              $.each(data.phonetype, function(index, item) {
                $('#phone_1_type').append(
                  $('<option></option>').val(item.phone_type_id).text(item.phone_type_desc)
                );
              });

              $.each(data.phonetype, function(index, item) {
                $('#phone_2_type').append(
                  $('<option></option>').val(item.phone_type_id).text(item.phone_type_desc)
                );
              });
              $.each(data.phonetype, function(index, item) {
                $('#phone_3_type').append(
                  $('<option></option>').val(item.phone_type_id).text(item.phone_type_desc)
                );
              });
            }, //error message
            error: function(xhr, status, error) {
              console.error("Error loading lists  " + error)
            }

          }); // End of ajax


          // Fetch Contact/Member data
          $('#contactID').change(function() {
            let contactid = $(this).val();
                        // let contactid = 3004;
            // console.log(contactid);

            if (contactid !== "") {

              $.ajax({
                url: 'getContact.php',
                type: 'POST',
                data: {
                  contactid: contactid
                },
                dataType: 'json',
                success: function(data) {
                  // Populate the form fields with the returned JSON
                   if (data.error) {
                      console.error("Server Error: " + data.error);
                      return;
                    }

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
                  $('#submitBtn').text('Update Contact/Member');
                  $('#resetBtn').show();
                }
              }); //end of ajax
            } else {
              // Reset form if "--Add New Contact/Member --" is chosen
              $('#contact-form')[0].reset();
              $('#contact_id').val('');
              $('#submitBtn').text('Add Contact/Member');
              $('#resetBtn').hide();
            }
          }); //End of Onchange


          // Reset button functionality
         $('#resetBtn').click(function() {
            $('#contact-form')[0].reset();
            $('#contact_id').val('');
            $('#submitBtn').text('Add Contact/Member');
            $(this).hide();
          });

          // Handle Insert/Updates via AJAX
          $('#contact-form').submit(function(e) {
              e.preventDefault(); // Prevent standard page reload

              // Clear previous error messages before making the call
              $('#firstError, #lastError, #emailError, #responseMsg').text('');

              let contactId = $('#contact_id').val();
              let curAction = (contactId === "" || contactId === "0") ? 'Adding' : 'Updating';
              // Determine target URL based on whether contactId exists
              let targetUrl = (contactId === "" || contactId === "0") ? 'saveContact.php' : 'updateContact.php';
              $.ajax({
                  url: targetUrl,
                  type: 'POST',
                  data: $(this).serialize(), // Automatically packs all populated form elements
                  dataType: 'json',
                  success: function(response) {
                    if (response.status === 'success') {
                      $('#responseMsg').html('<p style="color:green;">' + response.message + '</p>');
                      if(curAction=='Adding'){
                        const $newOption = new Option($('#last_name').val() + ", " + $('#first_name').val(), response.id);
                        $('#contactID').append($newOption).val(response.id); 
                        // Add and select the
                        $('#contact_id').val(response.id);
                        $('#submitBtn').text('Update Contact/Member');
                        $('#resetBtn').show();
                      }
                      // if (targetUrl === 'saveContact.php') {
                      //   $fullname = data.last_name.", ".data.first_name;
                      //   const conSelectName = data.contactId;
                      //   const dynamicDropdown = document.getElementById('contactID');
                      //   if (dynamicDropdown) {
                      //     const newOption = new Option(fullname, data.contactId);
                      //     dynamicDropdown.add(newOption);
                      //     dynamicDropdown.value = data.contactId; // Select the newly added option
                      //   } else {
                      //     console.error("Could not find contact ID.");
                      //   }
                      // }
                    }
                    },
                    error: function(xhr) {
                      // Handles 400 Bad Request (Validation) and 500 Internal Server errors cleanly
                      let response = xhr.responseJSON;

                      if (response && response.errors) {
                        if (response.errors.firstname) $('#responseMsg').text(response.errors.firstname).addClass('text-danger');
                        if (response.errors.lastname) $('#responseMsg').text(response.errors.lastname).addClass('text-danger');
                        if (response.errors.c_email) $('#responseMsg').text(response.errors.c_email).addClass('text-danger');
                      } else if (response && response.message) {
                        $('#responseMsg').html('<p style="color:red;">' + response.message + '</p>');
                      } else {
                        $('#responseMsg').html('<p style="color:red;">An unexpected system error occurred.</p>');
                      }
                    }
                  });
              });

          });
  </script>
</head>

<body class="nborder">
  <?php require_once("config/db.php") ?>

  <form id="contact-select" name="contact-select" class="nborder">

    <div class="selContact nborder" name="conSelect" id="conSelect">
      <div class="form-field form-row1 nborder"  style="--colspan: 2;">
        <label for="contactID" class="nborder selBox" style="color: white;"><h3>Select Member/Contact</label>
        <select name="contactID" id="contactID">
          <option value="">--Select--</option>
        </select>
        <div><button type="button" id="addMember" class="nb-btn" >Add New Contact/Member</button></div>
      </div>
      <!-- <input type="button" id="addMember" class="nb-btn" >Add New Contact/Member</input> -->
  </div>

  </form>
  <form id="contact-form" name="contact-form" class="nborder" >
    <div id="personalInfo" name="personalInfo">
      <legend style="margin: 1rem; padding: 10px;">
        <h2>Personal Information</h2>
      </legend>
      <input type="hidden" name="contact_id" id="contact_id"></input>
      <div class="persInform nborder">
       <div class="form-field nborder" style="--colspan: 1;">
        <!-- <input type="hidden" name="contact_id" id="contact_id"></input></div> -->
       <div class="form-field form-row2 nborder" style="--colspan: 1;">
          <label for="title_id">Title</label>
          <select name="title_id" id="title_id">
            <option value="">--Select--</option>
          </select>
        </div>


        <div class="form-field form-row2 nborder" style="--colspan: 1;">
          <label for="first_name">First Name</label>
          <input type="text" name="first_name" id="first_name">
          <!-- <div id="firstError" class="nborder"> </div> -->
        </div>
        <div class="form-field form-row2 nborder" style="--colspan: 1;">
          <label for="middle_name">Middle Name</label>
          <input type="text" name="middle_name" id="middle_name" />
        </div>

        <div class="form-field form-row2 nborder" style="--colspan: 1;">
          <label for="last_name">Last Name</label>
          <input type="text" id="last_name" name="last_name"/></label>
          <!-- <div id="lastError" class="nborder"> </div> -->
        </div>

        <div class="form-field form-row3 nborder" style="--colspan: 1;">
          <label for="date_of_birth">Date of Birth</label>
          <input type="date" id="date_of_birth" name="date_of_birth">
        </div>

        <div class="form-field form-row3 nborder" style="--colspan: 1;">
          <label for="gender">Gender</label>
          <select id="gender" name="gender" size="1">
            <option value="">Select</option>
            <option value="F">Female</option>
            <option value="M">Male</option>
          </select>
        </div>
        <div class="form-field form-row3 nborder" style="--colspan: 1;">
          <label for="marital_status">Marital Status</label>
          <select id="marital_status" name="marital_status" size="1">
            <option value="">Select</option>
          </select>
        </div>
        <div class="form-field form-row3 nborder" style="--colspan: 1;">
          <label for="anniv_date">Anniversary Date</label>
          <input type="date" id="anniv_date" name="anniv_date" />
        </div>
      </div>

    </div>
    <div class="conInform" id="contactInfo" name="contactInfo">
      <legend>
        <h2>Contact Information</h2>
      </legend>
      <div class="form-field form-row1 nborder" style="--colspan: 4;">
        <label for="address_1">Address</label>
        <input type="text" id="address_1" name="address_1" autocomplete="off" />
      </div>
      <div class="  form-field form-row2 nborder" style="--colspan: 2;">
        <label for="city">City</label>
        <input type="text" id="city" name="city" />
      </div>
      <div class="form-field form-row2 nborder" style="--colspan: 1;">
        <label for="state">State</label>
        <input type="text" id="state" name="state" />
      </div>
      <div class="form-field form-row2 nborder" style="--colspan: 1;">
        <label for="zipcode" name="zipcode">Zip Code</label>
        <input type="text" id="zipcode" name="zipcode" />
      </div>

      <div class="form-field form-row3 nborder" style="--colspan: 1;">
        <label for="phone_1" name="phone_1">Primary Phone</label>
        <input type="tel" id="phone_1" name="phone_1">
      </div>
      <div class="form-field form-row3 nborder" style="--colspan: 1;">
        <label for="phone_1_type">Type</label>
        <select name="phone_1_type" id="phone_1_type">
          <option value="">--Select--</option>
        </select>
      </div>
      <div class="form-field form-row3 nborder" style="--colspan: 1;">
        <label for="phone_2" name="phone_2">Secondary Phone</label>
        <input type="tel" id="phone_2" name="phone_2">
      </div>
      <div class="form-field form-row3 nborder" style="--colspan: 1;">
        <label for="phone_2_type">Type</label>
        <select name="phone_2_type" id="phone_2_type">
          <option value="">--Select--</option>
        </select>
      </div>
      <div class="form-field form-row4 nborder" style="--colspan: 1;">
        <label for="emergency_contact" name="emergency_contact">Emergency Contact Name</label>
        <input type="text" id="emergency_contact" name="emergency_contact">
      </div>
      <div class="form-field form-row4 nborder" style="--colspan: 1;">
        <label for="phone_3" name="phone_3">Emergency Contact Phone</label>
        <input type="tel" id="phone_3" name="phone_3">
      </div>
      <div class="form-field form-row4 nborder" style="--colspan: 1;">
        <label for="phone_3_type">Type</label>
        <select name="phone_3_type" id="phone_3_type">
          <option value="">--Select--</option>
        </select>
      </div>
      <div class="form-field form-row5 nborder" style="--colspan: 4;">
        <label for="c_email" name="c_email">E-mail Address</label>
        <input type="email" id="c_email" name="c_email" autocomplete="off">
        <div id="c_emailError" class="nborder"></div>
      </div>
    </div>
    <div class="conInform" id="membershipInfo" name="membershipInfo">
      <legend>
        <h2>Membership Information</h2>
      </legend>
      <div class="form-field form-row1 nborder" style="--colspan:1; size: 2ch;">
        <input type="checkbox" id="is_member" name="is_member">
        <label for="is_member">Member</label>
      </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="join_date">Date Joined</label>
        <input type="date" id="join_date" name="join_date">
      </div>

      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <input type="checkbox" id="is_baptisted" name="is_baptisted">
        <label for="is_baptisted">Baptized</label>
      </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="baptized_date">Date Baptized</label>
        <input type="date" id="baptized_date" name="baptized_date">
      </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <input type="checkbox" id="is_active" name="is_active">
        <label for="is_active">Active</label>
      </div>
    </div>
    <input type="submit" class="nb-btn" name="submitBtn">
    <input type="reset" class="nb-btn" name="resetBtn">
  </form>
  <div id="responseMsg"></div>
  </main>
</body>

</html>