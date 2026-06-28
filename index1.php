<?php
require_once 'config/db.php';
  // 1. Fetch Contacts
$contacts=[];
$titles=[];
$phonetype=[];

    $sqlcontacts = $db->query("SELECT contact_id, CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name, ' ')) as fullname FROM contacts");
    $contacts = $sqlcontacts->fetch_all(MYSQLI_ASSOC);

 
    // 2. Fetch Titles (Uncommented and operational)
    $sqlTitles = $db->query("SELECT title_id, titleabr FROM title");
    $titles = $sqlTitles->fetch_all(MYSQLI_ASSOC);


    //3.  Fetch Phone types
    $sqlphone= $db->query("SELECT phone_type_id, phone_type_desc FROM phone_type");
    $phonetype = $sqlphone->fetch_all(MYSQLI_ASSOC);
?>
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
    $(document).ready(function () {
       // Fire off a single request to get data for both dropdown components
        $.ajax({
            url: 'include/getLists.php',
            type: 'POST',
            dataType: 'json',
            success: function (data) {
                // Error safety handling Check
                var titleSelect = $('#titles');
                $.each(data.titles, function (data, item) {
                    titleSelect.append(
                        $('<option></option>').val(item.title_id).text(item.titleabr)
                    );
                });
            },
            error: function (xhr, status, error) {
                console.error('Titles error:', error);
            }
        });

         $('#contacts').on('change', function() {
            var contactId = $(this).val(); // Get selected value

      // Fetch Contact/Member data
          $.ajax({
            url: 'getContact.php',
            type: 'POST',
            data: { contact_id: contactId },
            dataType: 'JSON',
            success: function (data) {
              // Populate the form fields with the returned JSON
              $('#contact_id').val(data.contact_id || '');
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
              $('submit_btn').text('Update Contact/Member');
              $('#reset_btn').show();
            }
          });
        },
        else {
          // Reset form if "--Add New Contact/Member --" is chosen
          $('#contact-form')[0].reset();
          $('#contact_id').val('');
          $('#submitBtn').text('Add Contact/Member');
          $('#resetBtn').hide();
        }
      });
      // Reset button functionality
      $('reset_btn').click(function () {
        $('#contact-form')[0].reset();
        $('#contact_id').val('');
        $('#submiBtn').text('Add Contact/Member');
        $(this).hide();
      })

      // Handle Insert/Updates via AJAX
      $('#contact-form').submit(function (e) {
        e.preventDefault(); // Prevent standard page reload
        $.ajax({
          url: 'include/saveContact.php',
          type: 'POST',
          data: $(this).serialize(),
          dataType: 'json',
          success: function (response) {
            $('#response_message').html(response);
          },
          error: function (xhr, status, error) {
            console.error('update_member error:', error);
          }
        });
      });
    });
  </script>
</head>

<body class="nborder">
  <form id="contact-form" name="contact-form" class="nborder" autocomplete="on">

    <div class="container nborder" name="conSelect" id="conSelect">
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="contacts" class="nborder">Members/Contacts</label>
        <select name="contacts" id="contacts" autocomplete="on">
          <option value="">--Select--</option>
     <?php foreach ($contacts as $row): ?>
        <option value="<?= htmlspecialchars($row['contact_id'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach; ?>       </select>
      </div>
    </div>
    <div class="container" id="personalInfo" name="personalInfo">
      <legend>
        <h2>Personal Information</h2>
      </legend>
      <div type="hidden" name="contact_id" id="contact_id"></div>

      <div class="form-field form-row2 nborder" style="--colspan: 1;">
        <label for="title">Title</label>
        <select name="title" id="title" size: 1; >
          <option value="">--Select--</option>
      <?php foreach ($titles as $row): ?>
        <option value="<?= htmlspecialchars($row['title_id'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($row['titleabr'], ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach; ?>
     </select>
      </div>

      <div class="form-field form-row2 nborder" style="--colspan: 1;">
        <label for="firstname">First Name</label>
        <input type="firstname" id="firstname">
      </div>
      <div class="form-field form-row2 nborder" style="--colspan: 1;">
        <label for="middlename">Middle Name</label>
        <input type="middlename" id="middlename" />
      </div>
      <div class="form-field form-row2 nborder" style="--colspan: 1;">
        <label for="lastname">Last Name</label>
        <input type="text" id="lastname" /></label>
      </div>
      <div class="form-field form-row3 nborder" style="--colspan: 5;">
        <label for="address1">Address</label>
        <input type="text" id="address1" name="address" autocomplete="on"/>
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
      <div class="form-field form-row5 nborder" style="--colspan: 1;">
        <label for="zipcode" name="zipcode">Zip Code</label>
        <input type="text" id="zipcode" name="zipcode" />
      </div>
      <div class="form-field form-row5 nborder" style="--colspan: 1;">
        <label for="dob">Date of Birth</label>
        <input type="date" id="dob" name="dob">
      </div>
      <div class="form-field form-row5 nborder" style="--colspan: 1;">
        <label for="gender">Gender</label>
        <select id="gender" name="gender" size="1">
          <option value="">Select</option>
          <option value="F">Female</option>
          <option value="M">Male</option>
        </select>
      </div>
      <div class="form-field form-row5 nborder" style="--colspan: 1;">
        <label for="marital">Marital Status</label>
        <select id="marital" name="marital" size="1">
          <option value="">Select</option>
          <option value="S">Single</option>
          <option value="M">Married</option>
        </select>
      </div>
      <div class="form-field form-row5 nborder" style="--colspan: 1;">
        <label for="anniv">Anniversary Date</label>
        <input type="date" id="anniv" name="anniv">
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
      <div class="form-field form-row1 nborder" style="--colspan: 1; height: 1rem;">
        <label for="phone1type">Type</label>
        <select name="phone1type" id="phone1type" size="1">
          <option value="">--Select--</option>
      <?php foreach ($phonetype as $row): ?>
        <option value="<?= htmlspecialchars($row['phone_type_id'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($row['phone_type_desc'], ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach; ?>
       </select>
      </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1; height: 1rem;">
        <label for="phone2" name="phone2">Secondary Phone</label>
        <input type="tel" id="phone2" name="phone2" >
        </div>
      <div class="form-field form-row1 nborder" style="--colspan: 1;">
        <label for="phone2type">Type</label>
        <select name="phone2type" id="phone2type">
          <option value="">--Select--</option>
      <?php foreach ($phonetype as $row): ?>
        <option value="<?= htmlspecialchars($row['phone_type_id'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($row['phone_type_desc'], ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach; ?>
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
      <?php foreach ($phonetype as $row): ?>
        <option value="<?= htmlspecialchars($row['phone_type_id'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($row['phone_type_desc'], ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach; ?>
   </select>
      </div>
      <div class="form-field form-row2 nborder" style="--colspan: 6;">
        <label for="email" name="email">E-mail Address</label>
        <input type="email" id="email" name="email" autocomplete="on">
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
  </main>
</body>

</html>

