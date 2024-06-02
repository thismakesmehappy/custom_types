jQuery(document).ready(function($) {
    function addField(fieldId, fieldType, value = '', options = []) {
        let fieldHtml = '';
        switch (fieldType) {
            case 'text':
                fieldHtml = '<input type="text" name="' + fieldId + '[]" value="' + value + '" class="widefat">';
                break;
            case 'textarea':
                fieldHtml = '<textarea name="' + fieldId + '[]" class="widefat">' + value + '</textarea>';
                break;
            case 'image':
                fieldHtml = '<input type="url" name="' + fieldId + '[]" value="' + value + '" class="widefat">';
                fieldHtml += '<button type="button" class="button upload_image_button" data-target="#' + fieldId + '">Upload Image</button>';
                break;
            case 'checkbox':
                options.forEach(option => {
                    const checked = value.includes(option.value) ? 'checked' : '';
                    fieldHtml += '<label><input type="checkbox" name="' + fieldId + '[]" value="' + option.value + '" ' + checked + '> ' + option.label + '</label><br>';
                });
                break;
            case 'radio':
                options.forEach(option => {
                    const checked = (value === option.value) ? 'checked' : '';
                    fieldHtml += '<label><input type="radio" name="' + fieldId + '" value="' + option.value + '" ' + checked + '> ' + option.label + '</label><br>';
                });
                break;
            case 'dropdown':
                fieldHtml = '<select name="' + fieldId + '" class="widefat">';
                options.forEach(option => {
                    const selected = (value === option.value) ? 'selected' : '';
                    fieldHtml += '<option value="' + option.value + '" ' + selected + '>' + option.label + '</option>';
                });
                fieldHtml += '</select>';
                break;
        }
        fieldHtml += '<button type="button" class="button remove-field-button">REMOVE</button>';
        return '<div class="custom-field">' + fieldHtml + '</div>';
    }

    $('.add-field-button').click(function(e) {
        e.preventDefault();
        const fieldId = $(this).data('field-id');
        const fieldType = $('.custom-fields-container[data-field-id="' + fieldId + '"]').data('field-type');
        const fieldOptions = $('.custom-fields-container[data-field-id="' + fieldId + '"]').data('field-options');
        const newField = addField(fieldId, fieldType, '', fieldOptions);
        $('.custom-fields-container[data-field-id="' + fieldId + '"]').append(newField);
    });

    $(document).on('click', '.remove-field-button', function(e) {
        e.preventDefault();
        if ($(this).parent().siblings().length > 0) {
            $(this).parent().remove();
        }
    });

    $(document).on('click', '.upload_image_button', function(e) {
        e.preventDefault();
        const button = $(this);
        const fieldId = button.data('target');

        const frame = wp.media({
            title: 'Select or Upload an Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            button.siblings('input[type="url"]').val(attachment.url);
        });

        frame.open();
    });
});
