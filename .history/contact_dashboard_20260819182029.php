function loadContactList() {
  let membersOnly = $('input[name="contact_filter"]:checked').val() || 0;
  let ageFilter   = $('input[name="age_filter"]:checked').val() || 0;
  let currentSelection = $('#contactID').val();

  $.ajax({
    url: "getLists.php",
    type: 'POST',
    data: {
      members_only: membersOnly,
      age_filter: ageFilter
    },
    dataType: 'json',
    success: function(data) {
      if (data.status === 'error' || data.error) {
        showOnScreenError(data.message || data.error || "Failed to load contacts list.");
        return;
      }

      let $dropdown = $('#contactID').empty().append($('<option>', {
        value: '',
        text: '-- Select Contact --'
      }));

      if (data.contacts && Array.isArray(data.contacts) && data.contacts.length > 0) {
        $.each(data.contacts, function(i, item) {
          $dropdown.append($('<option>', {
            value: item.contact_id,
            text: item.fullname
          }));
        });
      } else {
        $dropdown.append($('<option>', {
          value: '',
          text: 'No contacts found matching filter'
        }));
      }

      if (currentSelection) {
        $('#contactID').val(currentSelection);
      }
    },
    error: function(xhr, status, error) {
      console.error("getLists error response:", xhr.responseText);
      showOnScreenError("Error loading contact list. Check server logs or console.");
    }
  });
}