<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YVF_Plugin {

    /** @var YVF_Admin */
    protected $admin;

    /** @var YVF_Frontend */
    protected $frontend;

    /** @var YVF_YouTube_API */
    protected $api;

    /**
     * Set up the plugin: classes + hooks.
     */
    public function init() {
        // Shared API instance.
        $this->api      = new YVF_YouTube_API();
        $this->frontend = new YVF_Frontend( $this->api );
        $this->admin    = new YVF_Admin();

        // Shortcodes.
        add_action( 'init', [ $this, 'register_shortcodes' ] );

        // Frontend assets.
        add_action( 'wp_enqueue_scripts', [ $this->frontend, 'enqueue_assets' ] );

        // Admin settings.
        add_action( 'admin_menu', [ $this->admin, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this->admin, 'register_settings' ] );

        // AJAX: pagination.
        add_action( 'wp_ajax_yvf_get_page', [ $this->frontend, 'ajax_get_page' ] );
        add_action( 'wp_ajax_nopriv_yvf_get_page', [ $this->frontend, 'ajax_get_page' ] );

        // AJAX: search.
        add_action( 'wp_ajax_yvf_search', [ $this->frontend, 'ajax_search' ] );
        add_action( 'wp_ajax_nopriv_yvf_search', [ $this->frontend, 'ajax_search' ] );
    }

    /**
     * Register plugin shortcodes.
     */
    public function register_shortcodes() {
        add_shortcode( 'youtube_video_feed', [ $this->frontend, 'render_video_feed' ] );
    }
}
