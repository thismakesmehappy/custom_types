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

function create_custom_post_type($type, $name, $fields, $include_default_body = false, $description = null, $position = null) {
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
        'show_in_rest' => true, // Expose to the REST API
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

function render_custom_field($post, $metabox) {
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
            $posts[] = array(
                'ID' => get_the_ID(),
                'title' => get_the_title(),
                'content' => get_the_content(),
            );
        }
        wp_reset_postdata();
    }

    return new WP_REST_Response($posts, 200);
}
