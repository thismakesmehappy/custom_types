<?php
$custom_types = load_custom_types_from_json(get_template_directory() . "$types_directory/custom_type_definitions.json");

function enqueue_custom_admin_scripts() {
    global $types_directory;
    wp_enqueue_script('my-custom-admin-script', get_template_directory_uri() . "$types_directory/js/image_for_types.js", array('jquery'), null, true);
    wp_enqueue_media(); // Ensures the WordPress media uploader is available
}
add_action('admin_enqueue_scripts', 'enqueue_custom_admin_scripts');

function load_custom_types_from_json($file) {
    $json = file_get_contents($file);
    return json_decode($json, true);
}

function create_custom_post_types() {
    global $custom_types;
    foreach ($custom_types as $custom_type) {
        create_custom_post_type_from_array($custom_type);
    }
}

function create_custom_post_type_from_array($array) {
    create_custom_post_type($array['type'], $array['name'], $array['fields'], $array['include_default_body'] ?? true, $array['description'] ?? null, $array['position'] ?? null);
    if (isset($array['endpoints'])) {
        create_custom_post_type_endpoints($array['type'], $array['endpoints']);
    }
}

function create_custom_post_type($type, $name, $fields, $include_default_body = true, $description = null, $position = null) {
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
        'show_in_rest' => $include_default_body, // Expose to the REST API only if content is true
    );

    if (isset($description)) {
        $args['description'] = $description;
    }

    if (isset($position)) {
        $args['menu_position'] = $position;
    }

    register_post_type($type, $args);

    // Register custom fields with the REST API if content is true
    if ($include_default_body) {
        foreach ($fields as $field_id => $field) {
            register_rest_field($type, $field_id, array(
                'get_callback' => function($post_arr) use ($field_id) {
                    return get_post_meta($post_arr['id'], $field_id, true);
                },
                'update_callback' => null,
                'schema' => null,
            ));
        }
    }

    add_action('add_meta_boxes', function () use ($type, $fields) {
        foreach ($fields as $field_id => $field) {
            add_meta_box(
                "{$type}_{$field_id}_meta_box",
                $field['label'],
                'render_custom_field',
                $type,
                'normal',
                'default',
                array('field_id' => $field_id, 'type' => $field['type'], 'repeatable' => $field['repeatable'] ?? false)
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
            if ($field['repeatable'] ?? false) {
                // Save as an array of values
                $field_value = array_filter($field_value); // Remove empty values
                update_post_meta($post_id, $field_id, $field_value);
            } else {
                if (isset($field['sanitization_callback']) && is_callable($field['sanitization_callback'])) {
                    $field_value = call_user_func($field['sanitization_callback'], $field_value);
                }
                update_post_meta($post_id, $field_id, $field_value);
            }
        }
    });
}

function render_custom_field($post, $metabox) {
    wp_nonce_field('save_' . $metabox['args']['field_id'], $metabox['args']['field_id'] . '_nonce');

    $field_id = $metabox['args']['field_id'];
    $field_type = $metabox['args']['type'];
    $repeatable = $metabox['args']['repeatable'] ?? false;
    $values = get_post_meta($post->ID, $field_id, true);

    if ($repeatable) {
        if (!is_array($values)) {
            $values = array($values); // Convert to array if not already
        }
    } else {
        $values = array($values); // Ensure it's an array for non-repeatable fields
    }

    echo '<label for="' . esc_attr($field_id) . '">' . esc_html($metabox['title']) . '</label>';
    echo '<div class="custom-fields-container" data-field-id="' . esc_attr($field_id) . '" data-field-type="' . esc_attr($field_type) . '">';
    foreach ($values as $value) {
        render_field_html($field_id, $field_type, $value, $repeatable);
    }
    echo '</div>';
    if ($repeatable) {
        echo '<button type="button" class="button add-field-button" data-field-id="' . esc_attr($field_id) . '">ADD</button>';
    }
}

function render_field_html($field_id, $field_type, $value, $repeatable) {
    switch ($field_type) {
        case 'text':
            echo '<div class="custom-field"><input type="text" name="' . esc_attr($field_id) . '[]" value="' . esc_attr($value) . '" class="widefat">';
            break;
        case 'textarea':
            echo '<div class="custom-field"><textarea name="' . esc_attr($field_id) . '[]" class="widefat">' . esc_textarea($value) . '</textarea>';
            break;
        case 'image':
            echo '<div class="custom-field"><input type="url" name="' . esc_attr($field_id) . '[]" value="' . esc_url($value) . '" class="widefat">';
            echo '<button type="button" class="button upload_image_button" data-target="#' . esc_attr($field_id) . '">Upload Image</button>';
            break;
    }
    if ($repeatable) {
        echo '<button type="button" class="button remove-field-button">REMOVE</button></div>';
    }
}

function create_custom_post_type_endpoints($type, $endpoints) {
    foreach ($endpoints as $endpoint) {
        add_action('rest_api_init', function () use ($type, $endpoint) {
            register_rest_route("custom/{$endpoint['version']}", "/{$type}/{$endpoint['route']}", array(
                'methods' => $endpoint['methods'],
                'callback' => function($request) use ($endpoint, $type) {
                    return call_user_func($endpoint['callback'], $request, $type);
                },
            ));
        });
    }
}

function get_custom_posts_with_meta($request, $type) {
    $meta_key = $request->get_param('meta_key');
    $meta_value = $request->get_param('meta_value');

    $args = array(
        'post_type' => $type,
        'meta_query' => array(
            array(
                'key' => $meta_key,
                'value' => $meta_value,
                'compare' => '='
            )
        )
    );

    $query = new WP_Query($args);
    $posts = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $custom_fields = get_post_meta(get_the_ID());

            // Deserialize each field if necessary
            foreach ($custom_fields as $key => $value) {
                if (is_serialized($value[0])) {
                    $custom_fields[$key] = maybe_unserialize($value[0]);
                } else {
                    $custom_fields[$key] = $value[0];
                }
            }

            $posts[] = array(
                'ID' => get_the_ID(),
                'title' => get_the_title(),
                'content' => get_the_content(),
                'custom_fields' => $custom_fields, // Include custom fields and unserialize
            );
        }
        wp_reset_postdata();
    }

    return new WP_REST_Response($posts, 200);
}
