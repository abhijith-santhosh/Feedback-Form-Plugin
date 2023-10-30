<?php
/*
Plugin Name: Feedback Form Plugin
Description: A custom plugin for collecting feedback.
Version: 1.0
Author: Abhijith Panicker S
*/
// Enqueue CSS for styling
function enqueue_feedback_form_styles() {
    wp_enqueue_style('feedback-form-styles', plugin_dir_url(__FILE__) . 'feedback-form.css');
}
add_action('wp_enqueue_scripts', 'enqueue_feedback_form_styles');

// Create a custom post type for feedback
function create_feedback_post_type() {
    register_post_type('feedback', array(
        'labels' => array(
            'name' => 'Feedback',
            'singular_name' => 'Feedback',
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor'),
    ));
}
add_action('init', 'create_feedback_post_type');

// Create the feedback form with an image upload field
function feedback_form_shortcode() {
    ob_start();
    ?>
    <form id="feedback-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('feedback_form_nonce', 'feedback_form_nonce'); ?>
        <label for="name">Name:</label>
        <input type="text" name="name" required>
        <label for="email">Email:</label>
        <input type="email" name="email" required>
        <label for="description">Description:</label>
        <textarea name="description" required></textarea>
        <label for="images">Images:</label>
        <input type="file" name="images[]" id="image-upload" multiple>
        <div id="image-preview"></div>
        <input type="submit" value="Submit Feedback">
    </form>
    
    <script>
    // JavaScript to handle image previews
    jQuery(document).ready(function($) {
        $('#image-upload').change(function() {
            $('#image-preview').html('');
            if (this.files && this.files[0]) {
                for (var i = 0; i < this.files.length; i++) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#image-preview').append('<img src="' + e.target.result + '">');
                    };
                    reader.readAsDataURL(this.files[i]);
                }
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// Handle image uploads and save in WordPress media directory
function handle_feedback_submission() {
    if (isset($_POST['feedback_form_nonce']) && wp_verify_nonce($_POST['feedback_form_nonce'], 'feedback_form_nonce')) {
        // Process and save feedback data
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $description = sanitize_textarea_field($_POST['description']);

        // Handle image uploads
        if (!empty($_FILES['images'])) {
            $uploaded_images = $_FILES['images'];
            $attachment_ids = array();
            
            foreach ($uploaded_images['tmp_name'] as $key => $tmp_name) {
                $file = array(
                    'name'     => $uploaded_images['name'][$key],
                    'type'     => $uploaded_images['type'][$key],
                    'tmp_name' => $tmp_name,
                    'error'    => $uploaded_images['error'][$key],
                    'size'     => $uploaded_images['size'][$key],
                );
                
                // Upload the image to the media library
                $attachment_id = media_handle_upload('images', 0);
                
                if (is_wp_error($attachment_id)) {
                    // Handle image upload error
                } else {
                    $attachment_ids[] = $attachment_id;
                }
            }
        }
        
        // Save feedback data and image attachment IDs as post content
        $feedback_content = 'Name: ' . $name . '<br>';
        $feedback_content .= 'Email: ' . $email . '<br>';
        $feedback_content .= 'Description: ' . $description . '<br>';
        
        if (!empty($attachment_ids)) {
            $feedback_content .= 'Images: ' . implode(', ', $attachment_ids);
        }
        
        $feedback_post = array(
            'post_title'   => 'Feedback from ' . $name,
            'post_content' => $feedback_content,
            'post_status'  => 'publish',
            'post_type'    => 'feedback', // Your custom post type
        );
        
        $post_id = wp_insert_post($feedback_post);
        
        if (is_wp_error($post_id)) {
            // Handle post creation error
        }
    }
}
add_action('init', 'handle_feedback_submission');


// Render content for the custom meta box
function render_feedback_details_meta_box($post) {
    // Retrieve saved data (if any)
    $additional_details = get_post_meta($post->ID, 'additional_details', true);

    // Output the form fields
    ?>
    <label for="additional_details">Additional Details:</label>
    <input type="text" name="additional_details" id="additional_details" value="<?php echo esc_attr($additional_details); ?>" style="width: 100%;">
    <?php
}

// Save custom meta box data
function save_feedback_details_meta_box($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Save custom meta box data
    if (isset($_POST['additional_details'])) {
        update_post_meta($post_id, 'additional_details', sanitize_text_field($_POST['additional_details']));
    }
}

// Hook into WordPress to add and save the custom meta box
add_action('add_meta_boxes', 'add_feedback_details_meta_box');
add_action('save_post', 'save_feedback_details_meta_box');

// Add a menu item for the settings page in the WordPress admin menu
function add_plugin_menu() {
    add_menu_page(
        'Feedback Form Settings',
        'Feedback Settings',
        'manage_options',
        'feedback-settings',
        'render_settings_page'
    );
}
add_action('admin_menu', 'add_plugin_menu');

// Render the settings page content
function render_settings_page() {
    ?>
    <div class="wrap">
        <h2>Feedback Form Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('feedback-settings-group');
            do_settings_sections('feedback-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings and fields
function register_plugin_settings() {
    register_setting('feedback-settings-group', 'show_image_field');
    add_settings_section('feedback-settings-section', 'Image Field Settings', null, 'feedback-settings');
    add_settings_field('show_image_field', 'Show Image Field in Feedback Form', 'show_image_field_callback', 'feedback-settings', 'feedback-settings-section');
}
add_action('admin_init', 'register_plugin_settings');

// Callback function to render the show_image_field setting
function show_image_field_callback() {
    $show_image_field = get_option('show_image_field');
    ?>
    <label>
        <input type="checkbox" name="show_image_field" value="1" <?php checked(1, $show_image_field); ?> />
        Show the image field in the feedback form
    </label>
    <?php
}

// Add a filter to conditionally show the image field in the feedback form
function conditionally_show_image_field($content) {
    if (is_singular('feedback')) {
        $show_image_field = get_option('show_image_field', 1); // Default to showing the image field
        if (!$show_image_field) {
            // Remove the image field from the content
            $content = str_replace('<input type="file" name="images[]" id="image-upload" multiple>', '', $content);
        }
    }
    return $content;
}
add_filter('the_content', 'conditionally_show_image_field');
