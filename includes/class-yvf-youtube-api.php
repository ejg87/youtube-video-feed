<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YVF_YouTube_API {

    protected $api_key;
    protected $channel_id;
    protected $max_results;

    public function __construct() {
        $this->api_key     = get_option( 'yvf_api_key', '' );
        $this->channel_id  = get_option( 'yvf_channel_id', '' );
        $this->max_results = (int) get_option( 'yvf_per_page', 8 );

        if ( $this->max_results < 1 ) {
            $this->max_results = 8;
        } elseif ( $this->max_results > 50 ) {
            $this->max_results = 50;
        }
    }

    /**
     * Get uploads playlist ID for this channel.
     * Cached for 24h.
     */
    public function get_uploads_playlist_id() {
        if ( empty( $this->api_key ) || empty( $this->channel_id ) ) {
            return '';
        }

        $transient_key = 'yvf_uploads_playlist_' . md5( $this->channel_id );

        $cached = get_transient( $transient_key );
        if ( $cached ) {
            return $cached;
        }

        $args = [
            'part'       => 'contentDetails',
            'id'         => $this->channel_id,
            'key'        => $this->api_key,
            'maxResults' => 1,
            // only return what we need
            'fields'     => 'items(contentDetails/relatedPlaylists/uploads)',
        ];

        $url      = add_query_arg( $args, 'https://www.googleapis.com/youtube/v3/channels' );
        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ) ) {
            return '';
        }

        $playlist_id = $body['items'][0]['contentDetails']['relatedPlaylists']['uploads'];

        set_transient( $transient_key, $playlist_id, DAY_IN_SECONDS );

        return $playlist_id;
    }

    /**
     * Fetch one page of videos from the uploads playlist.
     * Cached per page_token for 10 minutes.
     */
    public function fetch_videos_page( $page_token = '' ) {
        $playlist_id = $this->get_uploads_playlist_id();
        if ( ! $playlist_id ) {
            return [ 'videos' => [], 'nextPageToken' => '', 'prevPageToken' => '' ];
        }

        $cache_key = 'yvf_page_' . md5( $playlist_id . '|' . $page_token . '|' . $this->max_results );
        $cached    = get_transient( $cache_key );
        if ( $cached ) {
            return $cached;
        }

        $args = [
            'part'       => 'snippet,contentDetails',
            'playlistId' => $playlist_id,
            'maxResults' => $this->max_results,
            'key'        => $this->api_key,
            'fields'     => 'items(snippet(title,thumbnails/medium/url,publishedAt,description,resourceId/videoId)),nextPageToken,prevPageToken',
        ];

        if ( $page_token ) {
            $args['pageToken'] = $page_token;
        }

        $url      = add_query_arg( $args, 'https://www.googleapis.com/youtube/v3/playlistItems' );
        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            return [ 'videos' => [], 'nextPageToken' => '', 'prevPageToken' => '' ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $out  = [
            'videos'        => [],
            'nextPageToken' => isset( $body['nextPageToken'] ) ? $body['nextPageToken'] : '',
            'prevPageToken' => isset( $body['prevPageToken'] ) ? $body['prevPageToken'] : '',
        ];

        if ( ! empty( $body['items'] ) ) {
            foreach ( $body['items'] as $item ) {
                $snippet  = $item['snippet'];
                $video_id = $snippet['resourceId']['videoId'];

                $out['videos'][] = [
                    'id'        => $video_id,
                    'title'     => $snippet['title'],
                    'thumbnail' => $snippet['thumbnails']['medium']['url'],
                    'published' => $snippet['publishedAt'],
                    'views'     => '', // you can fill this via a separate /videos call if you want
                    'desc'      => isset( $snippet['description'] ) ? $snippet['description'] : '',
                ];
            }
        }

        // Cache page for 10 minutes
        set_transient( $cache_key, $out, MINUTE_IN_SECONDS * 10 );

        return $out;
    }

    /**
     * Search videos in this channel.
     *
     * YouTube search already matches:
     * - video title (song names)
     * - description (artists, album names if you put them there)
     * - tags
     *
     * Cached per search term for 5 minutes.
     */
    public function search_videos( $term ) {
        $term = trim( $term );
        if ( '' === $term || empty( $this->api_key ) || empty( $this->channel_id ) ) {
            return [ 'videos' => [] ];
        }

        $cache_key = 'yvf_search_' . md5( mb_strtolower( $term ) . '|' . $this->channel_id . '|' . $this->max_results );
        $cached    = get_transient( $cache_key );
        if ( $cached ) {
            return $cached;
        }

        $args = [
            'part'       => 'snippet',
            'channelId'  => $this->channel_id,
            'q'          => $term,
            'type'       => 'video',
            'maxResults' => $this->max_results,
            'order'      => 'relevance', // best match for title/artist/album words
            'key'        => $this->api_key,
            'fields'     => 'items(id/videoId,snippet(title,thumbnails/medium/url,description,publishedAt))',
        ];

        $url      = add_query_arg( $args, 'https://www.googleapis.com/youtube/v3/search' );
        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            return [ 'videos' => [] ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $out  = [ 'videos' => [] ];

        if ( ! empty( $body['items'] ) ) {
            foreach ( $body['items'] as $item ) {
                $snippet  = $item['snippet'];
                $video_id = $item['id']['videoId'];

                $out['videos'][] = [
                    'id'        => $video_id,
                    'title'     => $snippet['title'],
                    'thumbnail' => $snippet['thumbnails']['medium']['url'],
                    'published' => $snippet['publishedAt'],
                    'views'     => '',
                    'desc'      => isset( $snippet['description'] ) ? $snippet['description'] : '',
                ];
            }
        }

        // Cache search results for 5 minutes
        set_transient( $cache_key, $out, MINUTE_IN_SECONDS * 5 );

        return $out;
    }
}
