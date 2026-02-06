<?php
    /*
    Plugin Name: WP Comment Cleaner – Delete All Comments, Disable Comments, Bulk Delete & Remove Comments
    Plugin URI: http://www.navneetsoni.com/plugins/delete-comments
    Description: A complete WordPress comment management plugin to delete comments, disable comments, bulk delete comments, remove comments, and delete all comments in one click. Easily manage approved, pending, spam, or trashed comments. Disable comments globally or by post type, apply role-based exclusions, export comments, and automate spam cleanup with scheduled deletion.
    Author: royalnavneet
    Version: 6.7
    Author URI: http://www.navneetsoni.com 
    */
	
if ( ! function_exists( 'nonu_fs' ) ) {
    // Create a helper function for easy SDK access.
    function nonu_fs() {
        global $nonu_fs;

        if ( ! isset( $nonu_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';
            $nonu_fs = fs_dynamic_init( array(
                'id'                  => '7346',
                'slug'                => 'delete-all-comments-of-website',
                'type'                => 'plugin',
                'public_key'          => 'pk_3b87748f4797c99614f13caffb811',
                'is_premium'          => true,
                'premium_suffix'      => 'Plus',
                // If your plugin is a serviceware, set this option to false.
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'menu'                => array(
                    'slug'           => 'delete_comment',
                    'support'        => false,
                    'parent'         => array(
                        'slug' => 'tools.php',
                    ),
                ),
            ) );
        }

        return $nonu_fs;
    }

    // Init Freemius.
    nonu_fs();
    // Signal that SDK was initiated.
    do_action( 'nonu_fs_loaded' );
}

$fs = nonu_fs();

add_action( 'admin_enqueue_scripts', 'my_admin_scripts_nav' );




add_action( 'admin_menu', 'nav_delete_all_comment' );
define( 'NAV_COMENT_PLUGIN_URI', plugin_dir_url( __FILE__ ) );

// Create WordPress admin menu

function my_admin_scripts_nav($hook) {
    if ($hook != 'tools_page_delete_comment') {
        return;
    }

    // Enqueue jQuery
    wp_enqueue_script('jquery');

    // Enqueue Bootstrap
    wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js', array('jquery'));

    // Enqueue SweetAlert2
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js');

    // Enqueue custom CSS and JS
    wp_enqueue_style('nav-comments-admin', plugins_url('css/nav-comments-admin.css', __FILE__), array(), '1.0.0');
    wp_enqueue_script('nav-comments-admin', plugins_url('js/nav-comments-admin.js', __FILE__), array('jquery'), '1.0.0', true);

    // Create nonces for all AJAX actions
    $import_nonce = wp_create_nonce('nav_comments_import_nonce');
    $export_nonce = wp_create_nonce('nav_comments_export_nonce');
    $settings_nonce = wp_create_nonce('nav_comments_settings_nonce');

    // Localize script with all nonces
    wp_localize_script('nav-comments-admin', 'navCommentsSettings', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'import_nonce' => $import_nonce,
        'export_nonce' => $export_nonce,
        'settings_nonce' => $settings_nonce
    ));
}


// Add this function to handle success/error messages
function nav_show_admin_notice($message, $type = 'success') {
    add_action('admin_notices', function() use ($message, $type) {
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    });
}

// Add this function to handle saving settings
function nav_ajax_save_comment_settings() {
    // Check if user has proper permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'message' => 'You do not have sufficient permissions to perform this action.'
        ));
        return;
    }

    // Verify nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'nav_comments_settings_nonce')) {
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh the page and try again.'
        ));
        return;
    }

    try {
        // Save disable type
        $disable_type = isset($_POST['nav_disable_type']) ? sanitize_text_field($_POST['nav_disable_type']) : 'everywhere';
        update_option('nav_disable_comments_type', $disable_type);

        // Save post type settings
        $post_types = get_post_types(array('public' => true), 'objects');
        foreach ($post_types as $post_type) {
            $option_name = 'nav_disable_comments_' . $post_type->name;
            $value = isset($_POST[$option_name]) ? '1' : '0';
            update_option($option_name, $value);
        }

        // Save role exclusions
        $enable_role_exclusions = isset($_POST['nav_enable_role_exclusions']) ? '1' : '0';
        update_option('nav_enable_role_exclusions', $enable_role_exclusions);

        $wp_roles = wp_roles();
        foreach ($wp_roles->get_names() as $role => $name) {
            $option_name = 'nav_exclude_role_' . $role;
            $value = isset($_POST[$option_name]) ? '1' : '0';
            update_option($option_name, $value);
        }

        // Save avatar settings
        $show_avatars = isset($_POST['nav_show_avatars']) ? '1' : '0';
        update_option('nav_show_avatars', $show_avatars);

        // Save API settings
        $disable_api_comments = isset($_POST['nav_disable_api_comments']) ? '1' : '0';
        update_option('nav_disable_api_comments', $disable_api_comments);

        wp_send_json_success(array(
            'message' => 'Settings saved successfully.',
            'disable_type' => $disable_type
        ));

    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => 'An error occurred while saving settings: ' . $e->getMessage()
        ));
    }
}

// Add this function to handle resetting settings
function nav_reset_comment_settings() {
    // Delete all comment-related options
    delete_option('nav_disable_comments_type');
    delete_option('nav_enable_role_exclusions');
    delete_option('nav_show_avatars');
    delete_option('nav_disable_api_comments');
    
    // Delete all post type specific options
    $post_types = get_post_types(array('public' => true), 'objects');
    foreach ($post_types as $post_type) {
        delete_option('nav_disable_comments_' . $post_type->name);
    }
    
    // Delete all role exclusion options
    $wp_roles = wp_roles();
    foreach ($wp_roles->get_names() as $role => $name) {
        delete_option('nav_exclude_role_' . $role);
    }
    
    return true;
}

// Add AJAX handler for resetting settings
function nav_ajax_reset_comment_settings() {
    // Check if user has proper permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'message' => 'You do not have sufficient permissions to perform this action.'
        ));
        return;
    }

    // Verify nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'nav_comments_settings_nonce')) {
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh the page and try again.'
        ));
        return;
    }

    try {
        if (nav_reset_comment_settings()) {
            wp_send_json_success(array(
                'message' => 'All comment settings have been reset successfully.'
            ));
        } else {
            throw new Exception('Failed to reset settings');
        }
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => 'An error occurred while resetting settings: ' . $e->getMessage()
        ));
    }
}
add_action('wp_ajax_nav_reset_comment_settings', 'nav_ajax_reset_comment_settings');

