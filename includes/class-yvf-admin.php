<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YVF_Admin {

    /**
     * Add the settings page under "Settings".
     */
    public function add_settings_page() {
        add_options_page(
            __( 'YouTube Video Feed', 'snowlion-youtube-feed' ),
            __( 'YouTube Video Feed', 'snowlion-youtube-feed' ),
            'manage_options',
            'yvf-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register options for API key, channel ID, per-page count.
     */
    public function register_settings() {
        register_setting( 'yvf_settings', 'yvf_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'yvf_settings', 'yvf_channel_id', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( 'yvf_settings', 'yvf_per_page', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 8,
        ] );
    }

    /**
     * Render the settings page HTML.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $api_key    = get_option( 'yvf_api_key', '' );
        $channel_id = get_option( 'yvf_channel_id', '' );
        $per_page   = (int) get_option( 'yvf_per_page', 8 );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'YouTube Video Feed Settings', 'snowlion-youtube-feed' ); ?></h1>

            <p>
                <?php esc_html_e( 'Enter your YouTube Data API key and channel ID. Videos will be pulled directly from your channel; no media is stored in WordPress.', 'snowlion-youtube-feed' ); ?>
            </p>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'yvf_settings' );
                ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="yvf_api_key">
                                <?php esc_html_e( 'YouTube API Key', 'snowlion-youtube-feed' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="yvf_api_key"
                                   name="yvf_api_key"
                                   value="<?php echo esc_attr( $api_key ); ?>"
                                   class="regular-text"
                                   autocomplete="off" />
                            <p class="description">
                                <?php esc_html_e( 'Create an API key in Google Cloud Console and enable the YouTube Data API v3.', 'snowlion-youtube-feed' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="yvf_channel_id">
                                <?php esc_html_e( 'YouTube Channel ID', 'snowlion-youtube-feed' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="yvf_channel_id"
                                   name="yvf_channel_id"
                                   value="<?php echo esc_attr( $channel_id ); ?>"
                                   class="regular-text"
                                   autocomplete="off" />
                            <p class="description">
                                <?php esc_html_e( 'Your channel ID (not the custom URL). It usually starts with "UC...".', 'snowlion-youtube-feed' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="yvf_per_page">
                                <?php esc_html_e( 'Videos per page', 'snowlion-youtube-feed' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   id="yvf_per_page"
                                   name="yvf_per_page"
                                   value="<?php echo esc_attr( $per_page ); ?>"
                                   min="1"
                                   max="50" />
                            <p class="description">
                                <?php esc_html_e( 'How many videos to show per page in the grid (YouTube max is 50).', 'snowlion-youtube-feed' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
