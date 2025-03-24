jQuery(document).ready(function($) {
    // Initialize color picker
    $('.wp-color-picker-field').wpColorPicker();

    // Handle license validation
    $('#validate-license-button').on('click', function(e) {
        e.preventDefault();

        var licenseKey = $('input[name="wse_settings[license_key]"]').val();

        if (!licenseKey) {
            toastr.error(wse_admin_settings_object.messages.error);
            return;
        }

        toastr.info(wse_admin_settings_object.messages.loading);

        $.ajax({
            url: wse_admin_settings_object.ajax_url,
            type: 'POST',
            data: {
                action: 'wse_validate_license',
                nonce: wse_admin_settings_object.license_nonce,
                license_key: licenseKey
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message);
                    // Optionally, reload the page to reflect license status
                    location.reload();
                } else {
                    toastr.error(response.data.message);
                }
            },
            error: function() {
                toastr.error(wse_admin_settings_object.messages.error);
            }
        });
    });
});