// Update the nav_disable_comments_settings function to include color changes and selection persistence
function nav_disable_comments_settings() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $fs = nonu_fs();
    $disable_type = get_option('nav_disable_type', 'everywhere');
    $post_types = get_post_types(array('public' => true), 'objects');
    
    $excluded_post_types = array(
        'linked_variations',
        'my_templates',
        'layouts',
        'frequently_bought_together',
        'html_blocks',
        'size_guides',
        'sidebars'
    );
    ?>
    <style>
        .nav-radio input[type="radio"]:checked + .nav-radio-label {
            color: #2271b1;
            font-weight: bold;
        }
        .nav-radio input[type="radio"]:checked + .nav-radio-label::before {
            content: "✓";
            color: #2271b1;
            margin-right: 5px;
        }
        .nav-radio {
            display: block;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        .nav-radio:hover {
            background-color: #f0f0f1;
        }
        .nav-radio input[type="radio"] {
            margin-right: 8px;
        }
        .nav-radio.selected {
            background-color: #f0f7ff;
            border-color: #2271b1;
        }
        .post-types-container {
            display: none;
            margin-top: 20px;
        }
        .post-types-container.active {
            display: block;
        }
        .nav-checkbox input[type="checkbox"]:checked + .nav-checkbox-label {
            color: #2271b1;
            font-weight: bold;
        }
        .nav-toggle-switch input[type="checkbox"]:checked + .nav-toggle-slider {
            background-color: #2271b1;
        }
        .nav-radio, .nav-checkbox {
            transition: all 0.3s ease;
        }
        .nav-radio:hover, .nav-checkbox:hover {
            background-color: #f0f0f1;
            border-radius: 4px;
            padding: 5px;
        }
        .nav-button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .nav-button-reset {
            background: #dc2626 !important;
            border-color: #dc2626 !important;
            color: white !important;
        }
        .nav-button-reset:hover {
            background: #b91c1c !important;
            border-color: #b91c1c !important;
        }
        .premium-message {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background-color: #2271b1;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
        }
        .premium-message:hover {
            background-color: #135e96;
        }
        .premium-feature {
            opacity: 0.8;
        }
        .premium-feature:hover {
            opacity: 1;
        }
    </style>

    <div id="poststuff">
    <div id="post-body" class="metabox-holder columns-2">
        <div id="post-body-content">
            <form id="nav-disable-comments-form" method="post">
                <?php wp_nonce_field('nav_comments_settings_nonce'); ?>
                
                <div class="nav-section">
                    <div class="nav-section-header">
                        <h2>Global Settings</h2>
                    </div>
                    <div class="nav-section-content">
                        <div class="nav-warning-box">
                            <i class="dashicons dashicons-warning"></i>
                            <p>Warning: Disabling comments will prevent users from commenting on your website.</p>
                        </div>

                        <div class="" style="margin: 20px 0;">
                            <label class="nav-radio <?php echo $disable_type === 'everywhere' ? 'selected' : ''; ?>">
                                <input type="radio" name="nav_disable_type" value="everywhere" <?php checked($disable_type, 'everywhere'); ?>>
                                <span class="nav-radio-label">Disable comments everywhere</span>
                            </label>
                            <label class="nav-radio <?php echo $disable_type === 'specific' ? 'selected' : ''; ?> premium-feature">
                                <input type="radio" name="nav_disable_type" value="specific" <?php checked($disable_type, 'specific'); ?> >
                                <span class="nav-radio-label">Disable comments on specific post types</span>
                                <?php if (!$fs->is_paying()): ?>
                                    <span class="premium-message" title="Upgrade to Premium to enable this feature">Premium</span>
                                <?php endif; ?>
                            </label>
                        </div>

                        <div class="post-types-container <?php echo $disable_type === 'specific' ? 'active' : ''; ?>" style="margin-top: 20px;">
                            <div class="nav-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                                <?php
                                foreach ($post_types as $post_type) {
                                    if (!in_array($post_type->name, $excluded_post_types) && $post_type->name !== 'attachment') {
                                        $is_disabled = get_option('nav_disable_comments_' . $post_type->name, '0');
                                        ?>
														  <label class="nav-checkbox premium-feature" style="display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 4px; <?php echo (!$fs->is_paying()) ? 'cursor: not-allowed; opacity: 0.6;' : 'cursor: pointer;'; ?>">
						<input type="checkbox" name="nav_disable_comments_<?php echo $post_type->name; ?>" 
							value="1" <?php checked($is_disabled, '1'); ?> <?php disabled(!$fs->is_paying() || $disable_type === 'everywhere'); ?>>
						<span class="nav-checkbox-label"><?php echo $post_type->label; ?></span>
						<?php if (!$fs->is_paying()): ?>
							<span class="premium-message" title="Upgrade to Premium to enable this feature" style="margin-left: 8px; color: #d97706; font-weight: bold;">Premium</span>
						<?php endif; ?>
					</label>
                                        <?php
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
         
   
   



                    <div class="nav-section">
                        <div class="nav-section-header">
                            <h2>Role-Based Exclusions</h2>
                        </div>
                        <div class="nav-section-content">
                            <div class="nav-info-box">
                                <i class="dashicons dashicons-info"></i>
                                <p>Enable role-based exclusions to allow specific user roles to comment even when comments are disabled.</p>
                            </div>

                            <div class="nav-toggle-switch premium-feature" style="margin: 20px 0; position: relative;">
                                <input type="checkbox" id="nav_enable_role_exclusions" name="nav_enable_role_exclusions" 
                                    value="1" <?php checked(get_option('nav_enable_role_exclusions', '0'), '1'); ?> <?php disabled(!$fs->is_paying()); ?>>
                                <label for="nav_enable_role_exclusions" class="nav-toggle-slider"></label>
                                <span class="nav-toggle-label" style="margin-left: 10px;">Enable Role-Based Exclusions</span>
                                <?php if (!$fs->is_paying()): ?>
                                    <span class="premium-message" title="Upgrade to Premium to enable this feature">Premium</span>
                                <?php endif; ?>
                            </div>

                            <div class="nav-roles-container <?php echo get_option('nav_enable_role_exclusions', '0') === '1' ? 'active' : ''; ?>" style="margin-top: 20px;">
                                <div class="nav-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                                    <?php
                                    $wp_roles = wp_roles();
                                    foreach ($wp_roles->get_names() as $role => $name) {
                                        $is_excluded = get_option('nav_exclude_role_' . $role, '0');
                                        ?>
                                        <label class="nav-checkbox premium-feature" style="display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" name="nav_exclude_role_<?php echo $role; ?>" 
                                                value="1" <?php checked($is_excluded, '1'); ?> <?php disabled(!$fs->is_paying() || get_option('nav_enable_role_exclusions', '0') !== '1'); ?>>
                                            <span class="nav-checkbox-label"><?php echo $name; ?></span>
                                            <?php if (!$fs->is_paying()): ?>
                                                <span class="premium-message" title="Upgrade to Premium to enable this feature">Premium</span>
                                            <?php endif; ?>
                                        </label>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-header">
                            <h2>Avatar Settings</h2>
                        </div>
                        <div class="nav-section-content">
                            <div class="nav-toggle-switch" style="margin: 20px 0;">
                                <input type="checkbox" id="nav_show_avatars" name="nav_show_avatars" value="1" <?php checked(get_option('nav_show_avatars', '1'), '1'); ?>>
                                <label for="nav_show_avatars" class="nav-toggle-slider"></label>
                                <span class="nav-toggle-label" style="margin-left: 10px;">Show Avatars in Comments</span>
                            </div>
                        </div>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-header">
                            <h2>API Settings</h2>
                        </div>
                        <div class="nav-section-content">
                            <div class="nav-toggle-switch" style="margin: 20px 0;">
                                <input type="checkbox" id="nav_disable_api_comments" name="nav_disable_api_comments" value="1" <?php checked(get_option('nav_disable_api_comments', '0'), '1'); ?>>
                                <label for="nav_disable_api_comments" class="nav-toggle-slider"></label>
                                <span class="nav-toggle-label" style="margin-left: 10px;">Disable Comments via REST API</span>
                            </div>
                        </div>
                    </div>

                    <div class="nav-form-actions" style="margin-top: 30px;">
                        <div class="nav-submit-button">
                            <button type="submit" class="button button-primary" style="background: #2271b1; border-color: #2271b1;">
                                <i class="dashicons dashicons-saved"></i>
                                Save Settings
                            </button>
                            <button type="button" id="nav-reset-settings" class="button nav-button-reset">
                                <i class="dashicons dashicons-trash"></i>
                                Reset All Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div id="postbox-container-1" class="postbox-container">
                <div id="side-sortables" class="meta-box-sortables ui-sortable">
                    <?php if (!$fs->is_paying()): ?>
                        <div class="postbox support-postbox" style="background-color: #fcf8e3">
                            <div class="handlediv" title="Click to toggle"><br></div>
                          <div class="inside">
    <div style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <h4 style="color: #2271b1; margin-top: 0; font-size: 20px; text-align: center;">🐾 Pawcause – 100% goes to Dog Charity</h4>

        <div style="background: #2271b1; color: white; padding: 15px; border-radius: 6px; text-align: center; margin: 15px 0;">
            <div style="font-size: 28px; font-weight: bold;">$0.83</div>
            <div style="font-size: 14px; opacity: 0.9;">🐶 100% of your upgrade goes directly to supporting dog charities.</div>
        </div>

        <ul style="list-style: none; padding-left: 0; margin: 20px 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee; padding: 15px 0;">
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">🚀</span>
                <span><strong>Unlimited Comments</strong> - Delete as many comments as you need</span>
            </li>
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">📅</span>
                <span><strong>Smart Date Filtering</strong> - Delete comments by date range</span>
            </li>
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">📊</span>
                <span><strong>Export to CSV</strong> - Backup your comments before deletion</span>
            </li>
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">🛑</span>
                <span><strong>Disable Comments</strong> - Turn off comments globally or by post type</span>
            </li>
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">👥</span>
                <span><strong>Role-Based Exclusions</strong> - Allow specific roles to bypass restrictions</span>
            </li>
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">⭐</span>
                <span><strong>Priority Support</strong> - Get help when you need it</span>
            </li>
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">🔄</span>
                <span><strong>Auto Spam Cleanup</strong> - Automatically remove spam comments</span>
            </li>
        </ul>

        <div style="text-align: center;">
            <a href="<?php echo nonu_fs()->get_upgrade_url(); ?>" class="button button-primary" style="font-size: 12px; padding: 8px 25px; background-color: #2271b1; border-color: #2271b1; width: 100%;">
                Upgrade Now - Get Premium Access
            </a>
            <p style="color: #666; font-size: 12px; margin-top: 10px; text-align: center;">
                <span style="color: #2271b1;">✓</span> 30-day money-back guarantee
            </p>
        </div>
    </div>
</div>

                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Handle radio button changes
        $("input[name='nav_disable_type']").on("change", function() {
            var isEverywhere = $(this).val() === "everywhere";
            $(".post-types-container").toggleClass("active", !isEverywhere);
            $(".post-types-container input[type='checkbox']").prop("disabled", isEverywhere);
        });

        // Handle role exclusion toggle
        $("#nav_enable_role_exclusions").on("change", function() {
            var isEnabled = $(this).is(":checked");
            $(".nav-roles-container").toggleClass("active", isEnabled);
            $(".nav-roles-container input[type='checkbox']").prop("disabled", !isEnabled);
        });

        // Handle premium message clicks
        $(".premium-message").on("click", function() {
            window.location.href = "<?php echo $fs->get_upgrade_url(); ?>";
        });

        // Handle reset button click
        $("#nav-reset-settings").on("click", function() {
            Swal.fire({
                title: "Reset Settings?",
                text: "Are you sure you want to reset all comment settings? This will enable comments everywhere and remove all restrictions.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, reset settings",
                cancelButtonText: "No, cancel",
                confirmButtonColor: "#dc2626",
                cancelButtonColor: "#64748b",
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return $.ajax({
                        url: navCommentsSettings.ajaxurl,
                        type: "POST",
                        data: {
                            action: "nav_reset_comment_settings",
                            _wpnonce: $("#_wpnonce").val()
                        }
                    }).then(function(response) {
                        if (response.success) {
                            return response.data;
                        }
                        throw new Error(response.data?.message || "Failed to reset settings");
                    }).catch(function(error) {
                        Swal.showValidationMessage(`Request failed: ${error.message || error}`);
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: "Success!",
                        text: result.value?.message || "Settings have been reset successfully.",
                        icon: "success",
                        confirmButtonColor: "#22c55e"
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });
        });
    });
    </script>
    <?php
}

// Add functions to handle the new settings
function nav_handle_global_comments($open, $post_id) {
    if (get_option('nav_disable_comments_global', false)) {
        return false;
    }
    return $open;
}
add_filter('comments_open', 'nav_handle_global_comments', 20, 2);
add_filter('pings_open', 'nav_handle_global_comments', 20, 2);

function nav_handle_role_exclusions($open, $post_id) {
    $excluded_roles = get_option('nav_excluded_roles', array());
    $user = wp_get_current_user();
    
    if (!empty($excluded_roles)) {
        foreach ($excluded_roles as $role) {
            if (in_array($role, $user->roles)) {
                return $open; // Return original open state — don't force true
            }
        }
    }
    
    return $open;
}
add_filter('comments_open', 'nav_handle_role_exclusions', 30, 2);
add_filter('pings_open', 'nav_handle_role_exclusions', 30, 2);

function nav_handle_avatar_display($avatar_defaults) {
    if (get_option('nav_disable_avatar', false)) {
        $avatar_defaults = array('blank' => 'Blank');
    }
    return $avatar_defaults;
}
add_filter('avatar_defaults', 'nav_handle_avatar_display');

function nav_handle_xmlrpc_comments($methods) {
    if (get_option('nav_disable_xmlrpc', false)) {
        unset($methods['wp.newComment']);
    }
    return $methods;
}
add_filter('xmlrpc_methods', 'nav_handle_xmlrpc_comments');

function nav_handle_rest_api_comments($response, $handler, $request) {
    if (get_option('nav_disable_rest_api', false)) {
        if ($request->get_route() === '/wp/v2/comments') {
            return new WP_Error('rest_comments_disabled', 'Comments are disabled via REST API.', array('status' => 403));
        }
    }
    return $response;
}
add_filter('rest_pre_dispatch', 'nav_handle_rest_api_comments', 10, 3);

// Function to actually disable comments
function nav_disable_comments($open, $post_id) {
    if (!$open) {
        return false;
    }

    // Fix: only run logic if option exists
    $disable_type = get_option('nav_disable_comments_type');

    // If no setting exists (i.e., reset), return original state
    if ($disable_type === false) {
        return $open;
    }

    $disable_type = sanitize_text_field($disable_type);

    // Role-based exclusions
    if (get_option('nav_enable_role_exclusions', false)) {
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID > 0) {
            foreach ((array) $current_user->roles as $role) {
                if (get_option('nav_exclude_role_' . $role, false)) {
                    return $open;
                }
            }
        }
    }

    // Global disable
    if ($disable_type === 'everywhere') {
        return false;
    }

    $post_type = get_post_type($post_id);
    if (!$post_type) {
        return $open;
    }

    // Specific post type disable
    if ($disable_type === 'specific' && get_option('nav_disable_comments_' . $post_type, false)) {
        return false;
    }

    return $open;
}
add_filter('comments_open', 'nav_disable_comments', 20, 2);
add_filter('pings_open', 'nav_disable_comments', 20, 2);





if( !function_exists("nav_delete_all_comment") )
{
function nav_delete_all_comment() {
    global $wpdb;

    $count = 0;
    $favcolor_nav = isset($_POST['nav_delete_comment']) ? $_POST['nav_delete_comment'] : '';
    $date_filter = '';

    // Apply date range only for paid users
    if (nonu_fs()->is_paying()) {
        $date_from = isset($_POST['nav_delete_commentfrom']) ? sanitize_text_field($_POST['nav_delete_commentfrom']) : '';
        $date_to = isset($_POST['nav_delete_commentto']) ? sanitize_text_field($_POST['nav_delete_commentto']) : '';

        if ($date_from && $date_to) {
            $from = date('Y-m-d 00:00:00', strtotime($date_from));
            $to = date('Y-m-d 23:59:59', strtotime($date_to));
            $date_filter = $wpdb->prepare(" AND comment_date BETWEEN %s AND %s", $from, $to);
        }
    }

    switch($favcolor_nav) {
        case 'nav_delete_all':
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_type = 'comment' $date_filter");
            $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_type = 'comment' $date_filter");
            break;
        case 'nav_delete_moderation':
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = '0' AND comment_type = 'comment' $date_filter");
            $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_approved = '0' AND comment_type = 'comment' $date_filter");
            break;
        case 'nav_delete_approved':
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = '1' AND comment_type = 'comment' $date_filter");
            $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_approved = '1' AND comment_type = 'comment' $date_filter");
            break;
        case 'nav_delete_spam':
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = 'spam' AND comment_type = 'comment' $date_filter");
            $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_approved = 'spam' AND comment_type = 'comment' $date_filter");
            break;
        case 'nav_delete_trash':
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = 'trash' AND comment_type = 'comment' $date_filter");
            $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_approved = 'trash' AND comment_type = 'comment' $date_filter");
            break;
    }

    if ($count > 0) {
        nav_update_deleted_comments_count($count);
        nav_clear_comment_caches();
    }

    return $count;
}
}
 

// Function to clear all comment caches and update counts
function nav_clear_comment_caches() {
    global $wpdb;
    
    try {
        // Clear WordPress comment caches
        delete_transient('wp_count_comments');
        wp_cache_delete('comments-0', 'counts');
        clean_comment_cache(0);
        
        // Clear post comment counts
        $wpdb->query("UPDATE $wpdb->posts SET comment_count = 0");
        
        // Clear object cache
        wp_cache_flush();
        
        // Force WordPress to recount comments
        wp_defer_comment_counting(false);
        if (function_exists('_update_comment_count_on_transition_post_status')) {
            _update_comment_count_on_transition_post_status(null, null, null);
        }
        wp_defer_comment_counting(true);
        
        // Clear any custom transients
        delete_transient('nav_comments_count');
        delete_transient('nav_remaining_comments');
        
        // Clear post meta caches
        $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '%_comment_count%'");
        
        // Clear comment meta caches
        $wpdb->query("DELETE FROM $wpdb->commentmeta WHERE meta_key LIKE '%_comment_count%'");
        
    } catch (Exception $e) {
        // Log error if needed
        error_log('Error clearing comment caches: ' . $e->getMessage());
    }
}

// Function to get total comment count
function nav_get_total_comments() {
    global $wpdb;
    try {
        // Get fresh count directly from database
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_type = 'comment'");
        
        // Force update the comment count cache
        wp_cache_delete('comments-0', 'counts');
        clean_comment_cache(0);
        
        // Update the comment count transient
        set_transient('nav_comments_count', $count, HOUR_IN_SECONDS);
        
        return (int)$count;
    } catch (Exception $e) {
        error_log('Error getting total comments: ' . $e->getMessage());
        return 0;
    }
}

// Function to check if user can delete more comments
function nav_can_delete_more_comments() {
    if (nonu_fs()->is_paying()) {
        return true;
    }
    
    $total_comments = nav_get_total_comments();
    return $total_comments <= 500;
}

// Function to get remaining free comments
function nav_get_remaining_free_comments() {
    try {
        $total_comments = nav_get_total_comments();
        $deleted_comments = get_option('nav_deleted_comments_count', 0);
        $remaining = 500 - $deleted_comments;
        return max(0, $remaining);
    } catch (Exception $e) {
        return 0;
    }
}

// Add function to track deleted comments
function nav_update_deleted_comments_count($count) {
    $current_count = get_option('nav_deleted_comments_count', 0);
    update_option('nav_deleted_comments_count', $current_count + $count);
}

// Function to show upgrade notice
function nav_show_upgrade_notice() {
    ?>
    <div class="notice notice-warning">
        <p>
            <strong>Free Plan Limit Reached!</strong> You can only delete up to 500 comments in the free plan. 
            <a href="<?php echo nonu_fs()->get_upgrade_url(); ?>" class="button button-primary">Upgrade to Premium</a>
            to delete unlimited comments and get additional features like date range selection!
        </p>
    </div>
    <?php
}

// Function to format date for database
function nav_format_date_for_db($date) {
    return date('Y-m-d 00:00:00', strtotime($date));
}

// Function to get comment count with date range
function nav_get_comments_count_with_date($date_from = '', $date_to = '') {
    global $wpdb;
    try {
        $where_clause = "WHERE comment_type = 'comment'";
        if ($date_from && $date_to) {
            $date_from = nav_format_date_for_db($date_from);
            $date_to = nav_format_date_for_db($date_to);
            $where_clause .= $wpdb->prepare(" AND DATE(comment_date) >= DATE(%s) AND DATE(comment_date) <= DATE(%s)", $date_from, $date_to);
        }
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments $where_clause");
    } catch (Exception $e) {
        return 0;
    }
}

// Function to show no comments message
function nav_show_no_comments_message($type, $date_from = '', $date_to = '') {
    $is_premium = nonu_fs()->is_paying();
    $message = '';
    
    if ($is_premium && $date_from && $date_to) {
        $message = "No comments found between $date_from and $date_to";
    } else {
        switch ($type) {
            case 'nav_delete_all':
                $message = "No comments found to delete";
                break;
            case 'nav_delete_moderation':
                $message = "No moderation comments found";
                break;
            case 'nav_delete_approved':
                $message = "No approved comments found";
                break;
            case 'nav_delete_spam':
                $message = "No spam comments found";
                break;
            case 'nav_delete_trash':
                $message = "No trash comments found";
                break;
        }
    }
    return $message;
}

// Function to show success message
function nav_show_success_message($count, $type, $date_from = '', $date_to = '') {
    $is_premium = nonu_fs()->is_paying();
    $message = '';
    
    if ($is_premium && $date_from && $date_to) {
        $message = "Successfully deleted $count comments between $date_from and $date_to";
    } else {
        switch ($type) {
            case 'nav_delete_all':
                $message = "Successfully deleted $count comments";
                break;
            case 'nav_delete_moderation':
                $message = "Successfully deleted $count moderation comments";
                break;
            case 'nav_delete_approved':
                $message = "Successfully deleted $count approved comments";
                break;
            case 'nav_delete_spam':
                $message = "Successfully deleted $count spam comments";
                break;
            case 'nav_delete_trash':
                $message = "Successfully deleted $count trash comments";
                break;
        }
    }
    return $message;
}

// Function to validate date range
function nav_validate_date_range($date_from, $date_to) {
    $today = date('Y-m-d');
    $errors = array();
    
    if (!empty($date_from) && !empty($date_to)) {
        if ($date_from > $date_to) {
            $errors[] = "Start date cannot be later than end date";
        }
        if ($date_from > $today || $date_to > $today) {
            $errors[] = "Cannot select future dates";
        }
    }
    return $errors;
}

// Function to format date for display
function nav_format_date($date) {
    return date('Y-m-d', strtotime($date));
}

// Function to display debug logs
function nav_display_debug_log() {
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file)) {
        $logs = file_get_contents($log_file);
        if (!empty($logs)) {
            ?>
            <div class="postbox seedprod-postbox">
                <div class="handlediv" title="Click to toggle"><br></div>
                <h3 style="color:red;">Debug Log</h3>
                <div class="inside">
                    <pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">
                        <?php echo esc_html($logs); ?>
                    </pre>
                </div>
            </div>
            <?php
        }
    }
}

