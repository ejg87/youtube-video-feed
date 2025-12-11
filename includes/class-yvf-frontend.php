<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YVF_Frontend {

    /** @var YVF_YouTube_API */
    protected $api;

    public function __construct( YVF_YouTube_API $api ) {
        $this->api = $api;
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'yvf-styles',
            YVF_PLUGIN_URL . 'assets/css/yvf-styles.css',
            [],
            '1.1.0'
        );

        wp_enqueue_script(
            'yvf-scripts',
            YVF_PLUGIN_URL . 'assets/js/yvf-scripts.js',
            [ 'jquery' ],
            '1.1.0',
            true
        );

        wp_localize_script(
            'yvf-scripts',
            'YVF',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
            ]
        );
    }

    public function render_video_feed( $atts ) {
        $page_token   = '';
        $current_page = 1;
        $data         = $this->api->fetch_videos_page( $page_token );

        ob_start();
        ?>
        <div class="yvf-section">
            <div id="yvf-search-bar">
                <input type="text" id="yvf-search" placeholder="Search songs, artists, albums..." autocomplete="off" />
                <span class="yvf-search-hint">Type to search your music crate</span>
            </div>

            <div id="yvf-wrapper">
                <?php
                $this->render_grid_template(
                    $data['videos'],
                    $current_page,
                    $data['prevPageToken'],
                    $data['nextPageToken']
                );
                ?>
            </div>
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

    public function ajax_get_page() {
        $page       = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
        $page_token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

        $data = $this->api->fetch_videos_page( $page_token );

        ob_start();
        $this->render_grid_template(
            $data['videos'],
            $page,
            $data['prevPageToken'],
            $data['nextPageToken']
        );
        $html = ob_get_clean();

        wp_send_json_success(
            [
                'html'       => $html,
                'page'       => $page,
                'nextToken'  => $data['nextPageToken'],
                'prevToken'  => $data['prevPageToken'],
            ]
        );
    }

    public function ajax_search() {
        $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';

        if ( '' === $term ) {
            $data = $this->api->fetch_videos_page( '' );
            $page = 1;
        } else {
            $search = $this->api->search_videos( $term );
            $data   = [
                'videos'        => $search['videos'],
                'nextPageToken' => '',
                'prevPageToken' => '',
            ];
            $page = 1;
        }

        ob_start();
        $this->render_grid_template(
            $data['videos'],
            $page,
            $data['prevPageToken'],
            $data['nextPageToken'],
            $term
        );
        $html = ob_get_clean();

        wp_send_json_success(
            [
                'html' => $html,
            ]
        );
    }

    protected function render_grid_template( $videos, $current_page, $prev_token, $next_token, $search_term = '' ) {
        $videos       = is_array( $videos ) ? $videos : [];
        $current_page = (int) $current_page;

        include YVF_PLUGIN_DIR . 'templates/feed-grid.php';
    }
}
