<?php
    /*
    Plugin Name: Delete All Comments of wordpress
    Plugin URI: http://www.navneetsoni.com/plugins/delete-comments
    Description: Plugin to delete all comments of wordpress website (Approved, Pending, Spam)
    Author: Navneet Soni
    Version: 6.0
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
	

add_action( 'admin_enqueue_scripts', 'my_admin_scripts_nav' );




add_action( 'admin_menu', 'nav_delete_all_comment' );
define( 'NAV_COMENT_PLUGIN_URI', plugin_dir_url( __FILE__ ) );

// Create WordPress admin menu

function my_admin_scripts_nav($hook) {
    // Only load on our plugin page
    if($hook != 'tools_page_delete_comment') {
        return;
    }
    
    // Enqueue jQuery first
    wp_enqueue_script('jquery');
    
    // Enqueue SweetAlert CSS and JS
    wp_enqueue_style('sweetalert-css', NAV_COMENT_PLUGIN_URI . '/include/sweetalert.css', array(), '1.0');
    wp_enqueue_script('sweetalert-js', NAV_COMENT_PLUGIN_URI . '/include/sweetalert.min.js', array('jquery'), '1.0', true);
    
    // Add inline script for date validation
    wp_add_inline_script('sweetalert-js', '
        jQuery(document).ready(function($) {
            var fromDate = $("#nav_delete_commentfrom");
            var toDate = $("#nav_delete_commentto");
            var today = new Date().toISOString().split("T")[0];
            
            // Set max date to today
            fromDate.attr("max", today);
            toDate.attr("max", today);
            
            // Validate dates on change
            fromDate.on("change", validateDates);
            toDate.on("change", validateDates);
            
            function validateDates() {
                var from = fromDate.val();
                var to = toDate.val();
                
                if (from && to) {
                    if (from > to) {
                        swal("Error!", "Start date cannot be later than end date", "error");
                        // Reset the later date
                        if (fromDate.val() > toDate.val()) {
                            fromDate.val("");
                        } else {
                            toDate.val("");
                        }
                    }
                }
            }
        });
    ');
    
    // Add custom JavaScript for export functionality
    wp_enqueue_script('nav-comments-export', NAV_COMENT_PLUGIN_URI . 'include/nav-comments-export.js', array('jquery'), '1.0.0', true);
    wp_localize_script('nav-comments-export', 'navCommentsExport', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('nav_export_comments_nonce')
    ));
}




if( !function_exists("nav_delete_all_comment") )
{
function nav_delete_all_comment(){

  $page_title = 'Delete All comment !!';
  $menu_title = 'Delete Comments';
   $capability = 'install_plugins';
  $menu_slug  = 'delete_comment';
  $function   = 'nav_delete_comment';

  add_management_page( $page_title,
                 $menu_title,
                 $capability,
                 $menu_slug,
                 $function );
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
    } catch (Exception $e) {
        // No logging needed for this function
    }
}

// Function to get total comment count
function nav_get_total_comments() {
    global $wpdb;
    try {
        // Clear cache first
        nav_clear_comment_caches();
        
        // Get fresh count
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_type = 'comment'");
    } catch (Exception $e) {
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
        $remaining = 500 - $total_comments;
        return max(0, $remaining);
    } catch (Exception $e) {
        return 0;
    }
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
                $comment->comment_content,
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

// Add AJAX handler for export
add_action('wp_ajax_nav_export_comments_ajax', 'nav_handle_export_ajax');
function nav_handle_export_ajax() {
    check_ajax_referer('nav_export_comments_nonce', 'nonce');
    
    if (!nonu_fs()->is_paying()) {
        wp_send_json_error('Export feature is only available in the premium version.');
    }
    
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $batch_size = 1000;
    
    try {
        global $wpdb;
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
        
        if ($date_from && $date_to) {
            $date_from = nav_format_date_for_db($date_from);
            $date_to = nav_format_date_for_db($date_to);
            $where_clause .= $wpdb->prepare(" AND DATE(comment_date) >= DATE(%s) AND DATE(comment_date) <= DATE(%s)", $date_from, $date_to);
        }
        
        // Get total count
        $total_comments = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments $where_clause");
        
        if ($total_comments == 0) {
            wp_send_json_error('No comments found for the selected criteria.');
        }
        
        // Get current batch of comments
        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $wpdb->comments $where_clause LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        ));
        
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
                $comment->comment_content,
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
        wp_send_json_error('An error occurred during export: ' . $e->getMessage());
    }
}

// Modify the main delete function to handle messages after redirect
if( !function_exists("nav_delete_comment") )
{
function nav_delete_comment(){
    global $wpdb;
    
    // Display messages after export redirect
    if (isset($_GET['nav_export_message']) && isset($_GET['nav_export_status'])) {
        $message = sanitize_text_field(urldecode($_GET['nav_export_message']));
        $status = sanitize_text_field($_GET['nav_export_status']);
        ?>
        <div class="notice notice-<?php echo esc_attr($status); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
    
    try {
        // Handle delete request (export is handled by nav_handle_export_action)
        if(isset($_POST['nav_delete_comment']) && !isset($_POST['nav_export_comments'])) {
            $favcolor_nav = $_POST['nav_delete_comment'];
        } else {
            $favcolor_nav = "";
        }
        
        // Get total comments and remaining count first
        $total_comments = nav_get_total_comments();
        $remaining_comments = nav_get_remaining_free_comments();
        
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
        
        // Build the WHERE clause based on user type and date range
        $where_clause = "WHERE comment_type = 'comment'";
        if ($is_premium && empty($date_errors) && $date_from && $date_to) {
            $date_from_db = nav_format_date_for_db($date_from);
            $date_to_db = nav_format_date_for_db($date_to);
            $where_clause .= $wpdb->prepare(" AND DATE(comment_date) >= DATE(%s) AND DATE(comment_date) <= DATE(%s)", $date_from_db, $date_to_db);
        }
        
        // Add limit for free users
        if (!$is_premium) {
            $where_clause .= " LIMIT 500";
        }
        
        switch ($favcolor_nav) {
            case "nav_delete_all":
                if(wp_verify_nonce($_POST['nav@final_delete'], 'nav@final_delete')) {
                    // Show date validation errors if any
                    if (!empty($date_errors)) {
                        nav_show_alert('Error!', implode('\n', $date_errors));
                        break;
                    }
                    
                    // Get count before deletion
                    $count_before = nav_get_comments_count_with_date($date_from, $date_to);
                    
                    // Check if there are any comments to delete
                    if ($count_before <= 0) {
                        $message = nav_show_no_comments_message($favcolor_nav, $date_from, $date_to);
                        nav_show_alert('Info', $message);
                        break;
                    }
                    
                    // Proceed with deletion only if there are comments
                    $delete_result = $wpdb->query("DELETE FROM $wpdb->comments $where_clause");
                    
                    if($delete_result !== FALSE){
                        // Clear all caches and update counts
                        nav_clear_comment_caches();
                        
                        // Update remaining comments count
                        $remaining_comments = nav_get_remaining_free_comments();
                        
                        $message = nav_show_success_message($count_before, $favcolor_nav, $date_from, $date_to);
                        $buttons = array('close' => 'Close');
                        if (!$is_premium) {
                            $buttons['upgrade'] = 'Upgrade to Premium';
                        }
                        nav_show_alert('Success!', $message, $buttons);
                    } else {
                        nav_show_alert('Error!', 'An error occurred while deleting comments. Please try again.');
                    }
                } else {
                    die("Security Validation Failure");
                }
                break;
            
            case "nav_delete_moderation":
                if(wp_verify_nonce($_POST['nav@final_delete'], 'nav@final_delete')) {
                    // Build WHERE clause for moderation comments
                    $mod_where = "WHERE comment_approved = 0";
                    if ($is_premium && empty($date_errors) && $date_from && $date_to) {
                        $date_from_db = nav_format_date_for_db($date_from);
                        $date_to_db = nav_format_date_for_db($date_to);
                        $mod_where .= $wpdb->prepare(" AND DATE(comment_date) >= DATE(%s) AND DATE(comment_date) <= DATE(%s)", $date_from_db, $date_to_db);
                    }
                    
                    // Get count before deletion
                    $count_before = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments $mod_where");
                    
                    if($count_before > 0) {
                        $delete_result = $wpdb->query("DELETE FROM $wpdb->comments $mod_where");
                        if($delete_result !== FALSE) {
                            $wpdb->query("OPTIMIZE TABLE $wpdb->comments");
                            if (get_option('_transient_as_comment_count') !== false) {
                                update_option('_transient_as_comment_count', "");
                            }
                            
                            $message = nav_show_success_message($count_before, $favcolor_nav, $date_from, $date_to);
                            nav_show_alert('Success!', $message);
                        } else {
                            nav_show_alert('Error!', 'An error occurred while deleting comments. Please try again.');
                        }
                    } else {
                        $message = nav_show_no_comments_message($favcolor_nav, $date_from, $date_to);
                        nav_show_alert('Info', $message);
                    }
                } else {
                    die("Security Validation Failure");
                }
                break;

            case "nav_delete_approved":
                if(wp_verify_nonce($_POST['nav@final_delete'], 'nav@final_delete')) {
                    // Build WHERE clause for approved comments
                    $approved_where = "WHERE comment_approved = 1";
                    if ($is_premium && empty($date_errors) && $date_from && $date_to) {
                        $date_from_db = nav_format_date_for_db($date_from);
                        $date_to_db = nav_format_date_for_db($date_to);
                        $approved_where .= $wpdb->prepare(" AND DATE(comment_date) >= DATE(%s) AND DATE(comment_date) <= DATE(%s)", $date_from_db, $date_to_db);
                    }
                    
                    // Get count before deletion
                    $count_before = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments $approved_where");
                    
                    if($count_before > 0) {
                        $delete_result = $wpdb->query("DELETE FROM $wpdb->comments $approved_where");
                        if($delete_result !== FALSE) {
                            $wpdb->query("Update $wpdb->posts set comment_count = 0 where post_author != 0");
                            $wpdb->query("OPTIMIZE TABLE $wpdb->comments");
                            if (get_option('_transient_as_comment_count') !== false) {
                                update_option('_transient_as_comment_count', "");
                            }
                            
                            $message = nav_show_success_message($count_before, $favcolor_nav, $date_from, $date_to);
                            nav_show_alert('Success!', $message);
                        } else {
                            nav_show_alert('Error!', 'An error occurred while deleting comments. Please try again.');
                        }
                    } else {
                        $message = nav_show_no_comments_message($favcolor_nav, $date_from, $date_to);
                        nav_show_alert('Info', $message);
                    }
                } else {
                    die("Security Validation Failure");
                }
                break;

            case "nav_delete_spam":
                if(wp_verify_nonce($_POST['nav@final_delete'], 'nav@final_delete')) {
                    // Build WHERE clause for spam comments
                    $spam_where = "WHERE comment_approved = 'spam'";
                    if ($is_premium && empty($date_errors) && $date_from && $date_to) {
                        $date_from_db = nav_format_date_for_db($date_from);
                        $date_to_db = nav_format_date_for_db($date_to);
                        $spam_where .= $wpdb->prepare(" AND DATE(comment_date) >= DATE(%s) AND DATE(comment_date) <= DATE(%s)", $date_from_db, $date_to_db);
                    }
                    
                    // Get count before deletion
                    $count_before = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments $spam_where");
                    
                    if($count_before > 0) {
                        $delete_result = $wpdb->query("DELETE FROM $wpdb->comments $spam_where");
                        if($delete_result !== FALSE) {
                            $wpdb->query("Update $wpdb->posts set comment_count = 0 where post_author != 0");
                            $wpdb->query("OPTIMIZE TABLE $wpdb->comments");
                            if (get_option('_transient_as_comment_count') !== false) {
                                update_option('_transient_as_comment_count', "");
                            }
                            
                            $message = nav_show_success_message($count_before, $favcolor_nav, $date_from, $date_to);
                            nav_show_alert('Success!', $message);
                        } else {
                            nav_show_alert('Error!', 'An error occurred while deleting comments. Please try again.');
                        }
                    } else {
                        $message = nav_show_no_comments_message($favcolor_nav, $date_from, $date_to);
                        nav_show_alert('Info', $message);
                    }
                } else {
                    die("Security Validation Failure");
                }
                break;

            case "nav_delete_trash":
                if(wp_verify_nonce($_POST['nav@final_delete'], 'nav@final_delete')) {
                    // Build WHERE clause for trash comments
                    $trash_where = "WHERE comment_approved = 'trash'";
                    if ($is_premium && empty($date_errors) && $date_from && $date_to) {
                        $date_from_db = nav_format_date_for_db($date_from);
                        $date_to_db = nav_format_date_for_db($date_to);
                        $trash_where .= $wpdb->prepare(" AND DATE(comment_date) >= DATE(%s) AND DATE(comment_date) <= DATE(%s)", $date_from_db, $date_to_db);
                    }
                    
                    // Get count before deletion
                    $count_before = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments $trash_where");
                    
                    if($count_before > 0) {
                        $delete_result = $wpdb->query("DELETE FROM $wpdb->comments $trash_where");
                        if($delete_result !== FALSE) {
                            $wpdb->query("Update $wpdb->posts set comment_count = 0 where post_author != 0");
                            $wpdb->query("OPTIMIZE TABLE $wpdb->comments");
                            if (get_option('_transient_as_comment_count') !== false) {
                                update_option('_transient_as_comment_count', "");
                            }
                            
                            $message = nav_show_success_message($count_before, $favcolor_nav, $date_from, $date_to);
                            nav_show_alert('Success!', $message);
                        } else {
                            nav_show_alert('Error!', 'An error occurred while deleting comments. Please try again.');
                        }
                    } else {
                        $message = nav_show_no_comments_message($favcolor_nav, $date_from, $date_to);
                        nav_show_alert('Info', $message);
                    }
                } else {
                    die("Security Validation Failure");
                }
                break;
        }
        
        // Display the main interface
        // Force a fresh count
        nav_clear_comment_caches();
        $comments_count = wp_count_comments();
        ?>
        <div id="wpbody" role="main">
           <div id="wpbody-content" aria-label="Main content" tabindex="0" style="overflow: hidden;">
              <div class="wrap columns-2 seed_wnb">
                 <h2><b>Delete all comments with filter !!! </b></h2>
                 
                 <?php if (!$is_premium): ?>
                 <div class="notice notice-info">
                    <p>
                       <strong>Free Plan:</strong> You can delete up to 500 comments. 
                       <strong><?php echo $remaining_comments; ?></strong> comments remaining in your free plan.
                       <a href="<?php echo nonu_fs()->get_upgrade_url(); ?>" class="button button-primary">Upgrade to Premium</a>
                    </p>
                 </div>
                 <?php endif; ?>

                 <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                       <div id="post-body-content">
                          <form action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post">
                              <input type="hidden" name="nav@final_delete" value="<?php echo wp_create_nonce('nav@final_delete'); ?>">
                             <div class="postbox seedprod-postbox">
                                <div class="handlediv" title="Click to toggle"><br></div>
                                <h3 style="color:red">Delete comments !</h3>
                                <div class="inside">
                                    <p style="color:red;">Note : Deleted Comment will not be recovered !</p>
                                    <table class="form-table">
                                        <tbody>
                                            <tr>
                                                <th scope="row">All Comments:</th>
                                                <td>
                                                    <input type="radio" name="nav_delete_comment" required <?php if($comments_count->total_comments<=0) { echo 'disabled' ;  } ?>  value="nav_delete_all">
                                                    <span class="comment-count" style="color: #0073aa; font-weight: bold;">(<?php echo number_format($comments_count->total_comments); ?> comments)</span>
                                                    <br><small class="description">Note : Delete all comments (A to z)</small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Comments in moderation:</th>
                                                <td>
                                                    <input type="radio" name="nav_delete_comment" required <?php if($comments_count->moderated<=0) { echo 'disabled' ;  } ?> value="nav_delete_moderation">
                                                    <span class="comment-count" style="color: #0073aa; font-weight: bold;">(<?php echo number_format($comments_count->moderated); ?> comments)</span>
                                                    <br><small class="description">Note : Delete all comments which mark as moderation.</small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Comments approved:</th>
                                                <td>
                                                    <input type="radio" name="nav_delete_comment" required <?php if($comments_count->approved<=0) { echo 'disabled' ;  } ?>  value="nav_delete_approved">
                                                    <span class="comment-count" style="color: #0073aa; font-weight: bold;">(<?php echo number_format($comments_count->approved); ?> comments)</span>
                                                    <br><small class="description">Note : Delete all comments which mark as approved.</small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Comments in Spam:</th>
                                                <td>
                                                    <input type="radio" name="nav_delete_comment" required <?php if($comments_count->spam<=0) { echo 'disabled' ;  } ?> value="nav_delete_spam">
                                                    <span class="comment-count" style="color: #0073aa; font-weight: bold;">(<?php echo number_format($comments_count->spam); ?> comments)</span>
                                                    <br><small class="description">Note : Delete all comments which mark as Spam.</small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Comments in Trash:</th>
                                                <td>
                                                    <input type="radio" name="nav_delete_comment" required <?php if($comments_count->trash<=0) { echo 'disabled' ;  } ?> value="nav_delete_trash">
                                                    <span class="comment-count" style="color: #0073aa; font-weight: bold;">(<?php echo number_format($comments_count->trash); ?> comments)</span>
                                                    <br><small class="description">Note : Delete all comments which mark as Trash.</small>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                             </div>

                             <p>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php submit_button('Delete Now'); ?>
                                    <?php if (nonu_fs()->is_paying()): ?>
                                    <button type="button" id="nav-export-comments" class="button button-secondary">Export Comments</button>
                                    <?php else: ?>
                                    <button type="button" class="button button-secondary" disabled>Export Comments</button>
                                    <?php endif; ?>
                                </div>
                                <div id="nav-export-progress" style="display: none; margin-top: 10px;">
                                    <div class="progress-bar" style="width: 100%; background-color: #f0f0f0; border-radius: 4px;">
                                        <div class="progress-bar-fill" style="width: 0%; height: 20px; background-color: #0073aa; border-radius: 4px; transition: width 0.3s ease-in-out;"></div>
                                    </div>
                                    <p class="progress-text" style="margin-top: 5px; text-align: center;">Exporting comments... 0%</p>
                                </div>
                                <?php if (nonu_fs()->is_paying()): ?>
                                <p class="description" style="margin-top: 10px; color: #666;">
                                    <strong>Note:</strong> It's recommended to export your comments before deletion. This will create a backup of your comments in CSV format.
                                </p>
                                <?php else: ?>
                                <p class="description" style="margin-top: 10px; color: #666;">
                                    <strong>Note:</strong> Export functionality is available in the premium version. <a href="<?php echo nonu_fs()->get_upgrade_url(); ?>">Upgrade to Premium</a> to export your comments before deletion.
                                </p>
                                <?php endif; ?>
                             </p>
                          </form>

                          <div class="postbox seedprod-postbox">
                             <div class="handlediv" title="Click to toggle"><br></div>
                             <h3 style="color:green;">Automatic Spam Cleanup <?php if (!$is_premium): ?><span style="color: #FF813F;">(Premium Feature)</span><?php endif; ?></h3>
                             <div class="inside">
                                <form method="post" action="">
                                    <?php wp_nonce_field('nav_auto_delete_settings', 'nav_auto_delete_nonce'); ?>
                                    <table class="form-table">
                                       <tbody>
                                          <tr>
                                             <th scope="row">Auto-delete Spam:</th>
                                             <td>
                                                <select name="nav_auto_delete_spam" id="nav_auto_delete_spam" <?php if (!$is_premium) echo 'disabled'; ?>>
                                                   <option value="disabled" <?php selected(get_option('nav_auto_delete_spam'), 'disabled'); ?>>Disabled</option>
                                                   <option value="daily" <?php selected(get_option('nav_auto_delete_spam'), 'daily'); ?>>Daily</option>
                                                   <option value="weekly" <?php selected(get_option('nav_auto_delete_spam'), 'weekly'); ?>>Weekly</option>
                                                   <option value="monthly" <?php selected(get_option('nav_auto_delete_spam'), 'monthly'); ?>>Monthly</option>
                                                </select>
                                                <?php if ($is_premium): ?>
                                                <button type="submit" name="nav_schedule_cleanup" class="button button-primary" style="margin-left: 10px;">Schedule Now</button>
                                                <button type="submit" name="nav_cancel_cleanup" class="button button-secondary" style="margin-left: 10px;">Cancel Schedule</button>
                                                <?php else: ?>
                                                <button type="button" class="button button-primary" style="margin-left: 10px;" disabled>Schedule Now</button>
                                                <button type="button" class="button button-secondary" style="margin-left: 10px;" disabled>Cancel Schedule</button>
                                                <?php endif; ?>
                                                <p class="description">
                                                   <?php if ($is_premium): ?>
                                                   Automatically delete spam comments based on your selected schedule.
                                                   <?php else: ?>
                                                   <a href="<?php echo nonu_fs()->get_upgrade_url(); ?>">Upgrade to Premium</a> to enable automatic spam cleanup.
                                                   <?php endif; ?>
                                                </p>
                                             </td>
                                          </tr>
                                          <tr>
                                             <th scope="row">Last Cleanup:</th>
                                             <td>
                                                <?php 
                                                $last_cleanup = get_option('nav_last_spam_cleanup');
                                                if ($last_cleanup) {
                                                    echo date('F j, Y g:i a', strtotime($last_cleanup));
                                                } else {
                                                    echo 'Never';
                                                }
                                                ?>
                                             </td>
                                          </tr>
                                          <tr>
                                             <th scope="row">Next Cleanup:</th>
                                             <td>
                                                <?php 
                                                $schedule = get_option('nav_auto_delete_spam');
                                                if ($schedule && $schedule !== 'disabled') {
                                                    $next_run = wp_next_scheduled('nav_auto_delete_spam_event');
                                                    if ($next_run) {
                                                        echo date('F j, Y g:i a', $next_run);
                                                    } else {
                                                        echo 'Not scheduled';
                                                    }
                                                } else {
                                                    echo 'Not scheduled';
                                                }
                                                ?>
                                             </td>
                                          </tr>
                                       </tbody>
                                    </table>
                                </form>
                             </div>
                          </div>

                          <div class="postbox seedprod-postbox">
                             <div class="handlediv" title="Click to toggle"><br></div>
                             <?php if (!$is_premium): ?>
                             <h2 style="color:green;text-align:center;font-size:30px">PEOPLE WHO SUPPORTS THIS PLUGIN ( Total Paid users 25562+)  :</h2>
                             <h2 style="color:red;text-align:center;font-size:20px">*** Join 80K+ WordPress users who save countless hours managing comments! Upgrade to Premium and experience the power of efficient comment management. Limited time offer! ***</h2>
                             
                             <div style="text-align: center; margin: 20px 0;">
                                <a href="<?php echo nonu_fs()->get_upgrade_url(); ?>" class="button button-primary" style="font-size: 18px; padding: 10px 25px; background-color: #2271b1; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">
                                    Upgrade to Premium - Only $9.99 USD (One-time)
                                </a>
                                <p style="margin-top: 10px; color: #666; font-size: 14px;">
                                    ✨ Lifetime Access - No Recurring Charges
                                </p>
                             </div>
                             <?php else: ?>
                             <div style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin: 15px;">
                                 <h3 style="color: #2271b1; margin-top: 0; font-size: 20px; text-align: center;">Need WordPress Development?</h3>
                                 <div style="background: #2271b1; color: white; padding: 15px; border-radius: 6px; text-align: center; margin: 15px 0;">
                                     <div style="font-size: 24px; font-weight: bold;">$5 USD</div>
                                     <div style="font-size: 14px; opacity: 0.9;">per hour</div>
                                 </div>
                                 <ul style="list-style: none; padding-left: 0; margin: 20px 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee; padding: 15px 0;">
                                     <li style="margin-bottom: 12px; display: flex; align-items: center;">
                                         <span style="color: #2271b1; margin-right: 10px;">🎯</span>
                                         <span><strong>Custom WordPress Development</strong></span>
                                     </li>
                                     <li style="margin-bottom: 12px; display: flex; align-items: center;">
                                         <span style="color: #2271b1; margin-right: 10px;">🔧</span>
                                         <span><strong>Plugin Development & Customization</strong></span>
                                     </li>
                                     <li style="margin-bottom: 12px; display: flex; align-items: center;">
                                         <span style="color: #2271b1; margin-right: 10px;">🎨</span>
                                         <span><strong>Theme Development & Customization</strong></span>
                                     </li>
                                     <li style="margin-bottom: 12px; display: flex; align-items: center;">
                                         <span style="color: #2271b1; margin-right: 10px;">⚡</span>
                                         <span><strong>Performance Optimization</strong></span>
                                     </li>
                                 </ul>
                                 <div style="text-align: center;">
                                     <a href="mailto:info@lytechx.com" class="button button-primary" style="font-size: 16px; padding: 8px 25px; background-color: #2271b1; border-color: #2271b1; width: 100%;">
                                         Contact Us for Development
                                     </a>
                                     <p style="color: #666; font-size: 12px; margin-top: 10px; text-align: center;">
                                         <span style="color: #2271b1;">✓</span> Free consultation for premium users
                                     </p>
                                 </div>
                             </div>
                             <?php endif; ?>
                             
                             <div class="inside">
                                <table class="form-table">
                                
                                </table>
                             </div>
                          </div>
                       </div>

                       <div id="postbox-container-1" class="postbox-container">
                          <div id="side-sortables" class="meta-box-sortables ui-sortable">
                             <?php if (!$is_premium): ?>
                             <div class="postbox support-postbox" style="background-color: #fcf8e3">
                                <div class="handlediv" title="Click to toggle"><br></div>
                                <h3 class="hndle ui-sortable-handle"><span style="color:green">Upgrade to Premium</span></h3>
                                <div class="inside">
                                   <div style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <h4 style="color: #2271b1; margin-top: 0; font-size: 20px; text-align: center;">✨ Ultimate Comment Management</h4>
                                        <div style="background: #2271b1; color: white; padding: 15px; border-radius: 6px; text-align: center; margin: 15px 0;">
                                            <div style="font-size: 28px; font-weight: bold;">$9.99</div>
                                            <div style="font-size: 14px; opacity: 0.9;">One-time payment, lifetime access</div>
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
                                                <span style="color: #2271b1; margin-right: 10px;">⭐</span>
                                                <span><strong>Priority Support</strong> - Get help when you need it</span>
                                            </li>
                                            <li style="margin-bottom: 12px; display: flex; align-items: center;">
                                                <span style="color: #2271b1; margin-right: 10px;">🔄</span>
                                                <span><strong>Auto Spam Cleanup</strong> - Automatically remove spam comments</span>
                                            </li>
                                        </ul>
                                        <div style="text-align: center;">
                                            <a href="<?php echo nonu_fs()->get_upgrade_url(); ?>" class="button button-primary" style="font-size: 16px; padding: 8px 25px; background-color: #2271b1; border-color: #2271b1; width: 100%;">
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
                             
                             <div class="postbox support-postbox" style="background-color: #fcf8e3">
                                <div class="handlediv" title="Click to toggle"><br></div>
                                <h3 class="hndle ui-sortable-handle"><span style="color:green">Comments of your Website</span></h3>
                                <div class="inside">
                                   <div class="support-widget">
                                          <b>Total Comments:</b> 
                                          <?php echo $comments_count->total_comments; ?> </br>
                                      
                                         <b> Comments in moderation: :</b> 
                                          <?php echo $comments_count->moderated; ?> </br>
                                      
                                          <b>Comments approved: </b> 
                                        <?php echo $comments_count->approved; ?> </br>
                                      
                                         <b>Comments in Spam:</b> 
                                          <?php echo $comments_count->spam ; ?> </br>
                                      
                                         <b> Comments in Trash: </b> 
                                          <?php echo $comments_count->trash ; ?> </br>
                                      
                                   </div>
                                </div>
                             </div>
            
                             <iframe id='kofiframe' src='https://instavoty.com/RgaeZ/?hidefeed=true&widget=true&embed=true&preview=true' style='border:none;width:100%;padding:4px;background:#f9f9f9;' height='712' title='navneetsoni'></iframe>
                           
                             <?php if (WP_DEBUG): ?>
                             <?php nav_display_debug_log(); ?>
                             <?php endif; ?>
                          </div>
                       </div>
                    </div>
                 </div>
              </div>
           </div>
        </div>
        <?php
    } catch (Exception $e) {
        nav_show_alert('Error!', 'An error occurred while processing your request. Please try again later.');
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
?>
