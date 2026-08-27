<?php
// self_register.php
// Display errors for development debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enable mysqli strict error handling so try/catch handles DB errors cleanly
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Session handling
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/auth.php';

// --- CONFIGURABLE DEFAULTS ---
$target_event_id = 11004; // Change event ID here
$default_reg_fee = 40.00;

// Fetch Event Name for target event ID
$prg_evnt_name = 'Group'; // Fallback default text

$stmtEvt = $db->prepare("SELECT prg_evnt_name FROM programs_events WHERE prg_evnt_id = ?");
if ($stmtEvt) {
  $stmtEvt->bind_param("i", $target_event_id);
  $stmtEvt->execute();
  $stmtEvt->bind_result($db_event_name);
  if ($stmtEvt->fetch() && !empty($db_event_name)) {
    $prg_evnt_name = $db_event_name;
  }
  $stmtEvt->close();
}

$errors = [];
$success_msg = '';

function cleanPhone($val)
{
  return preg_replace('/\D/', '', (string)$val);
}

// Form Submission Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $registrants = $_POST['registrants'] ?? [];

  if (empty($registrants) || !is_array($registrants)) {
    $errors[] = "Please add at least one person to register.";
  } else {
    $db->begin_transaction();
    try {
      $inserted_count = 0;

      foreach ($registrants as $index => $person) {
        $contact_id  = !empty($person['contact_id']) ? intval($person['contact_id']) : null;
        $first_name  = trim($person['first_name'] ?? '');
        $last_name   = trim($person['last_name'] ?? '');
        $phone_1     = cleanPhone($person['phone_1'] ?? '');
        $c_email     = filter_var(trim($person['c_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
        $pay_method  = trim($person['payment_method'] ?? 'Cash');

        // Dynamic fee calculation based on payment method
        if (strcasecmp($pay_method, 'None') === 0) {
          $amount_paid = 0.00;
        } else {
          $raw_fee = filter_var($person['amount_paid'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
          $amount_paid = ($raw_fee !== null && $raw_fee >= 0) ? $raw_fee : $default_reg_fee;
        }

        if (empty($first_name) || empty($last_name)) {
          $errors[] = "Person #" . ($index + 1) . ": First and Last Name are required.";
          continue;
        }

        // UPDATE existing contact record IF contact_id exists
        if ($contact_id) {
          $stmtUpdate = $db->prepare("
            UPDATE contacts 
            SET first_name = ?, last_name = ?, phone_1 = ?, c_email = ? 
            WHERE contact_id = ?
          ");
          if ($stmtUpdate) {
            $stmtUpdate->bind_param("ssssi", $first_name, $last_name, $phone_1, $c_email, $contact_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();
          }
        } else {
          // INSERT brand-new contact record
          $stmtInsert = $db->prepare("
            INSERT INTO contacts (first_name, last_name, phone_1, c_email) 
            VALUES (?, ?, ?, ?)
          ");
          if ($stmtInsert) {
            $stmtInsert->bind_param("ssss", $first_name, $last_name, $phone_1, $c_email);
            if ($stmtInsert->execute()) {
              $contact_id = $stmtInsert->insert_id;
            }
            $stmtInsert->close();
          }
        }

        // Insert into prg_evnt_registrations table with prg_evnt_id included
        if ($contact_id) {
          $stmtReg = $db->prepare("
            INSERT INTO prg_evnt_registrations (prg_evnt_id, contact_id, amount_paid, payment_method, payment_status, registered_at) 
            VALUES (?, ?, ?, ?, 'Pending', NOW())
          ");
          if ($stmtReg) {
            $stmtReg->bind_param("iids", $target_event_id, $contact_id, $amount_paid, $pay_method);
            $stmtReg->execute();
            $stmtReg->close();
          }
        }

        $inserted_count++;
      }

      if (empty($errors)) {
        $db->commit();
        $success_msg = "Successfully processed registration for <strong>{$inserted_count}</strong> registrant(s)!";
      } else {
        $db->rollback();
      }
    } catch (Exception $e) {
      $db->rollback();
      error_log("Registration Submission Error: " . $e->getMessage());
      $errors[] = "An unexpected error occurred during processing: " . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Group Registration - CMS</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <style>
    .reg-wrapper {
      max-width: 900px;
      margin: 0 auto;
    }

    .person-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 1.25rem;
      margin-bottom: 1.25rem;
      position: relative;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
      transition: border-color 0.2s;
    }

    .person-card:focus-within {
      border-color: #0d9488;
    }

    .person-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
      padding-bottom: 0.5rem;
      border-bottom: 1px solid #f1f5f9;
    }

    .person-card-title {
      font-size: 1.05rem;
      font-weight: 700;
      color: #0f172a;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .person-card-number {
      background: #e0f2fe;
      color: #0284c7;
      border-radius: 50%;
      width: 24px;
      height: 24px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
    }

    .btn-remove-person {
      background: transparent;
      border: none;
      color: #ef4444;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      padding: 4px 8px;
      border-radius: 4px;
    }

    .btn-remove-person:hover {
      background-color: #fef2f2;
    }

    /* Live Search Auto-complete Overlay */
    .search-box-container {
      position: relative;
      margin-bottom: 1rem;
    }

    .search-results-dropdown {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      z-index: 1000;
      max-height: 220px;
      overflow-y: auto;
      display: none;
    }

    .search-result-item {
      padding: 0.65rem 0.85rem;
      cursor: pointer;
      border-bottom: 1px solid #f1f5f9;
      font-size: 0.875rem;
    }

    .search-result-item:last-child {
      border-bottom: none;
    }

    .search-result-item:hover {
      background-color: #f0fdf4;
      color: #0d9488;
    }

    /* Input Grids */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
    }

    @media (min-width: 640px) {
      .form-grid-2 {
        grid-template-columns: repeat(2, 1fr);
      }

      .form-grid-4 {
        grid-template-columns: 2fr 2fr 1.5fr 1.5fr;
      }
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 0.3rem;
    }

    .form-group label {
      font-size: 0.85rem;
      font-weight: 600;
      color: #334155;
    }

    .form-control {
      width: 100%;
      padding: 0.625rem 0.75rem;
      font-size: 0.95rem;
      font-family: inherit;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      background-color: #f8fafc;
      box-sizing: border-box;
    }

    .form-control:focus {
      outline: none;
      border-color: #0d9488;
      background-color: #ffffff;
      box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
    }

    .input-icon-group {
      position: relative;
    }

    .input-icon-group span {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #64748b;
      font-weight: 600;
    }

    .input-icon-group input {
      padding-left: 24px;
    }

    /* Dynamic Payment Method Alert Box */
    .payment-instruction-box {
      margin-top: 0.85rem;
      padding: 0.75rem 1rem;
      border-radius: 6px;
      font-size: 0.85rem;
      line-height: 1.4;
    }

    .payment-instruction-box.givelify-box {
      background-color: #eff6ff;
      border: 1px solid #bfdbfe;
      color: #1e40af;
    }

    .payment-instruction-box.admin-box {
      background-color: #fffbeb;
      border: 1px solid #fde68a;
      color: #92400e;
    }

    .givelify-link {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      color: #2563eb;
      font-weight: 700;
      text-decoration: underline;
    }

    /* Checkout & Grand Total Section */
    .summary-bar {
      background: #0f172a;
      color: #ffffff;
      padding: 1.25rem;
      border-radius: 8px;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      margin-top: 1.5rem;
    }

    @media (min-width: 640px) {
      .summary-bar {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
      }
    }

    .total-display {
      font-size: 1.25rem;
      font-weight: 700;
    }

    .total-display span {
      color: #2dd4bf;
    }

    .alert {
      padding: 0.85rem 1rem;
      border-radius: 6px;
      margin-bottom: 1.25rem;
      font-size: 0.9rem;
    }

    .alert-danger {
      background-color: #fef2f2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    .alert-success {
      background-color: #f0fdf4;
      color: #166534;
      border: 1px solid #bbf7d0;
    }
  </style>
</head>

<body>

  <?php
  $hide_menu = true;
  include 'include/header.php';
  ?>

  <main class="dashboard-container">
    <div class="reg-wrapper">

      <!-- Alert Notices -->
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <strong>Please check the following items:</strong>
          <ul style="margin: 0.25rem 0 0 1.25rem; padding: 0;">
            <?php foreach ($errors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($success_msg): ?>
        <div class="alert alert-success">
          <?= $success_msg ?>
        </div>
      <?php endif; ?>

      <form id="group-reg-form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
        <h1><?= htmlspecialchars(trim($prg_evnt_name)) ?> Registration</h1>
        <div id="people-container"></div>

        <!-- Control Toolbar to Add More People -->
        <div style="margin-top: 0.5rem; text-align: center;">
          <button type="button" id="btn-add-person" class="btn btn-secondary" style="width: 100%; max-width: 300px; padding: 0.75rem;">
            + Add Another Person
          </button>
        </div>

        <!-- Checkout Summary Bar -->
        <div class="summary-bar">
          <div>
            <div style="font-size: 0.85rem; color: #94a3b8;">Total Registration Summary</div>
            <div class="total-display">Total Amount Due: <span id="grand-total">$0.00</span></div>
          </div>
          <div>
            <button type="submit" class="btn btn-primary" style="width: 100%; min-width: 200px; padding: 0.75rem 1.5rem; font-size: 1rem;">
              Submit Registration
            </button>
          </div>
        </div>

      </form>

    </div>
  </main>

  <?php include_once 'include/footer.php'; ?>

  <script>
    $(document).ready(function() {
      const DEFAULT_REG_FEE = 40.00;
      let personIndex = 0;

      // Render individual Registrant HTML block
      function createPersonCard(index) {
        const personNum = index + 1;
        return `
      <div class="person-card" data-index="${index}">
        <input type="hidden" name="registrants[${index}][contact_id]" class="contact-id-input" value="">
        
        <div class="person-card-header">
          <div class="person-card-title">
            <span class="person-card-number">${personNum}</span>
            <span>Registrant Info</span>
          </div>
          ${index > 0 ? `<button type="button" class="btn-remove-person">&times; Remove</button>` : ''}
        </div>

        <!-- Live Database Lookup Field -->
        <div class="search-box-container">
          <div class="form-group">
            <label>🔍 Lookup Existing Contact (Optional)</label>
            <input type="text" class="form-control contact-search-input" placeholder="Type name or phone number to search database..." autocomplete="off">
            <div class="search-results-dropdown"></div>
          </div>
        </div>

        <div class="form-grid form-grid-2">
          <div class="form-group">
            <label for="fname_${index}">First Name *</label>
            <input type="text" id="fname_${index}" name="registrants[${index}][first_name]" class="form-control fname-input" required>
          </div>
          <div class="form-group">
            <label for="lname_${index}">Last Name *</label>
            <input type="text" id="lname_${index}" name="registrants[${index}][last_name]" class="form-control lname-input" required>
          </div>
        </div>

        <div class="form-grid form-grid-4" style="margin-top: 0.85rem;">
          <div class="form-group">
            <label for="phone_${index}">Phone Number</label>
            <input type="tel" id="phone_${index}" name="registrants[${index}][phone_1]" class="form-control phone-input" placeholder="(555) 000-0000">
          </div>
          <div class="form-group">
            <label for="email_${index}">Email Address</label>
            <input type="email" id="email_${index}" name="registrants[${index}][c_email]" class="form-control email-input" placeholder="email@example.com">
          </div>
          <div class="form-group">
            <label for="fee_${index}">Amount Paid</label>
            <div class="input-icon-group">
              <span>$</span>
              <input type="number" id="fee_${index}" step="0.01" min="0" name="registrants[${index}][amount_paid]" class="form-control fee-input" value="${DEFAULT_REG_FEE.toFixed(2)}">
            </div>
          </div>
          <div class="form-group">
            <label for="pay_${index}">Payment Method</label>
            <select id="pay_${index}" name="registrants[${index}][payment_method]" class="form-control payment-method-select">
              <option value="Cash">Cash</option>
              <option value="Check">Check</option>
              <option value="Givelify">Givelify</option>
              <option value="None">None / Free</option>
            </select>
          </div>
        </div>

        <!-- Payment Notice Box -->
        <div class="payment-instruction-box admin-box">
          If paying by cash or check, please see Central Administration in order to complete your registration.
        </div>
      </div>
    `;
      }

      // Add initial person card
      $('#people-container').append(createPersonCard(personIndex));
      calculateGrandTotal();

      // Add card event
      $('#btn-add-person').on('click', function() {
        personIndex++;
        $('#people-container').append(createPersonCard(personIndex));
        reindexCards();
        calculateGrandTotal();
      });

      // Remove card event
      $(document).on('click', '.btn-remove-person', function() {
        $(this).closest('.person-card').remove();
        reindexCards();
        calculateGrandTotal();
      });

      // Dynamic Contact Search Lookup
      $(document).on('input', '.contact-search-input', function() {
        const $input = $(this);
        const $card = $input.closest('.person-card');
        const $dropdown = $card.find('.search-results-dropdown');
        const query = $input.val().trim();

        if (query.length < 2) {
          $dropdown.hide().empty();
          return;
        }

        $.getJSON('ajax_search_contacts.php', {
          q: query
        }, function(data) {
          $dropdown.empty();
          if (data && data.length > 0) {
            data.forEach(function(item) {
              const phoneFormatted = item.phone_1 ? formatPhoneString(item.phone_1) : '';
              // Display only full name in the dropdown list
              $dropdown.append(`
                <div class="search-result-item" 
                     data-id="${item.id}" 
                     data-fname="${escapeHtml(item.first_name)}" 
                     data-lname="${escapeHtml(item.last_name)}" 
                     data-phone="${escapeHtml(phoneFormatted)}" 
                     data-email="${escapeHtml(item.c_email)}">
                  <strong>${escapeHtml(item.first_name + ' ' + item.last_name)}</strong>
                </div>
              `);
            });
            $dropdown.show();
          } else {
            $dropdown.append('<div class="search-result-item" style="color:#64748b; cursor:default;">No contacts found</div>').show();
          }
        });
      });

      // Select contact from dropdown and populate form
      $(document).on('click', '.search-result-item[data-id]', function() {
        const $item = $(this);
        const $card = $item.closest('.person-card');

        $card.find('.contact-id-input').val($item.data('id'));
        $card.find('.fname-input').val($item.data('fname'));
        $card.find('.lname-input').val($item.data('lname'));
        $card.find('.phone-input').val($item.data('phone'));
        $card.find('.email-input').val($item.data('email'));

        $card.find('.contact-search-input').val($item.data('fname') + ' ' + $item.data('lname'));
        $card.find('.search-results-dropdown').hide().empty();
      });

      // Close search dropdown when clicking outside
      $(document).on('click', function(e) {
        if (!$(e.target).closest('.search-box-container').length) {
          $('.search-results-dropdown').hide();
        }
      });

      // Handle Payment Method Switching & Dynamic Fee Adjustment
      $(document).on('change', '.payment-method-select', function() {
        const method = $(this).val();
        const $card = $(this).closest('.person-card');
        const $feeInput = $card.find('.fee-input');
        const $noticeBox = $card.find('.payment-instruction-box');

        if (method === 'None') {
          $feeInput.val('0.00');
        } else {
          if (parseFloat($feeInput.val()) === 0) {
            $feeInput.val(DEFAULT_REG_FEE.toFixed(2));
          }
        }

        calculateGrandTotal();

        if (method === 'Givelify') {
          $noticeBox.attr('class', 'payment-instruction-box givelify-box').html(`
            You have selected Givelify. 
            <a href="https://www.givelify.com/" target="_blank" rel="noopener noreferrer" class="givelify-link">
              Click here to pay via Givelify &rarr;
            </a>
          `);
        } else if (method === 'None') {
          $noticeBox.attr('class', 'payment-instruction-box admin-box').html(`
            No registration fee is required for this entry.
          `);
        } else {
          $noticeBox.attr('class', 'payment-instruction-box admin-box').html(`
            If paying by cash or check, please see Central Administration in order to complete your registration.
          `);
        }
      });

      // Format phone inputs dynamically
      $(document).on('input', '.phone-input', function() {
        $(this).val(formatPhoneString($(this).val()));
      });

      function formatPhoneString(val) {
        let input = val.replace(/\D/g, '');
        if (input.length > 10) input = input.substring(0, 10);
        if (input.length > 6) return `(${input.substring(0, 3)}) ${input.substring(3, 6)}-${input.substring(6)}`;
        if (input.length > 3) return `(${input.substring(0, 3)}) ${input.substring(3)}`;
        if (input.length > 0) return `(${input}`;
        return '';
      }

      // Live calculation of Grand Total
      $(document).on('input', '.fee-input', function() {
        calculateGrandTotal();
      });

      function calculateGrandTotal() {
        let total = 0;
        $('.fee-input').each(function() {
          total += parseFloat($(this).val()) || 0;
        });
        $('#grand-total').text('$' + total.toFixed(2));
      }

      function reindexCards() {
        $('#people-container .person-card').each(function(i) {
          $(this).find('.person-card-number').text(i + 1);
        });
      }

      function escapeHtml(str) {
        return (str || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
      }
    });
  </script>

</body>

</html>