// Function to show alert message
function nav_show_alert($type, $message, $buttons = array()) {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if (typeof swal !== "undefined") {
            swal({
                title: "<?php echo esc_js($type); ?>",
                text: "<?php echo esc_js($message); ?>",
                icon: "<?php echo esc_js($type === 'Success!' ? 'success' : ($type === 'Error!' ? 'error' : 'info')); ?>",
                <?php if (!empty($buttons)): ?>
                buttons: <?php echo json_encode($buttons); ?>,
                <?php endif; ?>
            }).then((value) => {
                <?php if (isset($buttons['upgrade'])): ?>
                if (value === "upgrade") {
                    window.location.href = "<?php echo esc_js(nonu_fs()->get_upgrade_url()); ?>";
                }
                <?php endif; ?>
                window.location.reload();
            });
        } else {
            alert("<?php echo esc_js($message); ?>");
            window.location.reload();
        }
    });
    </script>
    <?php
}

// Add this new function after the existing functions but before the main delete function
function nav_export_comments($type = 'all', $date_from = '', $date_to = '', $limit = 10000, $offset = 0) {
    // Start output buffering
    ob_start();
    
    global $wpdb;
    
    try {
        // Build the WHERE clause based on comment type
        $where_clause = "WHERE comment_type = 'comment'";
        
        switch ($type) {
            case 'moderation':
                $where_clause .= " AND comment_approved = 0";
                break;
            case 'approved':
                $where_clause .= " AND comment_approved = 1";
                break;
            case 'spam':
                $where_clause .= " AND comment_approved = 'spam'";
                break;
            case 'trash':
                $where_clause .= " AND comment_approved = 'trash'";
                break;
        }
        
        // Add date range if provided
        if ($date_from && $date_to) {
            $date_from = nav_format_date_for_db($date_from);
            $date_to = nav_format_date_for_db($date_to);
            $where_clause .= $wpdb->prepare(" AND DATE(comment_date) >= DATE(%s) AND DATE(comment_date) <= DATE(%s)", $date_from, $date_to);
        }
        
        // Add limit and offset for batch processing
        $limit_clause = "";
        if ($limit > 0) {
            $limit_clause = $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }
        
        // Get comments
        $comments = $wpdb->get_results("SELECT * FROM $wpdb->comments $where_clause $limit_clause");
        
        if (empty($comments)) {
            // Clean the buffer before outputting JSON response for no comments
            ob_clean();
            // Using wp_redirect for a better user experience on no comments found during export
            $redirect_url = add_query_arg(array('nav_export_message' => urlencode('No comments found for the selected criteria or batch.'), 'nav_export_status' => 'info'), wp_get_referer());
            wp_redirect($redirect_url);
            exit;
        }
        
        // Prepare CSV data
        $filename = 'comments-export-' . date('Y-m-d') . '-batch-' . ($offset / $limit + 1) . '.csv';
        $csv_data = array();
        
        // Add headers (only for the first batch)
        if ($offset == 0) {
            $csv_data[] = array(
                'Comment ID',
                'Post ID',
                'Author',
                'Email',
                'URL',
                'Comment',
                'Date',
                'Status'
            );
        }
        
        // Add comment data
        foreach ($comments as $comment) {
            $status = '';
            switch ($comment->comment_approved) {
                case '0':
                    $status = 'Pending';
                    break;
                case '1':
                    $status = 'Approved';
                    break;
                case 'spam':
                    $status = 'Spam';
                    break;
                case 'trash':
                    $status = 'Trash';
                    break;
            }
            
            $csv_data[] = array(
                $comment->comment_ID,
                $comment->comment_post_ID,
                $comment->comment_author,
                $comment->comment_author_email,
                $comment->comment_author_url,
               htmlspecialchars(str_replace(array("\r", "\n"), ' ', $comment->comment_content)),
                $comment->comment_date,
                $status
            );
        }
        
        // Generate CSV
        $output = fopen('php://temp', 'r+');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        // Clean the buffer before outputting CSV
        ob_clean();
        
        // Set headers for download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output CSV
        echo $csv;
        exit;
        
    } catch (Exception $e) {
        // Clean the buffer before outputting error response
        ob_clean();
        $redirect_url = add_query_arg(array('nav_export_message' => urlencode('An error occurred during export: ' . $e->getMessage()), 'nav_export_status' => 'error'), wp_get_referer());
        wp_redirect($redirect_url);
        exit;
    }
}

