jQuery(document).ready(function($) {
    $('.upload_image_button').click(function(e) {
        e.preventDefault();
        var button = $(this);
        var fieldId = button.data('target');

        var frame = wp.media({
            title: 'Select or Upload an Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $(fieldId).val(attachment.url);
        });

        frame.open();
    });
});
