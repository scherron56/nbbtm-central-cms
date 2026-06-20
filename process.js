<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  
$(document).ready(function () {
        // Fetch Contact/Member data
        $('#contact_select').change(function () {
            var contactid = $(this).val();
            if (contactid) {
                $.ajax({
                    url: '/include/get_contact.php',
                    type: 'GET',
                    data: {
                        contact_id: contactid
                    },
                    dataType: 'json',
                    success: function (data) {
                        // Populate the form fields with the returned JSON
                        $('#contact_id').val(data.contact_id || '');
                        $('#firstname').val(data.first_name || '');
                        $('#middlename').val(data.middle_name || '');
                        $('#lastname').val(data.last_name || '');
                        $('#address').val(data.address_1 || '');
                        $('#city').val(data.city || '');
                        $('#state').val(data.state || '');
                        $('#zipcode').val(data.zipcode || '');
                        $('submit_btn').text('Update Contact/Member');
                        $('#reset_btn').show();
                    });
            }
            else {
                // Reset form if "--Add New Contact/Member --" is chosen
                $('#member_form')[0].reset();
                $('#contact_id').val('');
                $('submit_btn').text('Add Contact/Member');
                $('#reset_btn').hide();
            }
        });
        // Reset button functionality
        $('reset_btn').click(function () {
            $('#contact_select').val('');
            $('#member_form')[0].reset();
            $('#contact_id').val('');
            $('submit_btn').text('Add Contact/Member');
            $(this).hide();
        })

        // Handle Insert/Updates via AJAX
        $('#member_form').submit(function (e) {
            e.preventDefault(); // Prevent standard page reload
            $.ajax({
                url: '/include/save_contact.php',
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
