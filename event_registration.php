<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Event Registration - Administrative Portal</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .registration-wrapper {
      width: 100%;
      max-width: 1000px;
      margin: 2rem auto;
    }
    .event-info-box {
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 20px;
      display: none;
    }
    .badge-fee {
      background: #dbeafe;
      color: #1e40af;
      padding: 4px 8px;
      border-radius: 4px;
      font-weight: bold;
      font-size: 0.95rem;
    }
    .contact-select-row {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    .contact-select-row select {
      flex: 1;
    }
    .btn-add-contact {
      background-color: #28089a;
      color: #ffffff;
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      white-space: nowrap;
      height: 38px;
      transition: background-color 0.15s ease;
    }
    .btn-add-contact:hover {
      background-color: #1e0573;
      color: #ffffff;
      text-decoration: none;
    }
    select, input[type="number"] {
      width: 100%;
      height: 38px;
      padding: 0 10px;
      border-radius: 6px;
      border: 1px solid #cbd5e1;
      color: #28089a;
      font-family: inherit;
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script>
    $(document).ready(function() {
        let eventsData = [];

        // Load lookups on init
        loadRegistrableEvents();
        loadContactsDropdown();

        function loadRegistrableEvents() {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_events' },
                dataType: 'json',
                success: function(data) {
                    if (data.success && data.programs_events) {
                        eventsData = data.programs_events;
                        let $select = $('#prg_evnt_id');
                        $select.find('option:not(:first)').remove();

                        // Filter to events requiring registration
                        let registrableEvents = eventsData.filter(e => e.requires_registration == 1);

                        if (registrableEvents.length === 0) {
                            $select.append('<option value="" disabled>No upcoming events requiring registration</option>');
                        } else {
                            $.each(registrableEvents, function(i, ev) {
                                let dispDate = ev.primary_start ? ` (${ev.primary_start.substring(0, 10)})` : '';
                                $select.append(`<option value="${ev.prg_evnt_id}">${ev.prg_evnt_name}${dispDate}</option>`);
                            });
                        }
                    }
                }
            });
        }

        function loadContactsDropdown() {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_contacts_list' },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        let $cSelect = $('#contact_id');
                        $cSelect.find('option:not(:first)').remove();
                        $.each(data.contacts, function(i, c) {
                            $cSelect.append(`<option value="${c.contact_id}">${c.full_name}</option>`);
                        });
                    }
                }
            });
        }

        // Handle Event Selection
        $('#prg_evnt_id').on('change', function() {
            let eventId = $(this).val();
            if (!eventId) {
                $('#eventDetailsBox').hide();
                $('#registrationFormFields').hide();
                return;
            }

            let ev = eventsData.find(e => e.prg_evnt_id == eventId);
            if (ev) {
                $('#infoLocation').text(ev.location || 'N/A');
                $('#infoSponsor').text(ev.ministry_name || 'N/A');
                
                let fee = parseFloat(ev.registration_fee || 0);
                $('#infoFee').text('$' + fee.toFixed(2));
                $('#amount_paid').val(fee.toFixed(2));

                if (fee > 0) {
                    $('#payment_status').val('Pending');
                } else {
                    $('#payment_status').val('Completed');
                }

                $('#eventDetailsBox').slideDown();
                $('#registrationFormFields').slideDown();
            }
        });

        // Submit Event Registration
        $('#registrationForm').on('submit', function(e) {
            e.preventDefault();

            let $btn = $('#submitBtn');
            $btn.prop('disabled', true).text('Processing...');

            $.ajax({
                url: 'prg_event_api.php?action=register_contact',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        alert(res.message || 'Attendee successfully registered!');
                        $('#registrationForm')[0].reset();
                        $('#eventDetailsBox').hide();
                        $('#registrationFormFields').hide();
                    } else {
                        alert('Registration Error: ' + res.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Server communication error: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save Registration');
                }
            });
        });
    });
  </script>
</head>
<body>
  <?php require_once("config/db.php"); ?>
  <?php include 'header.php'; ?> 

  <h1>Event Registration Portal</h1>

  <div class="registration-wrapper">
    <form id="registrationForm" name="registrationForm">
      
      <!-- STEP 1: EVENT SELECTION FIELDSET -->
      <fieldset class="form-grid-section-8">
        <legend>
          <h2>Event Selection</h2>
        </legend>
        <div class="field-group" style="--colspan: 8;">
          <label for="prg_evnt_id"><h3 style="color: #28089a; margin: 0 0 5px 0;">Select Event:</h3></label>
          <select id="prg_evnt_id" name="prg_evnt_id" required>
            <option value="">-- Choose an Event --</option>
          </select>
        </div>
      </fieldset>

      <!-- DYNAMIC EVENT OVERVIEW BOX -->
      <div id="eventDetailsBox" class="event-info-box">
        <h3 style="margin-top:0; color: #28089a; border-bottom: 1px solid #cbd5e1; padding-bottom: 5px;">Event Overview</h3>
        <div class="form-row" style="display: flex; gap: 20px;">
          <p style="flex:1; margin: 0;"><strong>Location:</strong> <span id="infoLocation">-</span></p>
          <p style="flex:1; margin: 0;"><strong>Sponsor:</strong> <span id="infoSponsor">-</span></p>
          <p style="flex:1; margin: 0;"><strong>Required Fee:</strong> <span id="infoFee" class="badge-fee">$0.00</span></p>
        </div>
      </div>

      <!-- STEP 2: ATTENDEE & PAYMENT DETAILS FIELDSET -->
      <div id="registrationFormFields" style="display: none;">
        <fieldset class="form-grid-section-8 fieldset-relative">
          <legend>
            <h2>Attendee & Payment Details</h2>
          </legend>

          <!-- Contact Dropdown + Add Contact Redirect -->
          <div class="field-group" style="--colspan: 8;">
            <label for="contact_id">Select Contact / Attendee:</label>
            <div class="contact-select-row">
              <select id="contact_id" name="contact_id" required>
                <option value="">-- Select Contact --</option>
              </select>
              <a href="contacts.php" class="btn-add-contact" title="Create a new contact if person isn't listed">
                + Add New Contact
              </a>
            </div>
          </div>

          <div class="field-group" style="--colspan: 4;">
            <label for="payment_method">Payment Method:</label>
            <select id="payment_method" name="payment_method" required>
              <option value="None">None / Free</option>
              <option value="Cash">Cash</option>
              <option value="Check">Check</option>
              <option value="Credit Card">Credit Card</option>
              <option value="Online">Online Transfer</option>
            </select>
          </div>

          <div class="field-group" style="--colspan: 4;">
            <label for="payment_status">Payment Status:</label>
            <select id="payment_status" name="payment_status" required>
              <option value="Completed">Completed</option>
              <option value="Pending">Pending</option>
              <option value="Waived">Waived</option>
            </select>
          </div>

          <div class="field-group" style="--colspan: 8;">
            <label for="amount_paid">Amount Paid ($):</label>
            <input type="number" step="0.01" id="amount_paid" name="amount_paid" value="0.00" required>
          </div>
        </fieldset>

        <!-- SUBMIT & RESET BUTTONS -->
        <fieldset class="form-grid-section-short-rght">
          <div class="field-group" style="--colspan: 3;">
            <button type="submit" id="submitBtn" class="btn-pulse">Save Registration</button>
          </div>
          <div class="field-group" style="--colspan: 3;">
            <button type="reset" id="resetBtn" class="btn-secondary">Reset</button>
          </div>
        </fieldset> 
      </div>

    </form>
  </div>
</body>
</html>