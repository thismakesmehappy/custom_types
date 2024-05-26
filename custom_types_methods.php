<?php
$custom_types = load_custom_types_from_json(get_template_directory() . '/types/custom_type_definitions.json');

function load_custom_types_from_json($file)
{
    $json = file_get_contents($file);
    return json_decode($json, true);
}

function create_custom_post_types()
{
    global $custom_types;
    foreach ($custom_types as $custom_type) {
        create_custom_post_type_from_array($custom_type);
    }
}

function create_custom_post_type_from_array($array)
{
    create_custom_post_type($array['type'], $array['name'], $array['fields'], $array['include_default_body'] ?? true, $array['description'] ?? null, $array['position'] ?? null);
}

function create_custom_post_type($type, $name, $fields, $include_default_body = false, $description = null, $position = null)
{
    $supports = array('title', 'thumbnail');
    if ($include_default_body) {
        $supports[] = 'editor';
    }

    $labels = array(
        'name' => _x($name, 'post type general name', 'your-textdomain'),
        'singular_name' => _x($name, 'post type singular name', 'your-textdomain'),
        'menu_name' => _x($name, 'admin menu', 'your-textdomain'),
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'supports' => $supports,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => true,
        'can_export' => true,
        'capability_type' => 'post',
    );

    if (isset($description)) {
        $args['description'] = $description;
    }

    if (isset($position)) {
        $args['menu_position'] = $position;
    }

    register_post_type($type, $args);

    add_action('add_meta_boxes', function () use ($type, $fields) {
        foreach ($fields as $field_id => $field) {
            add_meta_box(
                "{$type}_{$field_id}_meta_box",
                $field['label'],
                'render_custom_field',
                $type,
                'normal',
                'default',
                array('field_id' => $field_id, 'type' => $field['type'])
            );
        }
    });

    add_action('save_post_' . $type, function ($post_id) use ($fields) {
        foreach ($fields as $field_id => $field) {
            if (!isset($_POST[$field_id . '_nonce']) || !wp_verify_nonce($_POST[$field_id . '_nonce'], 'save_' . $field_id)) {
                continue;
            }

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
            if (!current_user_can('edit_post', $post_id)) return;

            $field_value = $_POST[$field_id] ?? '';
            if (isset($field['sanitization_callback']) && is_callable($field['sanitization_callback'])) {
                $field_value = call_user_func($field['sanitization_callback'], $field_value);
            }
            update_post_meta($post_id, $field_id, $field_value);
        }
    });
}

function render_custom_field($post, $metabox)
{
    wp_nonce_field('save_' . $metabox['args']['field_id'], $metabox['args']['field_id'] . '_nonce');

    $field_id = $metabox['args']['field_id'];
    $field_type = $metabox['args']['type'];
    $value = get_post_meta($post->ID, $field_id, true);

    echo '<label for="' . esc_attr($field_id) . '">' . esc_html($metabox['title']) . '</label>';
    switch ($field_type) {
        case 'text':
            echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '" class="widefat">';
            break;
        case 'textarea':
            echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" class="widefat">' . esc_textarea($value) . '</textarea>';
            break;
        case 'image':
            echo '<input type="url" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" value="' . esc_url($value) . '" class="widefat">';
            echo '<button type="button" class="button upload_image_button" data-target="#' . esc_attr($field_id) . '">Upload Image</button>';
            break;
    }
}
