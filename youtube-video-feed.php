<?php
/**
 * Plugin Name: YouTube Video Feed
 * Description: Displays a YouTube video feed from your channel with modal playback, async pagination and search. Uses YouTube as backend (no video files stored on WordPress).
 * Version: 2.0.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YVF_Plugin {

    const OPTION_API_KEY     = 'yvf_api_key';
    const OPTION_CHANNEL_ID  = 'yvf_channel_id';
    const OPTION_MAX_RESULTS = 'yvf_max_results';

    public function __construct() {
        // Admin
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Frontend
        add_shortcode( 'youtube_video_feed', [ $this, 'render_video_feed' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX: pagination + search
        add_action( 'wp_ajax_yvf_get_page', [ $this, 'ajax_get_page' ] );
        add_action( 'wp_ajax_nopriv_yvf_get_page', [ $this, 'ajax_get_page' ] );
        add_action( 'wp_ajax_yvf_search', [ $this, 'ajax_search' ] );
        add_action( 'wp_ajax_nopriv_yvf_search', [ $this, 'ajax_search' ] );
    }

    /* ---------------------------
     * Admin: settings page
     * --------------------------- */

    public function add_settings_page() {
        add_options_page(
            'YouTube Video Feed',
            'YouTube Video Feed',
            'manage_options',
            'yvf-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'yvf_settings_group', self::OPTION_API_KEY );
        register_setting( 'yvf_settings_group', self::OPTION_CHANNEL_ID );
        register_setting( 'yvf_settings_group', self::OPTION_MAX_RESULTS );

        add_settings_section(
            'yvf_main_section',
            'YouTube API Settings',
            function() {
                echo '<p>Enter your YouTube API details.</p>';
            },
            'yvf-settings'
        );

        add_settings_field(
            self::OPTION_API_KEY,
            'API Key',
            [ $this, 'render_text_field' ],
            'yvf-settings',
            'yvf_main_section',
            [ 'label_for' => self::OPTION_API_KEY ]
        );

        add_settings_field(
            self::OPTION_CHANNEL_ID,
            'Channel ID',
            [ $this, 'render_text_field' ],
            'yvf-settings',
            'yvf_main_section',
            [ 'label_for' => self::OPTION_CHANNEL_ID ]
        );

        add_settings_field(
            self::OPTION_MAX_RESULTS,
            'Videos per page',
            [ $this, 'render_text_field' ],
            'yvf-settings',
            'yvf_main_section',
            [ 'label_for' => self::OPTION_MAX_RESULTS ]
        );
    }

    public function render_text_field( $args ) {
        $option = get_option( $args['label_for'] );
        echo '<input type="text" id="' . esc_attr( $args['label_for'] ) . '" name="' . esc_attr( $args['label_for'] ) . '" value="' . esc_attr( $option ) . '" class="regular-text" />';
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>YouTube Video Feed Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'yvf_settings_group' );
                do_settings_sections( 'yvf-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /* ---------------------------
     * Frontend assets
     * --------------------------- */

    public function enqueue_assets() {
        wp_enqueue_style(
            'yvf-styles',
            plugin_dir_url( __FILE__ ) . 'assets/css/yvf-styles.css',
            [],
            '2.0.0'
        );

        wp_enqueue_script( 'jquery' );

        wp_enqueue_script(
            'yvf-scripts',
            plugin_dir_url( __FILE__ ) . 'assets/js/yvf-scripts.js',
            [ 'jquery' ],
            '2.0.0',
            true
        );

        wp_localize_script(
            'yvf-scripts',
            'YVF',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'yvf_nonce' ),
            ]
        );
    }

    /* ---------------------------
     * Shortcode output
     * --------------------------- */

    public function render_video_feed( $atts ) {
        $current_page = isset( $_GET['yvf_page'] ) ? max( 1, (int) $_GET['yvf_page'] ) : 1;
        $page_token   = isset( $_GET['yvf_token'] ) ? sanitize_text_field( wp_unslash( $_GET['yvf_token'] ) ) : '';

        $inner_html = $this->build_feed_html( $current_page, $page_token );

        ob_start();
        ?>
        <div id="yvf-search-bar">
            <input type="text" id="yvf-search" placeholder="Search videos..." autocomplete="off" />
            <span class="yvf-search-hint">Type to search titles</span>
        </div>

        <div id="yvf-wrapper">
            <?php echo $inner_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>

        <!-- Modal -->
        <div id="yvf-modal" class="yvf-modal">
            <div class="yvf-modal-content">
                <span class="yvf-close">&times;</span>
                <div class="yvf-modal-body">
                    <div class="yvf-player-wrapper">
                        <iframe id="yvf-player" src="" frameborder="0" allowfullscreen></iframe>
                    </div>
                    <div class="yvf-modal-meta">
                        <h3 id="yvf-modal-title"></h3>
                        <p id="yvf-modal-views"></p>
                    </div>
                </div>
            </div>
            <div class="yvf-modal-backdrop"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Build grid + pagination HTML for a given page/pageToken.
     * Used by both shortcode and AJAX.
     */
    private function build_feed_html( $current_page, $page_token = '' ) {
        $api_key    = get_option( self::OPTION_API_KEY );
        $channel_id = get_option( self::OPTION_CHANNEL_ID );
        $per_page   = (int) get_option( self::OPTION_MAX_RESULTS, 8 );

        if ( empty( $api_key ) || empty( $channel_id ) ) {
            return '<p class="yvf-error">YouTube feed is not configured yet.</p>';
        }

        $uploads_playlist_id = $this->get_uploads_playlist_id( $api_key, $channel_id );
        if ( empty( $uploads_playlist_id ) ) {
            return '<p class="yvf-error">Unable to load channel uploads.</p>';
        }

        $page_data = $this->fetch_channel_videos_page( $api_key, $uploads_playlist_id, $per_page, $page_token );
        if ( empty( $page_data['videos'] ) ) {
            return '<p class="yvf-error">No videos found.</p>';
        }

        $videos          = $page_data['videos'];
        $next_page_token = isset( $page_data['next_page_token'] ) ? $page_data['next_page_token'] : '';
        $prev_page_token = isset( $page_data['prev_page_token'] ) ? $page_data['prev_page_token'] : '';
        $total_results   = isset( $page_data['total_results'] ) ? (int) $page_data['total_results'] : 0;
        $results_perpage = isset( $page_data['results_per_page'] ) ? (int) $page_data['results_per_page'] : $per_page;

        $total_pages = ( $results_perpage > 0 && $total_results > 0 )
            ? (int) ceil( $total_results / $results_perpage )
            : $current_page;

        if ( $current_page > $total_pages ) {
            $current_page = $total_pages;
        }
        if ( $current_page < 1 ) {
            $current_page = 1;
        }

        // Base URL for non-JS fallback (only from front-end context)
        $base_url = remove_query_arg( [ 'yvf_page', 'yvf_token' ] );

        ob_start();
        ?>
        <div class="yvf-grid">
            <?php foreach ( $videos as $video ) : ?>
                <div class="yvf-item"
                     data-video-id="<?php echo esc_attr( $video['id'] ); ?>"
                     data-title="<?php echo esc_attr( $video['title'] ); ?>"
                     data-views="<?php echo esc_attr( $video['views'] ); ?>">
                    <div class="yvf-thumb">
                        <img src="<?php echo esc_url( $video['thumbnail'] ); ?>" alt="<?php echo esc_attr( $video['title'] ); ?>">
                    </div>
                    <div class="yvf-info">
                        <h3><?php echo esc_html( $video['title'] ); ?></h3>
                        <p class="yvf-views"><?php echo esc_html( $video['views'] ); ?> views</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="yvf-pagination">
            <?php if ( $current_page > 1 && ! empty( $prev_page_token ) ) : ?>
                <?php
                $prev_url = add_query_arg(
                    [
                        'yvf_page'  => $current_page - 1,
                        'yvf_token' => rawurlencode( $prev_page_token ),
                    ],
                    $base_url
                );
                ?>
                <a class="yvf-page-link yvf-prev"
                   href="<?php echo esc_url( $prev_url ); ?>"
                   data-page="<?php echo (int) ( $current_page - 1 ); ?>"
                   data-token="<?php echo esc_attr( $prev_page_token ); ?>">
                    &laquo; Prev
                </a>
            <?php endif; ?>

            <span class="yvf-page-current">
                Page <?php echo (int) $current_page; ?>
                <?php if ( $total_pages ) : ?>
                    of <?php echo (int) $total_pages; ?>
                <?php endif; ?>
            </span>

            <?php if ( ! empty( $next_page_token ) ) : ?>
                <?php
                $next_url = add_query_arg(
                    [
                        'yvf_page'  => $current_page + 1,
                        'yvf_token' => rawurlencode( $next_page_token ),
                    ],
                    $base_url
                );
                ?>
                <a class="yvf-page-link yvf-next"
                   href="<?php echo esc_url( $next_url ); ?>"
                   data-page="<?php echo (int) ( $current_page + 1 ); ?>"
                   data-token="<?php echo esc_attr( $next_page_token ); ?>">
                    Next &raquo;
                </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ---------------------------
     * AJAX: pagination / search
     * --------------------------- */

    public function ajax_get_page() {
        

        $page       = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
        $page_token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

        $html = $this->build_feed_html( $page, $page_token );

        if ( empty( $html ) ) {
            wp_send_json_error( [ 'message' => 'Failed to load videos.' ] );
        }

        wp_send_json_success( [ 'html' => $html ] );
    }

    public function ajax_search() {
        

        $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';

        if ( '' === $term ) {
            // Empty search → normal first page
            $html = $this->build_feed_html( 1 );
            wp_send_json_success( [ 'html' => $html ] );
        }

        $api_key    = get_option( self::OPTION_API_KEY );
        $channel_id = get_option( self::OPTION_CHANNEL_ID );
        $per_page   = (int) get_option( self::OPTION_MAX_RESULTS, 8 );

        if ( empty( $api_key ) || empty( $channel_id ) ) {
            wp_send_json_error( [ 'message' => 'YouTube feed is not configured yet.' ] );
        }

        $videos = $this->search_videos( $api_key, $channel_id, $term, $per_page );

        ob_start();
        if ( empty( $videos ) ) :
            ?>
            <p class="yvf-error">No videos found matching "<?php echo esc_html( $term ); ?>".</p>
            <?php
        else :
            ?>
            <p class="yvf-search-results-label">
                Showing results for "<strong><?php echo esc_html( $term ); ?></strong>"
            </p>
            <div class="yvf-grid">
                <?php foreach ( $videos as $video ) : ?>
                    <div class="yvf-item"
                         data-video-id="<?php echo esc_attr( $video['id'] ); ?>"
                         data-title="<?php echo esc_attr( $video['title'] ); ?>"
                         data-views="<?php echo esc_attr( $video['views'] ); ?>">
                        <div class="yvf-thumb">
                            <img src="<?php echo esc_url( $video['thumbnail'] ); ?>" alt="<?php echo esc_attr( $video['title'] ); ?>">
                        </div>
                        <div class="yvf-info">
                            <h3><?php echo esc_html( $video['title'] ); ?></h3>
                            <p class="yvf-views"><?php echo esc_html( $video['views'] ); ?> views</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
        endif;

        $html = ob_get_clean();
        wp_send_json_success( [ 'html' => $html ] );
    }

    /* ---------------------------
     * YouTube helpers
     * --------------------------- */

    private function get_uploads_playlist_id( $api_key, $channel_id ) {
        $cache_key           = 'yvf_channel_' . md5( $channel_id );
        $uploads_playlist_id = get_transient( $cache_key );

        if ( false !== $uploads_playlist_id ) {
            return $uploads_playlist_id;
        }

        $channel_url = add_query_arg(
            [
                'part' => 'contentDetails',
                'id'   => $channel_id,
                'key'  => $api_key,
            ],
            'https://www.googleapis.com/youtube/v3/channels'
        );

        $response = wp_remote_get( $channel_url );
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ) ) {
            return false;
        }

        $uploads_playlist_id = $data['items'][0]['contentDetails']['relatedPlaylists']['uploads'];
        set_transient( $cache_key, $uploads_playlist_id, DAY_IN_SECONDS );

        return $uploads_playlist_id;
    }

    private function fetch_channel_videos_page( $api_key, $uploads_playlist_id, $per_page, $page_token = '' ) {
        $args = [
            'part'       => 'snippet,contentDetails',
            'playlistId' => $uploads_playlist_id,
            'maxResults' => $per_page,
            'key'        => $api_key,
        ];

        if ( '' !== $page_token ) {
            $args['pageToken'] = $page_token;
        }

        $playlist_url      = add_query_arg( $args, 'https://www.googleapis.com/youtube/v3/playlistItems' );
        $playlist_response = wp_remote_get( $playlist_url );

        if ( is_wp_error( $playlist_response ) ) {
            return [ 'videos' => [] ];
        }

        $playlist_data = json_decode( wp_remote_retrieve_body( $playlist_response ), true );
        if ( empty( $playlist_data['items'] ) ) {
            return [ 'videos' => [] ];
        }

        $videos           = [];
        $video_ids        = [];
        $total_results    = isset( $playlist_data['pageInfo']['totalResults'] ) ? (int) $playlist_data['pageInfo']['totalResults'] : 0;
        $results_per_page = isset( $playlist_data['pageInfo']['resultsPerPage'] ) ? (int) $playlist_data['pageInfo']['resultsPerPage'] : $per_page;
        $next_page_token  = isset( $playlist_data['nextPageToken'] ) ? $playlist_data['nextPageToken'] : '';
        $prev_page_token  = isset( $playlist_data['prevPageToken'] ) ? $playlist_data['prevPageToken'] : '';

        foreach ( $playlist_data['items'] as $item ) {
            $video_id    = $item['contentDetails']['videoId'];
            $video_ids[] = $video_id;

            $thumb = isset( $item['snippet']['thumbnails']['medium']['url'] )
                ? $item['snippet']['thumbnails']['medium']['url']
                : '';

            $videos[ $video_id ] = [
                'id'        => $video_id,
                'title'     => $item['snippet']['title'],
                'thumbnail' => $thumb,
                'views'     => 0,
            ];
        }

        if ( ! empty( $video_ids ) ) {
            $stats_url = add_query_arg(
                [
                    'part' => 'statistics',
                    'id'   => implode( ',', $video_ids ),
                    'key'  => $api_key,
                ],
                'https://www.googleapis.com/youtube/v3/videos'
            );

            $stats_response = wp_remote_get( $stats_url );
            if ( ! is_wp_error( $stats_response ) ) {
                $stats_data = json_decode( wp_remote_retrieve_body( $stats_response ), true );
                if ( ! empty( $stats_data['items'] ) ) {
                    foreach ( $stats_data['items'] as $video_stat ) {
                        $id = $video_stat['id'];
                        if ( isset( $videos[ $id ] ) ) {
                            $videos[ $id ]['views'] = number_format_i18n( $video_stat['statistics']['viewCount'] );
                        }
                    }
                }
            }
        }

        return [
            'videos'           => array_values( $videos ),
            'next_page_token'  => $next_page_token,
            'prev_page_token'  => $prev_page_token,
            'total_results'    => $total_results,
            'results_per_page' => $results_per_page,
        ];
    }

    private function search_videos( $api_key, $channel_id, $term, $max_results = 20 ) {
        $args = [
            'part'       => 'snippet',
            'channelId'  => $channel_id,
            'q'          => $term,
            'type'       => 'video',
            'maxResults' => $max_results,
            'key'        => $api_key,
        ];

        $search_url      = add_query_arg( $args, 'https://www.googleapis.com/youtube/v3/search' );
        $search_response = wp_remote_get( $search_url );

        if ( is_wp_error( $search_response ) ) {
            return [];
        }

        $search_data = json_decode( wp_remote_retrieve_body( $search_response ), true );
        if ( empty( $search_data['items'] ) ) {
            return [];
        }

        $videos    = [];
        $video_ids = [];

        foreach ( $search_data['items'] as $item ) {
            if ( empty( $item['id']['videoId'] ) ) {
                continue;
            }

            $video_id    = $item['id']['videoId'];
            $video_ids[] = $video_id;

            $thumb = isset( $item['snippet']['thumbnails']['medium']['url'] )
                ? $item['snippet']['thumbnails']['medium']['url']
                : '';

            $videos[ $video_id ] = [
                'id'        => $video_id,
                'title'     => $item['snippet']['title'],
                'thumbnail' => $thumb,
                'views'     => 0,
            ];
        }

        if ( empty( $video_ids ) ) {
            return [];
        }

        $stats_url = add_query_arg(
            [
                'part' => 'statistics',
                'id'   => implode( ',', $video_ids ),
                'key'  => $api_key,
            ],
            'https://www.googleapis.com/youtube/v3/videos'
        );

        $stats_response = wp_remote_get( $stats_url );
        if ( ! is_wp_error( $stats_response ) ) {
            $stats_data = json_decode( wp_remote_retrieve_body( $stats_response ), true );
            if ( ! empty( $stats_data['items'] ) ) {
                foreach ( $stats_data['items'] as $video_stat ) {
                    $id = $video_stat['id'];
                    if ( isset( $videos[ $id ] ) ) {
                        $videos[ $id ]['views'] = number_format_i18n( $video_stat['statistics']['viewCount'] );
                    }
                }
            }
        }

        return array_values( $videos );
    }
}

new YVF_Plugin();