// Register AJAX actions
add_action('wp_ajax_nav_import_comments_ajax', 'nav_handle_import_ajax');
add_action('wp_ajax_nav_export_comments_ajax', 'nav_handle_export_ajax');
add_action('wp_ajax_nav_save_comment_settings', 'nav_ajax_save_comment_settings');
add_action('wp_ajax_nav_delete_comments_ajax', 'nav_handle_delete_ajax');

// Add AJAX handler for delete action
function nav_handle_delete_ajax() {
    
    if (!nonu_fs()->is_paying() && !nav_can_delete_more_comments()) {
    wp_send_json_error(array(
        'message' => 'Free plan limit reached. You can only delete up to 500 comments. Upgrade to Premium for unlimited access.'
    ));
    return;
    }
    
    
    // Check if user has proper permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'message' => 'You do not have sufficient permissions to perform this action.'
        ));
        return;
    }

    // Verify nonce
    if (!isset($_POST['nav@final_delete']) || !wp_verify_nonce($_POST['nav@final_delete'], 'nav@final_delete')) {
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh the page and try again.'
        ));
        return;
    }

    try {
        $count = nav_delete_all_comment();
        if ($count > 0) {
            wp_send_json_success(array(
                'message' => sprintf('Successfully deleted %d comments.', $count)
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'No comments were deleted.'
            ));
        }
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => 'An error occurred while deleting comments: ' . $e->getMessage()
        ));
    }
}

