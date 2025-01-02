jQuery(document).ready(function($) {
    // When the agreement link is clicked
    $('body').on('click', '.axytos-agreement-link', function(e) {
        e.preventDefault();

        // Show the loading state while fetching
        var agreementModal = '<div id="axytos-agreement-modal" class="axytos-agreement-modal">';
        agreementModal += '<div class="axytos-agreement-content">';
        agreementModal += '<div class="axytos-agreement-body">';
        agreementModal += '<div class="axytos-loading"></div>'; // Loading state
        agreementModal += '</div>';
        agreementModal += '<button class="axytos-close-btn">X</button>';
        agreementModal += '</div></div>';
        
        // Append the modal to the body and show it
        $('body').append(agreementModal);
        $('#axytos-agreement-modal').fadeIn();

        // Make the AJAX request to get the agreement content
        $.ajax({
            url: axytos_agreement.ajax_url,
            type: 'POST',
            data: {
                action: 'load_axytos_agreement',
                nonce: axytos_agreement.nonce,
            },
            success: function(response) {
                // Check if the request was successful
                if (response.success) {
                    // Replace the loading state with the agreement content
                    $('#axytos-agreement-modal .axytos-agreement-body').html(response.data);
                } else {
                    // Show an error message if the content could not be fetched
                    $('#axytos-agreement-modal .axytos-agreement-body').html('<div class="axytos-error">Error loading agreement.</div>');
                }
            },
            error: function() {
                // In case of an error in the AJAX request
                $('#axytos-agreement-modal .axytos-agreement-body').html('<div class="axytos-error">An error occurred while loading the agreement.</div>');
            }
        });

        // Close the modal on button click
        $('body').on('click', '.axytos-close-btn', function() {
            $('#axytos-agreement-modal').fadeOut(function() {
                $(this).remove();
            });
        });

        // Prevent body scroll when modal is open
        $('body').css('overflow', 'hidden');
    });
});
