<?php
/**
 * Template: Video grid + pagination.
 *
 * @var array  $videos
 * @var int    $current_page
 * @var string $prev_token
 * @var string $next_token
 * @var string $search_term
 */
?>

<div class="yvf-grid">
    <?php if ( empty( $videos ) ) : ?>
        <p class="yvf-error">
            <?php echo $search_term ? 'No matches found for "' . esc_html( $search_term ) . '".' : 'No videos found.'; ?>
        </p>
    <?php else : ?>
        <?php foreach ( $videos as $video ) : ?>
            <div class="yvf-item"
                 data-video-id="<?php echo esc_attr( $video['id'] ); ?>"
                 data-title="<?php echo esc_attr( $video['title'] ); ?>"
                 data-views="<?php echo esc_attr( $video['views'] ); ?>">
                <div class="yvf-thumb">
                    <img src="<?php echo esc_url( $video['thumbnail'] ); ?>"
                         alt="<?php echo esc_attr( $video['title'] ); ?>">
                </div>
                <div class="yvf-info">
                    <h3><?php echo esc_html( $video['title'] ); ?></h3>
                    <?php if ( ! empty( $video['views'] ) ) : ?>
                        <p class="yvf-views"><?php echo esc_html( $video['views'] ); ?> views</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ( ! empty( $prev_token ) || ! empty( $next_token ) ) : ?>
    <div class="yvf-pagination">
        <?php if ( ! empty( $prev_token ) ) : ?>
            <a href="#" class="yvf-page-link" data-page="<?php echo esc_attr( $current_page - 1 ); ?>" data-token="<?php echo esc_attr( $prev_token ); ?>">&laquo; Prev</a>
        <?php endif; ?>

        <span class="yvf-page-current">Page <?php echo esc_html( $current_page ); ?></span>

        <?php if ( ! empty( $next_token ) ) : ?>
            <a href="#" class="yvf-page-link" data-page="<?php echo esc_attr( $current_page + 1 ); ?>" data-token="<?php echo esc_attr( $next_token ); ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