// Modify the main delete function to include tabs
if( !function_exists("nav_delete_comment") )
{
function nav_delete_comment(){
    // Check if user has manage_options capability
    if (!current_user_can('manage_options')) {
        wp_die(__('Sorry, you are not allowed to access this page.'));
    }
    
    global $wpdb;
    
    // Get current tab
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'delete';
    
    // Handle delete action
    if(isset($_POST['nav_delete_comment']) && isset($_POST['nav@final_delete']) && wp_verify_nonce($_POST['nav@final_delete'], 'nav@final_delete')) {
        $count = nav_delete_all_comment();
        if ($count > 0) {
            if (wp_doing_ajax()) {
                wp_send_json_success(array(
                    'message' => sprintf('Successfully deleted %d comments.', $count)
                ));
            } else {
                nav_show_admin_notice(sprintf('Successfully deleted %d comments.', $count), 'success');
            }
        } else {
            if (wp_doing_ajax()) {
                wp_send_json_error(array(
                    'message' => 'No comments were deleted.'
                ));
            } else {
                nav_show_admin_notice('No comments were deleted.', 'info');
            }
        }
    }
    
    // Display messages after export redirect
    if (isset($_GET['nav_export_message']) && isset($_GET['nav_export_status'])) {
        $message = sanitize_text_field(urldecode($_GET['nav_export_message']));
        $status = sanitize_text_field($_GET['nav_export_status']);
        nav_show_admin_notice($message, $status);
    }
    
    try {
        // Get total comments and remaining count first
        $total_comments = nav_get_total_comments();
        $remaining_comments = nav_get_remaining_free_comments();
        
        // Initialize comment counts
        $comments_count = (object) array(
            'moderated' => $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = '0' AND comment_type = 'comment'"),
            'approved' => $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = '1' AND comment_type = 'comment'"),
            'spam' => $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = 'spam' AND comment_type = 'comment'"),
            'trash' => $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = 'trash' AND comment_type = 'comment'")
        );
        
        // Check if user can delete more comments
        if (!nav_can_delete_more_comments()) {
            nav_show_upgrade_notice();
        }
        
        // Check if user is premium
        $is_premium = nonu_fs()->is_paying();
        
        // Get date range if premium user
        $date_from = '';
        $date_to = '';
        $date_errors = array();
        
        if ($is_premium) {
            $date_from = isset($_POST['nav_delete_commentfrom']) ? sanitize_text_field($_POST['nav_delete_commentfrom']) : '';
            $date_to = isset($_POST['nav_delete_commentto']) ? sanitize_text_field($_POST['nav_delete_commentto']) : '';
            
            // Validate date range
            $date_errors = nav_validate_date_range($date_from, $date_to);
            
            // Format dates for display
            if (!empty($date_from)) {
                $date_from = nav_format_date($date_from);
            }
            if (!empty($date_to)) {
                $date_to = nav_format_date($date_to);
            }
        }
        
        // Display the main interface
        ?>
        <div id="wpbody" role="main">
           <div id="wpbody-content" aria-label="Main content" tabindex="0" style="overflow: hidden;">
              <div class="wrap columns-2 seed_wnb">
                 <h2><b>Delete & Disable Comments</b></h2>
                 
                 <!-- Add tabs -->
                <h2 class="nav-tab-wrapper">
    <a href="?page=delete_comment&tab=delete" 
       class="nav-tab <?php echo $current_tab === 'delete' ? 'nav-tab-active' : ''; ?>">
        <span class="dashicons dashicons-trash"></span>
        <?php esc_html_e( 'Delete Comments', 'your-text-domain' ); ?>
    </a>
    <a href="?page=delete_comment&tab=disable" 
       class="nav-tab <?php echo $current_tab === 'disable' ? 'nav-tab-active' : ''; ?>">
        <span class="dashicons dashicons-hidden"></span>
        <?php esc_html_e( 'Disable Comments', 'your-text-domain' ); ?>
    </a>
</h2>
                 
                 <?php if ($current_tab === 'delete'): ?>
                 <!-- Delete Comments Tab Content -->
                
              
               

                 <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                       <div id="post-body-content">
                          <form action="<?php echo admin_url('admin-ajax.php'); ?>" method="post" id="nav-delete-comments-form">
                              <input type="hidden" name="action" value="nav_delete_comments_ajax">
                              <input type="hidden" name="nav@final_delete" value="<?php echo wp_create_nonce('nav@final_delete'); ?>">
                             <div class="nav-comments-section">
                                <div class="nav-comments-header">
                                    <h3>Delete Comments</h3>
                                </div>
                                <div class="nav-comments-content">
                                    <div class="nav-notice danger">
                                        <span class="nav-notice-icon">⚠️</span>
                                        <div class="nav-notice-content">
                                            <div class="nav-notice-title">Warning: Irreversible Action (Deleted comments cannot be recovered. Please make sure to export your comments before deletion if needed. )</div>
                                            <p class="nav-notice-text">  <?php if (!$is_premium): ?> You can delete up to 500 comments. <strong><?php echo $remaining_comments; ?></strong> comments remaining in your free plan.
                             <a href="<?php echo nonu_fs()->get_upgrade_url(); ?>" class="button button-primary" style="margin-left: 10px;">Upgrade to Premium</a>   <?php endif; ?> </p>
                                        </div>
                                    </div>
									
									
									  

                                    <div class="nav-radio-group">
                                        <label>
                                            <input type="radio" name="nav_delete_comment" required <?php if($total_comments <= 0) { echo 'disabled' ;  } ?> value="nav_delete_all">
                                            <strong>All Comments</strong>
                                            <span class="comment-count" style="color: var(--nav-primary); font-weight: bold; margin-left: 8px;">
                                                (<?php echo number_format($total_comments); ?> comments)
                                            </span>
                                            <p class="description">Delete all comments from your website</p>
                                        </label>

                                        <label>
                                            <input type="radio" name="nav_delete_comment" required <?php if($comments_count->moderated <= 0) { echo 'disabled' ;  } ?> value="nav_delete_moderation">
                                            <strong>Comments in Moderation</strong>
                                            <span class="comment-count" style="color: var(--nav-warning); font-weight: bold; margin-left: 8px;">
                                                (<?php echo number_format($comments_count->moderated); ?> comments)
                                            </span>
                                            <p class="description">Delete all comments marked as moderation</p>
                                        </label>

                                        <label>
                                            <input type="radio" name="nav_delete_comment" required <?php if($comments_count->approved <= 0) { echo 'disabled' ;  } ?> value="nav_delete_approved">
                                            <strong>Approved Comments</strong>
                                            <span class="comment-count" style="color: var(--nav-success); font-weight: bold; margin-left: 8px;">
                                                (<?php echo number_format($comments_count->approved); ?> comments)
                                            </span>
                                            <p class="description">Delete all approved comments</p>
                                        </label>

                                        <label>
                                            <input type="radio" name="nav_delete_comment" required <?php if($comments_count->spam <= 0) { echo 'disabled' ;  } ?> value="nav_delete_spam">
                                            <strong>Spam Comments</strong>
                                            <span class="comment-count" style="color: var(--nav-danger); font-weight: bold; margin-left: 8px;">
                                                (<?php echo number_format($comments_count->spam); ?> comments)
                                            </span>
                                            <p class="description">Delete all spam comments</p>
                                        </label>

                                        <label>
                                            <input type="radio" name="nav_delete_comment" required <?php if($comments_count->trash <= 0) { echo 'disabled' ;  } ?> value="nav_delete_trash">
                                            <strong>Trash Comments</strong>
                                            <span class="comment-count" style="color: var(--nav-gray-500); font-weight: bold; margin-left: 8px;">
                                                (<?php echo number_format($comments_count->trash); ?> comments)
                                            </span>
                                            <p class="description">Delete all comments in trash</p>
                                        </label>
                                    </div>

                                    <div class="nav-date-filter">
                                        <div class="nav-date-filter-header">
                                            <span>📅</span>
                                            <div>
                                                <h4>Date Range Filter</h4>
                                                <p>Select a date range to delete comments from a specific period</p>
                                            </div>
                                        </div>
                                        <div class="nav-date-inputs">
                                            <div class="nav-date-input-group">
                                                <label>From Date</label>
                                                <input type="date" name="nav_delete_commentfrom" value="<?php echo esc_attr($date_from); ?>" <?php if (!nonu_fs()->is_paying()) echo 'disabled'; ?>>
                                            </div>
                                            <div class="nav-date-input-group">
                                                <label>To Date</label>
                                                <input type="date" name="nav_delete_commentto" value="<?php echo esc_attr($date_to); ?>" <?php if (!nonu_fs()->is_paying()) echo 'disabled'; ?>>
                                            </div>
                                        </div>
                                        <?php if (!empty($date_errors)): ?>
                                        <div class="nav-date-error" style="color: #ef4444; margin-top: 10px; padding: 10px; background: #fee2e2; border-radius: 4px;">
                                            <strong>Date Range Error:</strong>
                                            <ul style="margin: 5px 0 0 20px;">
                                                <?php foreach ($date_errors as $error): ?>
                                                    <li><?php echo esc_html($error); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!nonu_fs()->is_paying()): ?>
                                        <div class="nav-date-premium">
                                            <span>Date range filtering is available in the premium plan</span>
                                            <a href="<?php echo nonu_fs()->get_upgrade_url(); ?>" class="button button-primary">Upgrade</a>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="nav-submit-button">
                                        
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <button type="submit" class="button button-primary" style="background: var(--nav-danger); border-color: var(--nav-danger);">
                                                Delete Selected Comments
                                            </button>
											 <?php if (nonu_fs()->is_paying()): ?>
											<button type="button" id="nav-export-comments" class="button button-secondary" title="Export your comments as CSV">
												Export Comments
											</button>
										<?php else: ?>
											<button type="button" class="button button-secondary" disabled title="Available in Premium version">
												Export Comments (Premium)
											</button>
										<?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                             </div>
                          </form>

                          <div class="nav-comments-section">
                             <div class="nav-comments-header">
                                <h3>Automatic Spam Cleanup</h3>
                             </div>
                             <div class="nav-comments-content">
                                <form method="post" action="">
                                    <?php wp_nonce_field('nav_auto_delete_settings', 'nav_auto_delete_nonce'); ?>
                                    <div class="nav-notice info">
                                        <span class="nav-notice-icon">ℹ️</span>
                                        <div class="nav-notice-content">
                                            <div class="nav-notice-title">Premium Feature</div>
                                            <p class="nav-notice-text">Automatically clean up spam comments on a schedule.</p>
                                        </div>
                                    </div>

                                    <div class="nav-form-group">
																			<label class="nav-form-label">
									  Auto-delete Spam: <br>
									  <small>
										(After scheduling, if the "Next Cleanup Date" is not visible, please perform a hard refresh: 
										<strong>Ctrl + Shift + R</strong> on Windows or <strong>Cmd + R</strong> on Mac.)
									  </small>
									</label>
                                        <select name="nav_auto_delete_spam" id="nav_auto_delete_spam" class="nav-form-select" <?php if (!$is_premium) echo 'disabled'; ?>>
                                            <option value="disabled" <?php selected(get_option('nav_auto_delete_spam'), 'disabled'); ?>>Disabled</option>
                                            <option value="daily" <?php selected(get_option('nav_auto_delete_spam'), 'daily'); ?>>Daily</option>
                                            <option value="weekly" <?php selected(get_option('nav_auto_delete_spam'), 'weekly'); ?>>Weekly</option>
                                            <option value="monthly" <?php selected(get_option('nav_auto_delete_spam'), 'monthly'); ?>>Monthly</option>
                                        </select>
                                        <?php if ($is_premium): ?>
                                        <button type="submit" name="nav_schedule_cleanup" class="button button-primary">Schedule Now</button>
                                        <button type="submit" name="nav_cancel_cleanup" class="button button-secondary">Cancel Schedule</button>
                                        <?php else: ?>
                                        <button type="button" class="button button-primary" disabled>Schedule Now</button>
                                        <button type="button" class="button button-secondary" disabled>Cancel Schedule</button>
                                        <?php endif; ?>
                                    </div>

                                    <div class="nav-stats-grid">
                                        <div class="nav-stat-box">
                                            <div class="nav-stat-label">Last Cleanup:</div>
                                            <div class="nav-stat-value">
                                                <?php 
                                                $last_cleanup = get_option('nav_last_spam_cleanup');
                                                echo $last_cleanup ? date('F j, Y g:i a', strtotime($last_cleanup)) : 'Never';
                                                ?>
                                            </div>
                                        </div>
                                        <div class="nav-stat-box">
                                            <div class="nav-stat-label">Next Cleanup:</div>
                                            <div class="nav-stat-value">
                                                <?php  
                                                $schedule = get_option('nav_auto_delete_spam');
                                                if ($schedule && $schedule !== 'disabled') {
                                                    $next_run = wp_next_scheduled('nav_auto_delete_spam_event');
                                                    echo $next_run ? date('F j, Y g:i a', $next_run) : 'Not scheduled';
                                                } else {
                                                    echo 'Not scheduled';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                             </div>
                          </div>
                       </div>

                       <div id="postbox-container-1" class="postbox-container">
                          <div id="side-sortables" class="meta-box-sortables ui-sortable">
							  
							    <?php if (!$is_premium): ?>
                             <div class="postbox support-postbox" style="background-color: #fcf8e3">
                                <div class="handlediv" title="Click to toggle"><br></div>
                                
                              <div class="inside">
    <div style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <h4 style="color: #2271b1; margin-top: 0; font-size: 20px; text-align: center;">🐾 Pawcause – 100% goes to Dog Charity</h4>

        <div style="background: #2271b1; color: white; padding: 15px; border-radius: 6px; text-align: center; margin: 15px 0;">
            <div style="font-size: 28px; font-weight: bold;">$0.83</div>
            <div style="font-size: 14px; opacity: 0.9;">🐶 100% of your upgrade goes directly to supporting dog charities.</div>
        </div>

        <ul style="list-style: none; padding-left: 0; margin: 20px 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee; padding: 15px 0;">
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">🚀</span>
                <span><strong>Unlimited Comments</strong> - Delete as many comments as you need</span>
            </li>
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">📅</span>
                <span><strong>Smart Date Filtering</strong> - Delete comments by date range</span>
            </li>
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">📊</span>
                <span><strong>Export to CSV</strong> - Backup your comments before deletion</span>
            </li>
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">🛑</span>
                <span><strong>Disable Comments</strong> - Turn off comments globally or by post type</span>
            </li>
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">👥</span>
                <span><strong>Role-Based Exclusions</strong> - Allow specific roles to bypass restrictions</span>
            </li>
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">⭐</span>
                <span><strong>Priority Support</strong> - Get help when you need it</span>
            </li>
            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                <span style="color: #2271b1; margin-right: 10px;">🔄</span>
                <span><strong>Auto Spam Cleanup</strong> - Automatically remove spam comments</span>
            </li>
        </ul>

        <div style="text-align: center;">
            <a href="<?php echo nonu_fs()->get_upgrade_url(); ?>" class="button button-primary" style="font-size: 12px; padding: 8px 25px; background-color: #2271b1; border-color: #2271b1; width: 100%;">
                Upgrade Now - Get Premium Access
            </a>
            <p style="color: #666; font-size: 12px; margin-top: 10px; text-align: center;">
                <span style="color: #2271b1;">✓</span> 30-day money-back guarantee
            </p>
        </div>
    </div>
</div>
                             </div>
                             <?php endif; ?>
							  
							  
                            
                                <div class="nav-comments-section">
                                <div class="nav-comments-header">
                                    <h3>Comments Overview</h3>
                                </div>
                                <div class="nav-comments-content">
                                    <div class="nav-stats-grid">
                                        <div class="nav-stat-box">
                                            <div class="nav-stat-label">Total Comments:</div>
                                            <div class="nav-stat-value" style="color: var(--nav-primary);">
                                                <?php echo number_format($total_comments); ?>
                                            </div>
                                        </div>
                                        <div class="nav-stat-box">
                                            <div class="nav-stat-label">In Moderation:</div>
                                            <div class="nav-stat-value" style="color: var(--nav-warning);">
                                                <?php echo number_format($comments_count->moderated); ?>
                                            </div>
                                        </div>
                                        <div class="nav-stat-box">
                                            <div class="nav-stat-label">Approved:</div>
                                            <div class="nav-stat-value" style="color: var(--nav-success);">
                                                <?php echo number_format($comments_count->approved); ?>
                                            </div>
                                        </div>
                                        <div class="nav-stat-box">
                                            <div class="nav-stat-label">Spam:</div>
                                            <div class="nav-stat-value" style="color: var(--nav-danger);">
                                                <?php echo number_format($comments_count->spam); ?>
                                            </div>
                                        </div>
                                        <div class="nav-stat-box">
                                            <div class="nav-stat-label">In Trash:</div>
                                            <div class="nav-stat-value" style="color: var(--nav-gray-500);">
                                                <?php echo number_format($comments_count->trash); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                             </div>
                             </div>
                          </div>
                       </div>
                   
                 <?php else: ?>
                 <!-- Disable Comments Tab Content -->
                 <?php nav_disable_comments_settings(); ?>
                 <?php endif; ?>
                 
              </div>
           </div>
        </div>
        <?php
    } catch (Exception $e) {
        nav_show_admin_notice('An error occurred while processing your request. Please try again later.', 'error');
    }
}
}

