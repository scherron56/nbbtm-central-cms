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

    $(document).ready(function () {
      // Fire off a single request to get data for both dropdown components
      $.ajax({
        url: 'getLists.php',
        type: 'POST',
        dataType: 'json',
        success: function (data) {

        //  $('#contacts').empty();
        //  $('#title').empty()

         $.each(data.contacts, function (index, item) {
          $('#contactID').append(
              $('<option></option>').val(item.contact_id).text(item.fullname)
            );
          });
                    // 2. Process and append title records
         $.each(data.titles, function (index, item) {
          $('#title').append(
              $('<option></option>').val(item.title_id).text(item.titleabr)
            );
          });

          $.each(data.phonetype, function (index, item) {
          $('#phone1type').append(
              $('<option></option>').val(item.phone_type_id).text(item.phone_type_desc)
            );
          });

          $.each(data.phonetype, function (index, item) {
          $('#phone2type').append(
              $('<option></option>').val(item.phone_type_id).text(item.phone_type_desc)
            );
          });
          $.each(data.phonetype, function (index, item) {
          $('#phone3type').append(
              $('<option></option>').val(item.phone_type_id).text(item.phone_type_desc)
            );
          });
 }, //error message
          error: function(xhr, status, error){
            console.error("Error loading lists  " + error)
          }
    
     });

 
      // Fetch Contact/Member data
      $('#contactID').change(function () {
        let contactid = $(this).val();
       // console.log(contactid);

        if (contactid !== "") {

          $.ajax({
            url: 'getContact.php',
            type: 'POST',
            data: { contactid: contactid },
            dataType: 'json',
            success: function (data) {
              // Populate the form fields with the returned JSON
                //  if (data.error) {
                //     console.error("Server Error: " + data.error);
                //     return;
                //   }

              $('#contact_id').val(contactid|| '');
              $('#title').val(data.title_id || '');
              $('#firstname').val(data.first_name || '');
              $('#middlename').val(data.middle_name || '');
              $('#lastname').val(data.last_name || '');
              $('#address1').val(data.address_1 || '');
              $('#city').val(data.city || '');
              $('#state').val(data.state || '');
              $('#zipcode').val(data.zipcode || '');
              $('#dob').val(data.date_of_birth || '');
              $('#gender').val(data.gender || '');
              $('#marital').val(data.marital_status || '');
              $('#anniv').val(data.anniv_date || '');
              $('#phone1').val(data.phone_1 || '');
              $('#phone2').val(data.phone_2 || '');
              $('#phone3').val(data.phone_3 || '');
              $('#phone1type').val(data.phone_1_type || '');
              $('#phone2type').val(data.phone_2_type || '');
              $('#phone3type').val(data.phone_3_type || '');
              $('#email').val(data.c_email || '');
              $('#ismember').val(data.is_member || '');
              $('#dateJoined').val(data.join_date || '');
              $('#isbaptized').val(data.is_baptized || '');
              $('#baptizedDate').val(data.baptized_date || '');
              $('#isactive').val(data.is_active || '');
              $('#submitBtn').text('Update Contact/Member');
              $('#resetBtn').show();
            }
          });
        }
        else {
          // Reset form if "--Add New Contact/Member --" is chosen
          $('#contact-form')[0].reset();
          $('#contact-select')[0].reset();
          $('#contact_id').val('');
          $('#submitBtn').text('Add Contact/Member');
          $('#resetBtn').hide();
        }
      });
      // Reset button functionality
      $('#resetBtn').click(function () {
        $('#contact-form')[0].reset();
        $('#contact-select')[0].reset();
        $('#contact_id').val('');
        $('#submitBtn').text('Add Contact/Member');
        $(this).hide();
      });

      // Handle Insert/Updates via AJAX
      $('#contact-form').submit(function (e) {
        e.preventDefault(); // Prevent standard page reload
             $.ajax({
                url: 'saveContact.php',
                type: 'POST',
                data: $(this).serialize(), // Converts all 4 fields into a URL-encoded string
                dataType: 'json',
                success: function(response) {
                    if(response.status === 'success') {
                        $('#responseMsg').html('<p style="color:green;">' + response.message + '</p>');
                        $('#contact-form')[0].reset(); // Clear the form
                    } else {
                      if(response.errors.firstname)  $('#firstError').text(response.erros.firstname);
                      if(response.errors.lastname) $('#lastError') text(response.erros.firstname);
                      if(response.errors.email) $('#emailError') text(response.erros.email);
                   } 
                error: function() {
                    $('#responseMsg').html('<p style="color:red;">An error occurred on the server.</p>');
                  }
            });
      });
    });
  </script>
</head>

<body class="nborder">
  <?php  require_once("config/db.php") ?>

  <form id="contact-select" name="contact-select" class="nborder">

    <div class="container nborder" name="conSelect" id="conSelect">
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="contactID" class="nborder">Members/Contacts</label>
        <select name="contactID" id="contactID">
          <option value="">--Select--</option>
        </select>
      </div>
    </div>
  </form>
  <form id ="contact-form" name="contact-form" class="noborder">

    <div class="container" id="personalInfo" name="personalInfo">
      <legend>
        <h2>Personal Information</h2>
      </legend>
      <input type="hidden" name="contact_id" id="contact_id"></input>

      <div class="form-field form-row2 nborder" style="--colspan: 1;">
        <label for="title">Title</label>
        <select name="title" id="title">
          <option value="">--Select--</option>
        </select>
      </div>
     <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="firstname">First Name</label>
        <input type="firstname" name="firstname" id="firstname" required>
        <div id="firstError"></div>    
      <div class="form-field form-row2 nborder" style="--colspan: 1;">
        <label for="middlename">Middle Name</label>
        <input type="middlename" name="middlename" id="middlename" />
      </div>
      <div class="form-field form-row2 nborder" style="--colspan: 1;">
        <label for="lastname">Last Name</label>
        <input type="text" id="lastname" name="lastname" required/></label>
        <div id="lastError"></div>    
      </div>
      <div class="form-field form-row3 nborder" style="--colspan: 5;">
        <label for="address1">Address</label>
        <input type="text" id="address1" name="address"  autocomplete="off"/>
      </div>
      <!-- Row 2: Two equal columns -->
      <div class="form-field form-row4 nborder" style="--colspan: 4;">
        <label for="city">City</label>
        <input type="text" id="city" name="city" />
      </div>
      <div class="form-field form-row4 nborder" style="--colspan: 1;">
        <label for="state">State</label>
        <input type="text" id="state" name="state" />
      </div>
      <div class="form-field form-row4 nborder" style="--colspan: 1;">
        <label for="zipcode" name="zipcode">Zip Code</label>
        <input type="text" id="zipcode" name="zipcode" />
      </div>
      <div class="form-field form-row4 nborder" style="--colspan: 1;">
        <label for="dob">Date of Birth</label>
        <input type="date" id="dob" name="dob">
      </div>
      <div class="form-field form-row4 nborder" style="--colspan: 1;">
        <label for="gender">Gender</label>
        <select id="gender" name="gender" size="1">
          <option value="">Select</option>
          <option value="F">Female</option>
          <option value="M">Male</option>
        </select>
      </div>
      <div class="form-field form-row4 nborder" style="--colspan: 1;">
        <label for="marital">Marital Status</label>
        <select id="marital" name="marital" size="1">
          <option value="">Select</option>
          <option value="S">Single</option>
          <option value="M">Married</option>
        </select>
      </div>
      <div class="form-field form-row4 nborder" style="--colspan: 1;">
        <label for="anniv">Anniversary Date</label>
        <input type="text" id="anniv" name="anniv">
      </div>
    </div>
    <div class="container2" id="contactInfo" name="contactInfo">
      <legend>
        <h2>Contact Information</h2>
      </legend>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="phone1" name="phone1">Primary Phone</label>
        <input type="tel" id="phone1" name="phone1">
      </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="phone1type">Type</label>
        <select name="phone1type" id="phone1type">
          <option value="">--Select--</option>
        </select>
      </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="phone2" name="phone2">Secondary Phone</label>
        <input type="tel" id="phone2" name="phone2">
      </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="phone2type">Type</label>
        <select name="phone2type" id="phone2type">
          <option value="">--Select--</option>
        </select>
      </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="phone3" name="phone3">Other Phone</label>
        <input type="tel" id="phone3" name="phone3">
      </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="phone3type">Type</label>
        <select name="phone3type" id="phone3type">
          <option value="">--Select--</option>
        </select>
      </div>
      <div class="form-field form-row2 nborder" style="--colspan: 6;">
        <label for="email" name="email">E-mail Address</label>
        <input type="email" id="email" name="email" autocomplete="off">
        <div id="emaailError"></div>    
      </div>
    </div>
    <div class="container3" id="membershipInfo" name="membershipInfo">
      <legend>
        <h2>Membership Information</h2>
      </legend>
      <div class="form-field form-row1 nborder" style="--colspan:1; size: 2ch;">
        <input type="checkbox" id="ismember" name="ismember">
        <label for="ismember">Member</label>
      </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="dateJoined">Date Joined</label>
        <input type="date" id="dateJoined" name="dateJoined">

      </div>

      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <input type="checkbox" id="isbaptisted" name="isbaptisted">
        <label for="isbaptisted">Baptized</label>
      </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="baptizedDate">Date Baptized</label>
        <input type="date" id="baptizedDate" name="baptizedDate">
      </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <input type="checkbox" id="isactive" name="isactive">
        <label for="isactive">Active</label>
      </div>
    </div>
    <input type="submit" class="nb-btn" name="submitBtn">
    <input type="reset" class="nb-btn" name="resetBtn">
  </form>
  <div id="responseMsg"></div>
  </main>
</body>

</html>