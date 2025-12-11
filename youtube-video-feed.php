<?php
/**
 * Plugin Name: YouTube Video Feed
 * Description: Displays a YouTube channel feed with modal player, async pagination, and search using the YouTube Data API.
 * Author: EJ Goralewski
 * Version: 1.1.0
 * Text Domain: youtube-feed
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Core constants.
define( 'YVF_PLUGIN_FILE', __FILE__ );
define( 'YVF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YVF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YVF_PLUGIN_VERSION', '1.1.0' );

// Include classes.
require_once YVF_PLUGIN_DIR . 'includes/class-yvf-plugin.php';
require_once YVF_PLUGIN_DIR . 'includes/class-yvf-admin.php';
require_once YVF_PLUGIN_DIR . 'includes/class-yvf-frontend.php';
require_once YVF_PLUGIN_DIR . 'includes/class-yvf-youtube-api.php';

/**
 * Bootstrap the plugin.
 */
function yvf_run_plugin() {
    $plugin = new YVF_Plugin();
    $plugin->init();
}
add_action( 'plugins_loaded', 'yvf_run_plugin' );