// Add this after the existing functions but before the main delete function
function nav_schedule_spam_cleanup() {
    // Clear any existing schedule first
    wp_clear_scheduled_hook('nav_auto_delete_spam_event');
    
    // Get the current schedule
    $schedule = get_option('nav_auto_delete_spam');
    
    // Only schedule if not disabled
    if ($schedule && $schedule !== 'disabled') {
        // Schedule based on frequency
        switch ($schedule) {
            case 'daily':
                wp_schedule_event(time(), 'daily', 'nav_auto_delete_spam_event');
                break;
            case 'weekly':
                wp_schedule_event(time(), 'weekly', 'nav_auto_delete_spam_event');
                break;
            case 'monthly':
                wp_schedule_event(time(), 'monthly', 'nav_auto_delete_spam_event');
                break;
        }
    }
}
add_action('wp', 'nav_schedule_spam_cleanup');

// Add custom cron schedules
function nav_add_cron_schedules($schedules) {
    $schedules['weekly'] = array(
        'interval' => 7 * DAY_IN_SECONDS,
        'display' => __('Once Weekly')
    );
    $schedules['monthly'] = array(
        'interval' => 30 * DAY_IN_SECONDS,
        'display' => __('Once Monthly')
    );
    return $schedules;
}
add_filter('cron_schedules', 'nav_add_cron_schedules');

