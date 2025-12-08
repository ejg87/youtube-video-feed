jQuery(document).ready(function ($) {
    var $modal      = $('#yvf-modal');
    var $player     = $('#yvf-player');
    var $modalTitle = $('#yvf-modal-title');
    var $modalViews = $('#yvf-modal-views');
    var $wrapper    = $('#yvf-wrapper');
    var $search     = $('#yvf-search');

    /* ----------------------
     * Modal behaviour
     * ---------------------- */

    // Delegate so it still works after AJAX replacements
    $(document).on('click', '.yvf-grid .yvf-item', function () {
        var $item   = $(this);
        var videoId = $item.data('video-id');
        var title   = $item.data('title');
        var views   = $item.data('views');

        var embedUrl = 'https://www.youtube.com/embed/' + videoId + '?autoplay=1';
        $player.attr('src', embedUrl);

        $modalTitle.text(title || '');
        if (views) {
            $modalViews.text(views + ' views');
        } else {
            $modalViews.text('');
        }

        $modal.addClass('yvf-open');
    });

    $('.yvf-close, .yvf-modal-backdrop').on('click', function () {
        $modal.removeClass('yvf-open');
        $player.attr('src', '');
    });

    $(document).on('keyup', function (e) {
        if (e.key === 'Escape') {
            $modal.removeClass('yvf-open');
            $player.attr('src', '');
        }
    });

    /* ----------------------
     * Helper: loading state
     * ---------------------- */
    function setLoading(isLoading) {
        if (isLoading) {
            $wrapper.addClass('yvf-is-loading');
        } else {
            $wrapper.removeClass('yvf-is-loading');
        }
    }

    /* ----------------------
     * AJAX pagination
     * ---------------------- */

    $(document).on('click', '.yvf-pagination a.yvf-page-link', function (e) {
        e.preventDefault();

        var $link  = $(this);
        var page   = $link.data('page') || 1;
        var token  = $link.data('token') || '';

        setLoading(true);

        $.post(
            YVF.ajax_url,
            {
                action: 'yvf_get_page',
                nonce: YVF.nonce,
                page: page,
                token: token
            }
        ).done(function (response) {
            if (response && response.success && response.data && response.data.html) {
                $wrapper.html(response.data.html);

                // Keep URL on the front-end page only, never admin-ajax
                var newUrl = window.location.pathname + '?yvf_page=' + page;
                window.history.pushState({ page: page }, '', newUrl);

                $('html, body').animate(
                    {
                        scrollTop: $wrapper.offset().top - 50
                    },
                    300
                );
            } else {
                console.error('Failed to load page', response);
            }
        }).fail(function (xhr) {
            console.error('AJAX error', xhr);
        }).always(function () {
            setLoading(false);
        });
    });

    /* ----------------------
     * AJAX search
     * ---------------------- */

    var searchTimeout = null;

    $search.on('keyup', function () {
        var term = $(this).val();

        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(function () {
            setLoading(true);

            $.post(
                YVF.ajax_url,
                {
                    action: 'yvf_search',
                    nonce: YVF.nonce,
                    term: term
                }
            ).done(function (response) {
                if (response && response.success && response.data && response.data.html) {
                    $wrapper.html(response.data.html);
                    // We deliberately do NOT change the URL for search
                } else {
                    console.error('Search failed', response);
                }
            }).fail(function (xhr) {
                console.error('AJAX error', xhr);
            }).always(function () {
                setLoading(false);
            });
        }, 300); // debounce 300ms
    });
});
