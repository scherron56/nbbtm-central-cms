<?php
// Enforce persistent cookie scope before session start
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400, // 24 Hours
        'path'     => '/',   // Root path ensures session spans all sub-folders
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Require auth helper
require_once __DIR__ . '/include/auth.php';
$canEdit = canEdit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Beginnings Baptist Tabernacle Ministries</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .fieldset-relative {
      position: relative;
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

    .top-right-member label {
      font-weight: 700;
    }

    .alert-box {
      padding: 12px 16px;
      margin-bottom: 20px;
      border-radius: 6px;
      font-weight: 500;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      display: none;
    }
    .alert-success {
      background-color: #d1fae5;
      border: 1px solid #6ee7b7;
      color: #065f46;
    }
    .alert-error {
      background-color: #fee2e2;
      border: 1px solid #fca5a5;
      color: #991b1b;
    }
    .read-only-banner {
      background-color: #f1f5f9;
      border-left: 4px solid #0ea5e9;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
      color: #334155;
    }
    .doc-management-box {
      background: #f8fafc;
      border: 1px dashed #cbd5e1;
      border-radius: 6px;
      padding: 14px;
      margin-top: 10px;
    }
    .file-upload-row {
      display: flex;
      gap: 10px;
      align-items: center;
      margin-bottom: 8px;
    }
    .attachment-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
    }
    .attachment-table th, .attachment-table td {
      border: 1px solid #e2e8f0;
      padding: 6px 10px;
      text-align: left;
      font-size: 0.9rem;
    }
    .attachment-table th { background: #f1f5f9; }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <script>
$(document).ready(function() {

  const CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;
  const DEFAULT_STATE = 'MN';
  let globalMinistryList = [];
  let globalRoleList = [];
  let contactCategoriesCache = [];

  function showStatusMessage(message, type = 'success') {
    let $box = $('#status-message');
    let htmlContent = '';

    if (Array.isArray(message)) {
      htmlContent = '<ul style="margin: 0; padding-left: 20px;">' + message.map(msg => `<li>${msg}</li>`).join('') + '</ul>';
    } else if (typeof message === 'object') {
      htmlContent = '<ul style="margin: 0; padding-left: 20px;">' + Object.values(message).map(msg => `<li>${msg}</li>`).join('') + '</ul>';
    } else {
      htmlContent = message;
    }

    $box.removeClass('alert-success alert-error')
        .addClass(type === 'success' ? 'alert-success' : 'alert-error')
        .html(htmlContent)
        .stop(true, true)
        .fadeIn(200);

    $('html, body').animate({ scrollTop: $box.offset().top - 20 }, 200);

    if (type === 'success') {
      setTimeout(function() { $box.fadeOut(500); }, 5000);
    }
  }

  function clearStatusMessage() {
    $('#status-message').fadeOut(200).empty();
  }

  $('#is_member, #is_baptized, #is_active, #is_head, #is_child').prop('checked', false);

  function formatPhoneNumber(value) {
    if (!value) return '';
    let digits = String(value).replace(/\D/g, '');
    if (digits.length === 10) {
      return `(${digits.slice(0,3)}) ${digits.slice(3,6)}-${digits.slice(6)}`;
    }
    if (digits.length > 6) {
      return `(${digits.slice(0,3)}) ${digits.slice(3,6)}-${digits.slice(6,10)}`;
    }
    if (digits.length > 3) {
      return `(${digits.slice(0,3)}) ${digits.slice(3)}`;
    }
    if (digits.length > 0) {
      return `(${digits}`;
    }
    return '';
  }

  $(document).on('input', 'input[type="tel"]', function() {
    let cursorPosition = this.selectionStart;
    let originalLength = this.value.length;
    this.value = formatPhoneNumber(this.value);
    let newLength = this.value.length;
    cursorPosition += (newLength - originalLength);
    this.setSelectionRange(cursorPosition, cursorPosition);
  });

  // --- DOCUMENT CATEGORIES & MULTI-ROW UPLOAD LOGIC ---
  function loadContactCategories() {
    $.ajax({
      url: 'category_api.php',
      type: 'GET',
      data: { action: 'get_categories', entity_type: 'contact', only_active: 1 },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          contactCategoriesCache = res.categories || [];
          populateCategoryDropdowns();
        }
      }
    });
  }

  function populateCategoryDropdowns() {
    $('.attach-cat-select').each(function() {
      let $sel = $(this);
      let currentVal = $sel.val();
      $sel.find('option:not(:first)').remove();
      $.each(contactCategoriesCache, function(i, c) {
        $sel.append(`<option value="${c.doc_category_id}">${escapeHtml(c.category_name)}</option>`);
      });
      if (currentVal) $sel.val(currentVal);
    });
  }

  $('#btnAddFileRow').on('click', function() {
    let newRow = `
      <div class="file-upload-row">
        <select name="attach_cat_ids[]" class="form-control attach-cat-select" style="flex: 1;" required>
          <option value="">-- Select Category --</option>
        </select>
        <input type="file" name="attach_files[]" accept=".pdf,.doc,.docx,.xlsx,.png,.jpg" class="form-control" style="flex: 2; background:#fff;" required>
        <button type="button" class="btn btn-danger btnRemoveFileRow" style="padding: 6px 10px;">&times;</button>
      </div>
    `;
    $('#uploadRowsContainer').append(newRow);
    populateCategoryDropdowns();
    toggleFileRowButtons();
  });

  $(document).on('click', '.btnRemoveFileRow', function() {
    $(this).closest('.file-upload-row').remove();
    toggleFileRowButtons();
  });

  function toggleFileRowButtons() {
    let rows = $('.file-upload-row');
    rows.find('.btnRemoveFileRow').prop('disabled', rows.length <= 1);
  }

  function renderAttachmentsList(attachments) {
    let $tbody = $('#existingAttachmentsTable tbody').empty();
    if (!attachments || attachments.length === 0) {
      $('#noAttachmentsMsg').show();
      $('#existingAttachmentsTable').hide();
    } else {
      $('#noAttachmentsMsg').hide();
      $('#existingAttachmentsTable').show();
      $.each(attachments, function(i, att) {
        let sizeKb = att.document_size ? Math.round(att.document_size / 1024) + ' KB' : '-';
        let downloadUrl = `download_document.php?attachment_id=${att.attachment_id}`;
        
        $tbody.append(`
          <tr>
            <td><span class="status-badge badge-secondary">${escapeHtml(att.category_name || 'General')}</span></td>
            <td><a href="${downloadUrl}" target="_blank" style="font-weight: 600;">📄 ${escapeHtml(att.document_name)}</a></td>
            <td>${sizeKb}</td>
            <td style="text-align:right;">
              <a href="${downloadUrl}" class="btn btn-sm btn-primary" target="_blank">Download</a>
              <button type="button" class="btn btn-sm btn-danger btnDeleteAttachment" data-id="${att.attachment_id}" ${!CAN_EDIT ? 'disabled' : ''}>Delete</button>
            </td>
          </tr>
        `);
      });
    }
  }

  $(document).on('click', '.btnDeleteAttachment', function() {
    if (!confirm('Are you sure you want to remove this document?')) return;
    let attId = $(this).data('id');

    $.ajax({
      url: 'saveContact.php',
      type: 'POST',
      data: { action: 'delete_attachment', attachment_id: attId },
      dataType: 'json',
      success: function(res) {
        if (res.status === 'success') {
          showStatusMessage('Document deleted.', 'success');
          $('#contactID').trigger('change');
        } else {
          showStatusMessage(res.message || 'Failed to delete attachment.', 'error');
        }
      }
    });
  });

  function resetUploadRows() {
    $('#uploadRowsContainer').html(`
      <div class="file-upload-row">
        <select name="attach_cat_ids[]" class="form-control attach-cat-select" style="flex: 1;">
          <option value="">-- Select Category --</option>
        </select>
        <input type="file" name="attach_files[]" accept=".pdf,.doc,.docx,.xlsx,.png,.jpg" class="form-control" style="flex: 2; background:#fff;">
        <button type="button" class="btn btn-danger btnRemoveFileRow" style="padding: 6px 10px;" disabled>&times;</button>
      </div>
    `);
    populateCategoryDropdowns();
  }

  // --- MULTI-STATE & CITY/ZIP POPULATION LOGIC ---
  function populateCities(stateCode, selectedCity = '', callback = null) {
    let $cityDropdown = $('#city');
    $cityDropdown.empty().append($('<option>', { value: '', text: '--Loading Cities...--' }));

    if (!stateCode) {
      $cityDropdown.empty().append($('<option>', { value: '', text: '--Select City--' }));
      return;
    }

    $.getJSON('lookupZip.php', { action: 'getCities', state: stateCode }, function(response) {
      $cityDropdown.empty().append($('<option>', { value: '', text: '--Select City--' }));
      if (response.status === 'success' && response.cities) {
        $.each(response.cities, function(idx, item) {
          let cityName = item.city;
          $cityDropdown.append($('<option>', { value: cityName, text: cityName }));
        });
        if (selectedCity) {
          $cityDropdown.val(selectedCity);
        }
      }
      if (typeof callback === 'function') callback();
    });
  }

  $('#state').val(DEFAULT_STATE);
  populateCities(DEFAULT_STATE);

  $('#state').on('change', function() {
    let stateCode = $(this).val();
    $('#zipcode').val('');
    populateCities(stateCode);
  });

  $('#city').on('change', function() {
    let city = $(this).val();
    let state = $('#state').val();

    if (city && state) {
      $.getJSON('lookupZip.php', { action: 'getZip', city: city, state: state }, function(res) {
        if (res.status === 'success' && res.found) {
          $('#zipcode').val(res.zipcode);
        }
      });
    }
  });

  $('#zipcode').on('input', function() {
    let zip = $(this).val().trim();
    if (zip.length === 5 && /^\d{5}$/.test(zip)) {
      $.getJSON('lookupZip.php', { action: 'getDetailsByZip', zip: zip }, function(res) {
        if (res.status === 'success' && res.found) {
          let stateCode = res.data.state_code;
          let cityName = res.data.city;

          $('#state').val(stateCode);
          populateCities(stateCode, cityName);
        }
      });
    }
  });

  updateFormLists();
  loadContactCategories();

  function toggleMemberSections(shouldShow) {
    if (shouldShow) {
      $('#membership-section, #ministry-section').show();
    } else {
      $('#membership-section, #ministry-section').hide();
    }
  }

  function buildMinistryCheckboxes(ministryList, roleList, savedMinistries = []) {
    $('#checkbox-container').empty(); 

    if (ministryList && ministryList.length > 0) {
      let groupedMinistries = {};

      $.each(ministryList, function(index, item) {
        let typeId = item.min_comm_type_id || 0;
        if (!groupedMinistries[typeId]) {
          groupedMinistries[typeId] = {
            typeDesc: item.min_grp_type_desc || 'General Associations',
            items: []
          };
        }
        groupedMinistries[typeId].items.push(item);
      });

      $.each(groupedMinistries, function(typeId, groupData) {
        let groupHeader = $('<h3>').css({
          'grid-column': '1 / -1',
          'color': '#28089a',
          'margin-top': '18px',
          'margin-bottom': '6px',
          'border-bottom': '2px solid #cbd5e1',
          'padding-bottom': '4px'
        }).text(groupData.typeDesc);

        $('#checkbox-container').append(groupHeader);

        $.each(groupData.items, function(index, item) {
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
          const rowDiv = $('<div>').addClass('ministry-card').css('margin-bottom', '10px');
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
      });
    }

    if (!CAN_EDIT) {
      $('#checkbox-container input, #checkbox-container select').prop('disabled', true);
    }
  }

  $('#is_member').change(function() {
    toggleMemberSections($(this).is(':checked'));
  });

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
        showStatusMessage("Error loading contact list.", 'error');
      }
    });
  }

  function updateFormLists(callback) {
    let membersOnly = $('input[name="contact_filter"]:checked').val();

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
        globalMinistryList = data.ministryList || [];
        globalRoleList = data.roleList || [];

        $('#contactID').empty().append($('<option>', { value: '', text: '--Select--' }));
        if (data.contacts && Array.isArray(data.contacts)) {
          $.each(data.contacts, function(index, item) {
            $('#contactID').append($('<option>', { value: item.contact_id, text: item.fullname }));
          });
        }

        $('#family_id').empty().append($('<option>', { value: '', text: '--Select--' }));
        if (data.heads && Array.isArray(data.heads)) {
          $.each(data.heads, function(index, item) {
            $('#family_id').append($('<option>', { value: item.contact_id, text: item.fullname }));
          });
        }

        $('#title_id').empty().append($('<option>', { value: '', text: '--Select--' }));
        if (data.titles && Array.isArray(data.titles)) {
          $.each(data.titles, function(index, item) {
            $('#title_id').append($('<option>', { value: item.title_id, text: item.titleabr }));
          });
        }

        $('#marital_status').empty().append($('<option>', { value: '', text: '--Select--' }));
        if (data.marital && Array.isArray(data.marital)) {
          $.each(data.marital, function(index, item) {
            let mId = item.marital_id || item.maritial_id || item.marital_status_id;
            $('#marital_status').append($('<option>', { value: mId, text: item.marital_status }));
          });
        }

        $('#phone_1_type, #phone_2_type, #phone_3_type').empty().append($('<option>', { value: '', text: '--Select--' }));
        if (data.phonetype && Array.isArray(data.phonetype)) {
          $.each(data.phonetype, function(index, item) {
            let opt = $('<option>', { value: item.phone_type_id, text: item.phone_type_desc });
            $('#phone_1_type').append(opt.clone());
            $('#phone_2_type').append(opt.clone());
            $('#phone_3_type').append(opt.clone());
          });
        }

        if (selContact) $('#contactID').val(selContact);
        if (selFamily) $('#family_id').val(selFamily);
        if (selTitle) $('#title_id').val(selTitle);
        if (selMarital) $('#marital_status').val(selMarital);
        if (selPhone1) $('#phone_1_type').val(selPhone1);
        if (selPhone2) $('#phone_2_type').val(selPhone2);
        if (selPhone3) $('#phone_3_type').val(selPhone3);

        if (!$('#contact_id').val() && $('#checkbox-container').is(':empty')) {
          buildMinistryCheckboxes(globalMinistryList, globalRoleList, []);
        }

        if (typeof callback === 'function') {
          callback();
        }
      },
      error: function(xhr, status, error) {
        showStatusMessage("Error loading dropdown data.", 'error');
      }
    });
  }

  $(document).on('change', 'input[name="contact_filter"]', function() {
    updateContactList();
  });

  $('#addNewContact, #resetBtn').click(function(e) {
    clearStatusMessage();
    if ($('#contact-form').length) {
      $('#contact-form')[0].reset();
      $('#contact_id').val('');
      $('#contactID').val('');
      $('#date_of_death').val('');
      $('#is_member, #is_baptized, #is_active, #is_head, #is_child').prop('checked', false);
      
      $('#state').val(DEFAULT_STATE);
      $('#city').val('');
      $('#zipcode').val('');
      populateCities(DEFAULT_STATE);

      buildMinistryCheckboxes(globalMinistryList, globalRoleList, []);
      toggleMemberSections(false);
      renderAttachmentsList([]);
      resetUploadRows();
      $('#submitBtn').text('Save');
    }
  });

  $('#contactID').change(function() {
    clearStatusMessage();
    let contactid = $(this).val();

    if (contactid === "") {
      if ($('#contact-form').length) {
        $('#contact-form')[0].reset();
        $('#contact_id').val('');
        $('#date_of_death').val('');
        $('#is_member, #is_baptized, #is_active, #is_head, #is_child').prop('checked', false);
        $('#state').val(DEFAULT_STATE);
        $('#city').val('');
        $('#zipcode').val('');
        populateCities(DEFAULT_STATE);
        buildMinistryCheckboxes(globalMinistryList, globalRoleList, []);
        toggleMemberSections(false);
        renderAttachmentsList([]);
        resetUploadRows();
      }
      return;
    }

    $.ajax({
      url: 'getContact.php',
      type: 'POST',
      data: { contactid: contactid },
      dataType: 'json',
      success: function(data) {
        if (data.error) {
          showStatusMessage(data.error, 'error');
          return;
        }

        let contact = data.contact || {};

        $('#contact_id').val(contactid || '');
        $('#title_id').val(contact.title_id || '');
        $('#first_name').val(contact.first_name || '');
        $('#middle_name').val(contact.middle_name || '');
        $('#last_name').val(contact.last_name || '');
        $('#address_1').val(contact.address_1 || '');

        let contactState = contact.state || DEFAULT_STATE;
        $('#state').val(contactState);
        populateCities(contactState, contact.city || '');

        $('#zipcode').val(contact.zipcode || '');
        $('#date_of_birth').val(contact.date_of_birth || '');
        $('#date_of_death').val(contact.date_of_death || '');
        $('#gender').val(contact.gender || '');
        $('#marital_status').val(contact.marital_status || '');
        $('#anniv_date').val(contact.anniv_date || '');
        $('#phone_1').val(formatPhoneNumber(contact.phone_1 || ''));
        $('#phone_2').val(formatPhoneNumber(contact.phone_2 || ''));
        $('#emergency_contact').val(contact.emergency_contact || '');
        $('#phone_3').val(formatPhoneNumber(contact.phone_3 || ''));
        $('#phone_1_type').val(contact.phone_1_type || '');
        $('#phone_2_type').val(contact.phone_2_type || '');
        $('#phone_3_type').val(contact.phone_3_type || '');
        $('#c_email').val(contact.c_email || '');
        $('#join_date').val(contact.join_date || '');
        $('#baptized_date').val(contact.baptized_date || '');

        let isHead = String(contact.is_head) === "1" || contact.is_head === true;
        $('#is_head').prop('checked', isHead);

        let isChild = String(contact.is_child) === "1" || contact.is_child === true;
        $('#is_child').prop('checked', isChild);

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
        buildMinistryCheckboxes(data.ministryList, data.roleList, data.ministries);
        
        renderAttachmentsList(data.attachments || []);
        resetUploadRows();

        if (CAN_EDIT) {
          $('#submitBtn').text('Update Contact');
          $('#resetBtn').show();
        }
      },
      error: function(xhr, status, error) {
        showStatusMessage("Error fetching contact details.", 'error');
      }
    });
  });

  // Save handler with FormData for file uploads
  $('#contact-form').on('submit', function(e) {
    e.preventDefault();
    if (!CAN_EDIT) return;
    clearStatusMessage();

    let formData = new FormData(this);
    let submitBtn = $('#submitBtn');
    submitBtn.prop('disabled', true).text('Processing...');

    $.ajax({
      url: 'saveContact.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function(response) {
        if (response.status === 'success') {
          showStatusMessage(response.message, 'success');
          let savedId = response.id;
          
          updateFormLists(function() {
            if (savedId) {
              $('#contactID').val(savedId).trigger('change');
            }
          });
        } else {
          showStatusMessage(response.message || "Failed to save contact.", 'error');
        }
      },
      error: function(xhr, status, error) {
        let response = xhr.responseJSON || {};
        if (response.errors) {
          showStatusMessage(response.errors, 'error');
        } else {
          showStatusMessage("An error occurred while saving. Please try again.", 'error');
        }
      },
      complete: function() {
        submitBtn.prop('disabled', false).text('Save');
      }
    });
  });

  $('#deleteBtn').click(function(e) {
    e.preventDefault();
    if (!CAN_EDIT) return;
    clearStatusMessage();
    let contactId = $('#contact_id').val();

    if (!contactId) {
      showStatusMessage("Please select a contact to delete.", 'error');
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
            showStatusMessage(response.message, 'success');
            $('#addNewContact').click();
            updateFormLists();
          } else {
            showStatusMessage(response.message || "Failed to delete contact.", 'error');
          }
        },
        error: function(xhr, status, error) {
          showStatusMessage("An error occurred while attempting to delete the contact.", 'error');
        }
      });
    }
  });

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }

  toggleMemberSections($('#is_member').is(':checked'));
});
  </script>
