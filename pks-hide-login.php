<?php

/**
 * Plugin Name: PKS Hide Login
 * Description: Custom plugin to modify WP login URL.
 * Version:     1.0.0
 * Author:     Palash Kumer
 * Text Domain: pks-hide-login
 */

// Define absolute path for security.
if (!defined('ABSPATH')) {
    exit; // Prevent direct access.
}
if (!defined('PKS_WP_Admin_Login_Name')) {
    define('PKS_WP_Admin_Login_Name', 'PKS wp-admin login');
}

if (!defined('PKS_WP_Admin_Login_Path')) {
    define('PKS_WP_Admin_Login_Path', plugin_dir_path(__FILE__));
}

if (!defined('PKS_WP_Admin_Login_Base_Uri')) {
    define('PKS_WP_Admin_Login_Base_Uri', plugin_dir_url(__FILE__));
}

require_once PKS_WP_Admin_Login_Path . 'includes/pks-wp-admin-login.php';
