(function ($) {
    let currentVideoId = null;
    let searchTimeout  = null;

    function setLoading(isLoading) {
        const $wrapper = $('#yvf-wrapper');
        if (isLoading) {
            $wrapper.addClass('yvf-is-loading');
        } else {
            $wrapper.removeClass('yvf-is-loading');
        }
    }

    function openModal(videoId, title, views) {
        currentVideoId = videoId;

        const src = 'https://www.youtube.com/embed/' + encodeURIComponent(videoId) + '?autoplay=1';

        $('#yvf-player').attr('src', src);
        $('#yvf-modal-title').text(title || '');
        $('#yvf-modal-views').text(views ? views + ' views' : '');

        $('#yvf-modal').addClass('yvf-open');
    }

    function closeModal() {
        $('#yvf-modal').removeClass('yvf-open');
        $('#yvf-player').attr('src', '');
        currentVideoId = null;
    }

    function loadPage(page, token) {
        setLoading(true);

        $.post(
            YVF.ajax_url,
            {
                action: 'yvf_get_page',
                page: page,
                token: token || ''
            }
        ).done(function (response) {
            if (response && response.success && response.data && response.data.html) {
                $('#yvf-wrapper').html(response.data.html);
            } else {
                console.error('Pagination error', response);
            }
        }).fail(function (xhr) {
            console.error('AJAX error', xhr);
        }).always(function () {
            setLoading(false);
        });
    }

    function performSearch(term) {
        setLoading(true);

        $.post(
            YVF.ajax_url,
            {
                action: 'yvf_search',
                term: term || ''
            }
        ).done(function (response) {
            if (response && response.success && response.data && response.data.html) {
                $('#yvf-wrapper').html(response.data.html);
            } else {
                console.error('Search error', response);
            }
        }).fail(function (xhr) {
            console.error('AJAX error', xhr);
        }).always(function () {
            setLoading(false);
        });
    }

    // Delegated handlers (content is replaced via AJAX)
    $(document)
        // open modal on video click
        .on('click', '.yvf-grid .yvf-item', function (e) {
            e.preventDefault();
            const $item = $(this);

            openModal(
                $item.data('video-id'),
                $item.data('title'),
                $item.data('views')
            );
        })

        // pagination
        .on('click', '.yvf-page-link', function (e) {
            e.preventDefault();

            const $link = $(this);
            const page  = parseInt($link.data('page'), 10) || 1;
            const token = $link.data('token') || '';

            loadPage(page, token);
        })

        // close modal
        .on('click', '.yvf-close, .yvf-modal-backdrop', function () {
            closeModal();
        });

    // Esc key closes modal
    $(document).on('keyup', function (e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // Debounced search
    $('#yvf-search').on('input', function () {
        const term = $(this).val().trim();

        clearTimeout(searchTimeout);

        // Only search when 2+ chars, otherwise reset to page 1
        searchTimeout = setTimeout(function () {
            if (term.length >= 2) {
                performSearch(term);
            } else {
                performSearch('');
            }
        }, 400);
    });
})(jQuery);