</head>

<body>
  <?php require_once("config/db.php") ?>
  <?php include 'include/header.php'?>

  <h1>Member/Contact Information</h1>

  <div id="status-message" class="alert-box"></div>

  <fieldset id="contact-select" class="form-grid-section-short" style="width: 80%;">
    <div class="field-group" style="--colspan: 2;">
      <label><h3 style="color: blue; margin-bottom: 5px;">View Option</h3></label>
      <div style="display: flex; gap: 10px; align-items: center; margin-top: 4px; flex-wrap: wrap;">
        <label for="filter_all" style="font-weight: normal; cursor: pointer; font-size: 0.85rem; white-space: nowrap;">
          <input type="radio" id="filter_all" name="contact_filter" value="0" checked>
          All Contacts
        </label>
        <label for="filter_members" style="font-weight: normal; cursor: pointer; font-size: 0.85rem; white-space: nowrap;">
          <input type="radio" id="filter_members" name="contact_filter" value="1">
          Members
        </label>
        <label for="filter_non_members" style="font-weight: normal; cursor: pointer; font-size: 0.85rem; white-space: nowrap;">
          <input type="radio" id="filter_non_members" name="contact_filter" value="2">
          Non-Members
        </label>
      </div>
    </div>

    <div class="field-group" style="--colspan: 2;">
      <label for="contactID"><h3 style="color: blue;">Select Member/Contact</h3></label>
      <select name="contactID" id="contactID">
        <option value="">--Select--</option>
      </select>
    </div>

    <?php if ($canEdit): ?>
      <div class="field-group" style="--colspan: 2; display: flex; justify-content: center; align-items: center;">
        <button type="button" id="addNewContact" class="btn-pulse nbtn">Add Contact</button>
      </div>
    <?php endif; ?>
  </fieldset>

  <?php if ($canEdit): ?>
    <form id="contact-form" name="contact-form" enctype="multipart/form-data">
      <input type="hidden" name="entity_type" value="contact">

      <!-- PERSONAL INFORMATION FIELDSET -->
      <fieldset class="form-grid-section-8 fieldset-relative">
        <legend>
          <h2>Personal Information</h2>
        </legend>

        <div class="top-right-member">
          <label for="is_member">Member</label>
          <input type="hidden" name="is_member" value="0">
          <input type="checkbox" id="is_member" name="is_member" value="1">
        </div>

        <input type="hidden" id="contact_id" name="contact_id">

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
        <div class="field-group" style="--colspan: 2; --rowspan: 1;">
          <label for="date_of_death">Deceased Date</label>
          <input type="date" id="date_of_death" name="date_of_death">
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
        <div class="field-group" style="--colspan: 2; --rowspan: 3;">
          <label for="is_child">Child</label>
          <input type="hidden" name="is_child" value="0">
          <input type="checkbox" id="is_child" name="is_child" value="1">
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

        <div class="field-group" style="--colspan: 3;">
          <label for="state">State</label>
          <select id="state" name="state">
            <optgroup label="Default Region">
              <option value="MN" selected>Minnesota (MN)</option>
              <option value="WI">Wisconsin (WI)</option>
              <option value="IA">Iowa (IA)</option>
              <option value="ND">North Dakota (ND)</option>
              <option value="SD">South Dakota (SD)</option>
            </optgroup>
            <optgroup label="All States">
              <option value="AL">Alabama (AL)</option><option value="AK">Alaska (AK)</option>
              <option value="AZ">Arizona (AZ)</option><option value="AR">Arkansas (AR)</option>
              <option value="CA">California (CA)</option><option value="CO">Colorado (CO)</option>
              <option value="CT">Connecticut (CT)</option><option value="DE">Delaware (DE)</option>
              <option value="DC">District of Columbia (DC)</option><option value="FL">Florida (FL)</option>
              <option value="GA">Georgia (GA)</option><option value="HI">Hawaii (HI)</option>
              <option value="ID">Idaho (ID)</option><option value="IL">Illinois (IL)</option>
              <option value="IN">Indiana (IN)</option><option value="KS">Kansas (KS)</option>
              <option value="KY">Kentucky (KY)</option><option value="LA">Louisiana (LA)</option>
              <option value="ME">Maine (ME)</option><option value="MD">Maryland (MD)</option>
              <option value="MA">Massachusetts (MA)</option><option value="MI">Michigan (MI)</option>
              <option value="MS">Mississippi (MS)</option><option value="MO">Missouri (MO)</option>
              <option value="MT">Montana (MT)</option><option value="NE">Nebraska (NE)</option>
              <option value="NV">Nevada (NV)</option><option value="NH">New Hampshire (NH)</option>
              <option value="NJ">New Jersey (NJ)</option><option value="NM">New Mexico (NM)</option>
              <option value="NY">New York (NY)</option><option value="NC">North Carolina (NC)</option>
              <option value="OH">Ohio (OH)</option><option value="OK">Oklahoma (OK)</option>
              <option value="OR">Oregon (OR)</option><option value="PA">Pennsylvania (PA)</option>
              <option value="RI">Rhode Island (RI)</option><option value="SC">South Carolina (SC)</option>
              <option value="TN">Tennessee (TN)</option><option value="TX">Texas (TX)</option>
              <option value="UT">Utah (UT)</option><option value="VT">Vermont (VT)</option>
              <option value="VA">Virginia (VA)</option><option value="WA">Washington (WA)</option>
              <option value="WV">West Virginia (WV)</option><option value="WY">Wyoming (WY)</option>
            </optgroup>
          </select>
        </div>

        <div class="field-group" style="--colspan: 3; --rowspan: 1;">
          <label for="city">City</label>
          <select id="city" name="city">
            <option value="">--Select City--</option>
          </select>
        </div>

        <div class="field-group" style="--colspan: 3;">
          <label for="zipcode">Zip Code</label>
          <input type="text" id="zipcode" name="zipcode" maxlength="5" autocomplete="off" placeholder="Enter ZIP" />
        </div>

        <div class="field-group" style="--colspan: 2; --rowspan: 1;">
          <label for="phone_1">Primary Phone</label>
          <input type="tel" id="phone_1" name="phone_1" placeholder="(123) 456-7890" maxlength="14" inputmode="tel" autocomplete="tel">
        </div>
        <div class="field-group" style="--colspan: 2;">
          <label for="phone_1_type">Phone Type</label>
          <select name="phone_1_type" id="phone_1_type">
            <option value="">--Select--</option>
          </select>
        </div>
        <div class="field-group" style="--colspan: 2; --rowspan: 1;">
          <label for="phone_2">Secondary Phone</label>
          <input type="tel" id="phone_2" name="phone_2" placeholder="(123) 456-7890" maxlength="14" inputmode="tel" autocomplete="tel">
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
          <input type="tel" id="phone_3" name="phone_3" placeholder="(123) 456-7890" maxlength="14" inputmode="tel" autocomplete="tel">
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

      <!-- MEMBERSHIP INFORMATION FIELDSET (Includes Document Uploads) -->
      <fieldset id="membership-section" class="form-grid-section-70">
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

        <!-- CONTACT DOCUMENTS SUB-SECTION -->
        <div class="field-group" style="grid-column: 1 / -1;">
          <label><strong>Important Contact Documents:</strong></label>
          <div class="doc-management-box">
            <label style="font-size: 0.85rem; color: #475569; font-weight: bold; margin-bottom: 5px; display: block;">Uploaded Documents:</label>
            <div id="noAttachmentsMsg" style="color: #64748b; font-style: italic; margin-bottom: 8px;">No documents attached to this contact record.</div>

            <table class="attachment-table" id="existingAttachmentsTable" style="display: none; margin-bottom: 15px;">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>File Name</th>
                  <th>Size</th>
                  <th style="text-align:right;">Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>

            <label style="font-size: 0.85rem; color: #475569; font-weight: bold; margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center;">
              Upload New Documents:
              <button type="button" id="btnAddFileRow" class="btn btn-sm btn-success" style="padding: 2px 8px; font-size: 0.8rem;">+ Add Document</button>
            </label>
            <div id="uploadRowsContainer">
              <div class="file-upload-row">
                <select name="attach_cat_ids[]" class="form-control attach-cat-select" style="flex: 1;">
                  <option value="">-- Select Category --</option>
                </select>
                <input type="file" name="attach_files[]" accept=".pdf,.doc,.docx,.xlsx,.png,.jpg" class="form-control" style="flex: 2; background:#fff;">
                <button type="button" class="btn btn-danger btnRemoveFileRow" style="padding: 6px 10px;" disabled>&times;</button>
              </div>
            </div>
          </div>
        </div>
      </fieldset>

      <!-- DYNAMIC MINISTRY ALLIANCES AREA -->
      <fieldset id="ministry-section">
        <legend>
          <h2>Ministry/Committee Associations</h2>
        </legend>
        <div id="checkbox-container"></div>
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
  <?php else: ?>
    <div class="read-only-banner">
      <strong>Read-Only Mode:</strong> You must be signed in as staff or an administrator to edit or delete contact records.
    </div>
  <?php endif; ?>
  
  <?php include_once 'include/footer.php'; ?>

</body>
</html>