function nav_auto_delete_spam_comments() {
    global $wpdb;
    
    // Check if auto-delete is enabled
    $schedule = get_option('nav_auto_delete_spam');
    if ($schedule === 'disabled') {
        return;
    }
    
    // Get the last cleanup time
    $last_cleanup = get_option('nav_last_spam_cleanup');
    $current_time = current_time('timestamp');
    
    // Check if it's time to run based on schedule
    $should_run = false;
    if ($schedule === 'daily' && (!$last_cleanup || (strtotime($last_cleanup) + DAY_IN_SECONDS) <= $current_time)) {
        $should_run = true;
    } elseif ($schedule === 'weekly' && (!$last_cleanup || (strtotime($last_cleanup) + WEEK_IN_SECONDS) <= $current_time)) {
        $should_run = true;
    } elseif ($schedule === 'monthly' && (!$last_cleanup || (strtotime($last_cleanup) + MONTH_IN_SECONDS) <= $current_time)) {
        $should_run = true;
    }
    
    if ($should_run) {
        // Delete spam comments
        $deleted = $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_approved = 'spam'");
        
        // Update last cleanup time
        update_option('nav_last_spam_cleanup', current_time('mysql'));
        
        // Clear comment caches
        nav_clear_comment_caches();
        
        // Log the cleanup
        if ($deleted !== false) {
            error_log("Nav Comments Plugin: Automatically deleted $deleted spam comments on " . current_time('mysql'));
        }
    }
}
add_action('nav_auto_delete_spam_event', 'nav_auto_delete_spam_comments');

