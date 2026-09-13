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
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/registration_fee.php';

// --- CONFIGURABLE DEFAULTS ---
$target_event_id = 11004; // Change event ID here
$default_reg_fee = 40.00;

// Fetch Event Name for target event ID
$prg_evnt_name = 'Group'; // Fallback default text

$stmtEvt = $db->prepare("SELECT prg_evnt_name FROM programs_events WHERE prg_evnt_id = ?");
if ($stmtEvt) {
  $stmtEvt->bind_param("i", $target_event_id);
  $db_event_name = null;
  $stmtEvt->execute();
  $stmtEvt->bind_result($db_event_name);
  if ($stmtEvt->fetch() && !empty($db_event_name)) {
    $prg_evnt_name = $db_event_name;
  }
  $stmtEvt->close();
}

$errors = [];
$success_msg = '';

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
            SET first_name = ?, last_name = ?
            WHERE contact_id = ?
          ");
          if ($stmtUpdate) {
            $stmtUpdate->bind_param("ssi", $first_name, $last_name, $contact_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();
          }
        } else {
          // INSERT brand-new contact record
          $stmtInsert = $db->prepare("
            INSERT INTO contacts (first_name, last_name)
            VALUES (?, ?)
          ");
          if ($stmtInsert) {
            $stmtInsert->bind_param("ss", $first_name, $last_name);
            if ($stmtInsert->execute()) {
              $contact_id = $stmtInsert->insert_id;
            }
            $stmtInsert->close();
          }
        }

        // Always set Payment Status to 'Pending' for everyone who registers
        $payment_status = 'Pending';
        $is_completed   = 0;

        if ($contact_id) {
          $stmtReg = $db->prepare("
            INSERT INTO prg_evnt_registrations (prg_evnt_id, contact_id, amount_paid, payment_method, payment_status, is_completed, registered_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
          ");
          if ($stmtReg) {
            $stmtReg->bind_param("iidssi", $target_event_id, $contact_id, $amount_paid, $pay_method, $payment_status, $is_completed);
            $stmtReg->execute();
            $stmtReg->close();

            logRegistrationFeeActual($db, $target_event_id, $contact_id, $amount_paid, $is_completed, $payment_status);
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
  <title><?= htmlspecialchars(trim($prg_evnt_name)) ?> Registration - CMS</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  
  <!-- Root-relative path prevents stylesheet resolution breaks on form submit -->
  <link rel="stylesheet" href="/css/style.css">
  
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <style>
    /* Scoped UI styling for self registration workflow */
    .person-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 1.25rem;
      margin-bottom: 1.25rem;
      position: relative;
    }

    .person-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
      padding-bottom: 0.5rem;
      border-bottom: 1px solid #e2e8f0;
    }

    .person-card-title {
      font-size: 1.05rem;
      font-weight: 700;
      color: #28089a;
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

    .search-result-item:hover {
      background-color: #f0fdf4;
      color: #0d9488;
    }

    .input-icon-group {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-icon-group span {
      position: absolute;
      left: 10px;
      color: #64748b;
      font-weight: 600;
    }

    .input-icon-group input {
      padding-left: 24px !important;
    }

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

    .summary-bar {
      background: #043b8f;
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
      color: #38bdf8;
    }

    .form-control-custom {
      width: 100%;
      padding: 8px;
      border-radius: 4px;
      border: 1px solid #cbd5e1;
      font-size: 0.95rem;
      box-sizing: border-box;
    }

    .form-control-custom[readonly] {
      background-color: #e2e8f0;
      color: #475569;
      cursor: not-allowed;
    }
  </style>
</head>

<body>

  <?php
  $hide_menu = true;
  include __DIR__ . '/include/header.php';
  ?>

  <main class="dashboard-container">
    <div class="card">
      <h3><?= htmlspecialchars(trim($prg_evnt_name)) ?> Registration</h3>

      <!-- Status Alerts -->
      <?php if (!empty($errors)): ?>
        <div class="text-danger" style="margin-bottom: 1rem;">
          <strong>Please check the following items:</strong>
          <ul style="margin: 0.25rem 0 0 1.25rem; padding: 0;">
            <?php foreach ($errors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($success_msg): ?>
        <div class="text-success" style="margin-bottom: 1rem; font-weight: 600;">
          <?= $success_msg ?>
        </div>
      <?php endif; ?>

      <form id="group-reg-form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" style="max-width: 100%; padding: 0; box-shadow: none; background: transparent;">
        <div id="people-container"></div>

        <!-- Add Registrant Action -->
        <div style="margin-top: 1rem; text-align: center;">
          <button type="button" id="btn-add-person" class="btn-secondary" style="width: 100%; max-width: 300px;">
            + Add Another Person
          </button>
        </div>

        <!-- Summary & Checkout Controls -->
        <div class="summary-bar">
          <div>
            <div style="font-size: 0.85rem; opacity: 0.9;">Total Registration Summary</div>
            <div class="total-display">Total Amount Due: <span id="grand-total">$0.00</span></div>
          </div>
          <div>
            <button type="submit" class="btn-accent" style="width: 100%; min-width: 200px; padding: 0.75rem 1.5rem; font-size: 1rem;">
              Submit Registration
            </button>
          </div>
        </div>

      </form>

    </div>
  </main>

  <?php include_once __DIR__ . '/include/footer.php'; ?>

  <script>
    $(document).ready(function() {
      const DEFAULT_REG_FEE = 40.00;
      let personIndex = 0;

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
          ${index > 0 ? `<button type="button" class="btn-danger btn-remove-person" style="padding: 4px 8px; font-size: 0.8rem;">&times; Remove</button>` : ''}
        </div>

        <div class="search-box-container">
          <div class="field-group">
            <label><strong>🔍 Lookup Existing Contact by Name (Optional)</strong></label>
            <input type="text" class="form-control-custom contact-search-input" placeholder="Type a first or last name to search..." autocomplete="off">
            <div class="search-results-dropdown"></div>
          </div>
        </div>

        <div class="form-grid-section-8" style="margin-bottom: 1rem;">
          <div class="field-group" style="grid-column: span 4;">
            <label for="fname_${index}"><strong>First Name *</strong></label>
            <input type="text" id="fname_${index}" name="registrants[${index}][first_name]" class="form-control-custom fname-input" required>
          </div>
          <div class="field-group" style="grid-column: span 4;">
            <label for="lname_${index}"><strong>Last Name *</strong></label>
            <input type="text" id="lname_${index}" name="registrants[${index}][last_name]" class="form-control-custom lname-input" required>
          </div>
        </div>

        <div class="form-grid-section-9">
          <div class="field-group" style="grid-column: span 3;">
            <label for="fee_${index}"><strong>Amount Paid</strong></label>
            <div class="input-icon-group">
              <span>$</span>
              <input type="number" id="fee_${index}" step="0.01" min="0" name="registrants[${index}][amount_paid]" class="form-control-custom fee-input" value="${DEFAULT_REG_FEE.toFixed(2)}">
            </div>
          </div>
          <div class="field-group" style="grid-column: span 3;">
            <label for="pay_${index}"><strong>Payment Method</strong></label>
            <select id="pay_${index}" name="registrants[${index}][payment_method]" class="form-control-custom payment-method-select">
              <option value="Cash">Cash</option>
              <option value="Check">Check</option>
              <option value="Givelify">Givelify</option>
              <option value="None">None / Free</option>
            </select>
          </div>
          <div class="field-group" style="grid-column: span 3;">
            <label for="pay_status_${index}"><strong>Payment Status</strong></label>
            <input type="text" id="pay_status_${index}" name="registrants[${index}][payment_status]" class="form-control-custom" value="Pending" readonly>
          </div>
        </div>

        <div class="payment-instruction-box admin-box">
          If paying by cash or check, please see Central Administration in order to complete your registration.
        </div>
      </div>
    `;
      }

      // Initialize first form card
      $('#people-container').append(createPersonCard(personIndex));
      calculateGrandTotal();

      $('#btn-add-person').on('click', function() {
        personIndex++;
        $('#people-container').append(createPersonCard(personIndex));
        reindexCards();
        calculateGrandTotal();
      });

      $(document).on('click', '.btn-remove-person', function() {
        $(this).closest('.person-card').remove();
        reindexCards();
        calculateGrandTotal();
      });

      $(document).on('input', '.contact-search-input', function() {
        const $input = $(this);
        const $card = $input.closest('.person-card');
        const $dropdown = $card.find('.search-results-dropdown');
        const query = $input.val().trim();

        if (query.length < 2) {
          $dropdown.hide().empty();
          return;
        }

        $.getJSON('ajax_search_contacts.php', { q: query }, function(data) {
          $dropdown.empty();
          if (data && data.length > 0) {
            data.forEach(function(item) {
              $dropdown.append(`
                <div class="search-result-item"
                     data-id="${item.id}"
                     data-fname="${escapeHtml(item.first_name)}"
                     data-lname="${escapeHtml(item.last_name)}">
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

      $(document).on('click', '.search-result-item[data-id]', function() {
        const $item = $(this);
        const $card = $item.closest('.person-card');

        $card.find('.contact-id-input').val($item.data('id'));
        $card.find('.fname-input').val($item.data('fname'));
        $card.find('.lname-input').val($item.data('lname'));

        $card.find('.contact-search-input').val($item.data('fname') + ' ' + $item.data('lname'));
        $card.find('.search-results-dropdown').hide().empty();
      });

      $(document).on('click', function(e) {
        if (!$(e.target).closest('.search-box-container').length) {
          $('.search-results-dropdown').hide();
        }
      });

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
            <a href="https://www.givelify.com/" target="_blank" rel="noopener noreferrer" style="color: #2563eb; font-weight: 700;">
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