// Update the handle_auto_delete_settings function
function nav_handle_auto_delete_settings() {
    if (isset($_POST['nav_auto_delete_spam']) && nonu_fs()->is_paying()) {
        check_admin_referer('nav_auto_delete_settings', 'nav_auto_delete_nonce');
        
        if (isset($_POST['nav_cancel_cleanup'])) {
            // Cancel the schedule
            wp_clear_scheduled_hook('nav_auto_delete_spam_event');
            update_option('nav_auto_delete_spam', 'disabled');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Automatic spam cleanup schedule has been cancelled.</p></div>';
            });
        } else {
            // Update schedule
            $schedule = sanitize_text_field($_POST['nav_auto_delete_spam']);
            update_option('nav_auto_delete_spam', $schedule);
            
            // Clear existing schedule
            wp_clear_scheduled_hook('nav_auto_delete_spam_event');
            
            // Reschedule if not disabled
            if ($schedule !== 'disabled') {
                nav_schedule_spam_cleanup();
                add_action('admin_notices', function() use ($schedule) {
                    echo '<div class="notice notice-success is-dismissible"><p>Automatic spam cleanup has been scheduled to run ' . $schedule . '.</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>Automatic spam cleanup has been disabled.</p></div>';
                });
            }
        }
    }
}
add_action('admin_init', 'nav_handle_auto_delete_settings');

// Update the menu registration
function nav_add_admin_menu() {
    add_management_page(
        'Delete All Comments',
        'Delete Comments',
        'manage_options',
        'delete_comment',
        'nav_delete_comment'
    );
}
add_action('admin_menu', 'nav_add_admin_menu');

// Add AJAX handler for import
function nav_handle_import_ajax() {
    // Check if user has proper permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'message' => 'You do not have sufficient permissions to perform this action.'
        ));
        return;
    }

    // Verify nonce
    if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'nav_comments_import_nonce')) {
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh the page and try again.'
        ));
        return;
    }

    try {
        // Check if user is premium
        if (!nonu_fs()->is_paying()) {
            throw new Exception('Import feature is only available in the premium version');
        }

        // Check if file was uploaded
        if (!isset($_FILES['import_file'])) {
            throw new Exception('No file was uploaded');
        }

        if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $_FILES['import_file']['error']);
        }

        // Validate file type
        $file_type = wp_check_filetype($_FILES['import_file']['name']);
        if ($file_type['ext'] !== 'csv') {
            throw new Exception('Invalid file type. Please upload a CSV file.');
        }

        $file = $_FILES['import_file']['tmp_name'];
        $handle = fopen($file, 'r');

        if ($handle === false) {
            throw new Exception('Could not open the uploaded file');
        }

        // Get comment type
        $comment_type = isset($_POST['nav_import_comment_type']) ? sanitize_text_field($_POST['nav_import_comment_type']) : 'comment';

        // Read and validate header row
        $header = fgetcsv($handle);
        if (!$header || count($header) < 8) {
            fclose($handle);
            throw new Exception('Invalid CSV format. The file must contain: Comment ID, Post ID, Author, Email, URL, Comment, Date, and Status columns.');
        }

        $imported = 0;
        $skipped = 0;
        $errors = array();

        // Process all rows
        while (($data = fgetcsv($handle)) !== false) {
            try {
                if (count($data) < 8) {
                    $skipped++;
                    $errors[] = "Invalid data format";
                    continue;
                }

                list($comment_id, $post_id, $author, $email, $url, $content, $date, $status) = $data;

                // Validate post exists
                if (!get_post($post_id)) {
                    $skipped++;
                    $errors[] = "Post ID $post_id does not exist";
                    continue;
                }

                // Validate email
                if (!is_email($email)) {
                    $skipped++;
                    $errors[] = "Invalid email format";
                    continue;
                }

                // Convert status text to WordPress format
                $comment_approved = '0'; // Default to pending
                switch (strtolower(trim($status))) {
                    case 'approved':
                    case '1':
                        $comment_approved = '1';
                        break;
                    case 'pending':
                    case '0':
                        $comment_approved = '0';
                        break;
                    case 'spam':
                        $comment_approved = 'spam';
                        break;
                    case 'trash':
                        $comment_approved = 'trash';
                        break;
                }

					$author = sanitize_text_field($author);
					$email = sanitize_email($email);
					$url = esc_url_raw($url);
					$content = wp_kses_post($content);
				
				
                // Prepare comment data
                $commentdata = array(
                    'comment_post_ID' => $post_id,
                    'comment_author' => $author,
                    'comment_author_email' => $email,
                    'comment_author_url' => $url,
                    'comment_content' => $content,
                    'comment_date' => $date,
                    'comment_approved' => $comment_approved,
                    'comment_type' => $comment_type
                );

                // Insert comment
                $result = wp_insert_comment($commentdata);
                if ($result) {
                    $imported++;
                } else {
                    $skipped++;
                }
            } catch (Exception $row_error) {
                $skipped++;
                $errors[] = $row_error->getMessage();
            }
        }

        fclose($handle);

        // Prepare response message
        $message = sprintf('Successfully imported %d comments. %d comments were skipped.', $imported, $skipped);
        if (!empty($errors)) {
            $message .= "\nErrors:\n" . implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "\n... and " . (count($errors) - 5) . " more errors";
            }
        }

        wp_send_json_success(array(
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $message
        ));

    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}

// Add AJAX handler for export
function nav_handle_export_ajax() {
    try {
        // Verify nonce
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'nav_comments_export_nonce')) {
            throw new Exception('Security check failed');
        }

        // Check if user is premium
        if (!nonu_fs()->is_paying()) {
            throw new Exception('Export feature is only available in the premium version');
        }

        // Get parameters
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 1000;

        global $wpdb;
        $where_clause = "WHERE comment_type = 'comment'";

        // Add type filter
        switch ($type) {
            case 'moderation':
                $where_clause .= " AND comment_approved = '0'";
                break;
            case 'approved':
                $where_clause .= " AND comment_approved = '1'";
                break;
            case 'spam':
                $where_clause .= " AND comment_approved = 'spam'";
                break;
            case 'trash':
                $where_clause .= " AND comment_approved = 'trash'";
                break;
        }

        // Add date range if provided
        if ($date_from && $date_to) {
            $date_from = nav_format_date_for_db($date_from);
            $date_to = nav_format_date_for_db($date_to);
            $where_clause .= $wpdb->prepare(" AND DATE(comment_date) >= DATE(%s) AND DATE(comment_date) <= DATE(%s)", $date_from, $date_to);
        }

        // Get total count
        $total_comments = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments $where_clause");

        if ($total_comments == 0) {
            throw new Exception('No comments found for the selected criteria');
        }

        // Get current batch of comments
        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $wpdb->comments $where_clause ORDER BY comment_date DESC LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        ));

        if (empty($comments)) {
            throw new Exception('No comments found in this batch');
        }

        $csv_data = array();

        // Add headers only for first batch
        if ($offset == 0) {
            $csv_data[] = array(
                'Comment ID',
                'Post ID',
                'Author',
                'Email',
                'URL',
                'Comment',
                'Date',
                'Status'
            );
        }

        foreach ($comments as $comment) {
            $status = '';
            switch ($comment->comment_approved) {
                case '0':
                    $status = 'Pending';
                    break;
                case '1':
                    $status = 'Approved';
                    break;
                case 'spam':
                    $status = 'Spam';
                    break;
                case 'trash':
                    $status = 'Trash';
                    break;
            }

            $csv_data[] = array(
                $comment->comment_ID,
                $comment->comment_post_ID,
                $comment->comment_author,
                $comment->comment_author_email,
                $comment->comment_author_url,
                htmlspecialchars(str_replace(array("\r", "\n"), ' ', $comment->comment_content)),
                $comment->comment_date,
                $status
            );
        }

        $processed = $offset + count($comments);
        $progress = round(($processed / $total_comments) * 100);

        wp_send_json_success(array(
            'progress' => $progress,
            'processed' => $processed,
            'total' => $total_comments,
            'csv_data' => $csv_data,
            'filename' => 'comments-export-' . date('Y-m-d') . '.csv',
            'is_complete' => $processed >= $total_comments
        ));

    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
?>
