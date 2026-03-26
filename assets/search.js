jQuery(function ($) {

    const $form = $('.sm-search-form');
    const $resultsWrap = $('.sm-search-results');

    if (!$form.length || !$resultsWrap.length) {
        console.log('❌ brak form lub resultsWrap');
        return;
    }

    let currentPage = 1;
    let isLoadingFromURL = false;

    console.log('🚀 INIT SEARCH JS');

    // ======================
    // URL UPDATE
    // ======================
    function updateURL(dataArr) {
        const params = new URLSearchParams();

        const excluded = [
            'sm_flats_search_nonce_field',
            '_wp_http_referer',
            'action'
        ];

        dataArr.forEach(item => {

            if (excluded.includes(item.name)) return;

            if (item.name === 'pg') {
                params.set('pg', item.value);
                return;
            }

            if (item.value !== '') {
                params.set(item.name, item.value);
            }
        });

        const newUrl = window.location.pathname + '?' + params.toString();

        console.log('🔗 UPDATE URL:', newUrl);

        history.pushState({}, '', newUrl);
    }

    // ======================
    // SEARCH
    // ======================
    function runSearch(page = 1, updateUrl = true) {

        console.log('🟡 runSearch START → page:', page);

        currentPage = page;

        const dataArr = $form.serializeArray();
        const filtered = dataArr.filter(item => item.name !== 'pg');

        filtered.push({ name: 'pg', value: currentPage });

        if (updateUrl && !isLoadingFromURL) {
            updateURL(filtered);
        }

        $resultsWrap.addClass('is-loading');

        $.ajax({
            url: sm_search.ajax_url,
            method: 'POST',
            data: $.param(filtered),
            success: function (response) {

                console.log('✅ AJAX SUCCESS page:', page);

                if (response && response.success && response.data) {
                    const html = response.data.html || '';
                    const pagination = response.data.pagination || '';

                    $resultsWrap.html(html + pagination);
                } else {
                    $resultsWrap.html('<div class="sm-error">Błąd wyszukiwania</div>');
                }

                $resultsWrap.removeClass('is-loading');

                // 🔥 RESTORE SCROLL (PO AJAX)
                const scroll = sessionStorage.getItem('sm_scroll');

                if (scroll !== null) {
                    console.log('🔙 RESTORE SCROLL AFTER AJAX:', scroll);

                    setTimeout(() => {
                        window.scrollTo(0, parseInt(scroll, 10));
                        sessionStorage.removeItem('sm_scroll');
                    }, 50);
                }
            },
            error: function () {
                console.log('❌ AJAX ERROR');
                $resultsWrap.html('<div class="sm-error">Błąd AJAX</div>');
                $resultsWrap.removeClass('is-loading');
            }
        });
    }

    // ======================
    // LOAD FROM URL
    // ======================
    function loadFromURL() {

        const params = new URLSearchParams(window.location.search);

        console.log('🌍 loadFromURL:', params.toString());

        if (!params.toString()) return false;

        isLoadingFromURL = true;

        $form.off('change.smSearch');

        params.forEach((value, key) => {
            const $el = $form.find(`[name="${key}"]`);
            if ($el.length) {
                $el.val(value);
            }
        });

        const page = parseInt(params.get('pg'), 10) || 1;

        console.log('📄 PAGE FROM URL:', page);

        currentPage = page;

        runSearch(page, false);

        setTimeout(() => {
            isLoadingFromURL = false;

            $form.on('change.smSearch', 'select, input', function () {
                runSearch(1);
            });

        }, 0);

        return true;
    }

    // ======================
    // EVENTS
    // ======================
    $form.on('change.smSearch', 'select, input', function () {
        if (isLoadingFromURL) return;
        runSearch(1);
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        runSearch(1);
    });

    $resultsWrap.on('click', '.sm-page-btn', function (e) {
        e.preventDefault();

        const page = parseInt($(this).data('page'), 10) || 1;

        if (page === currentPage) return;

        runSearch(page);

        $('html, body').animate({
            scrollTop: $resultsWrap.offset().top - 250
        }, 250);
    });

    $form.on('reset', function () {
        setTimeout(() => {
            history.pushState({}, '', window.location.pathname);
            runSearch(1, false); 
        }, 0);
    });

    // ======================
    // SCROLL SAVE
    // ======================
    $(document).on('click', '.sm-flat-card__btn', function () {
        const scrollY = window.scrollY;
        console.log('💾 SAVE SCROLL:', scrollY);
        sessionStorage.setItem('sm_scroll', scrollY);
    });

    // ======================
    // START
    // ======================
    $(window).on('load', function () {

        console.log('🔥 WINDOW LOAD');

        const loaded = loadFromURL();

        if (!loaded) {
            console.log('🚀 FIRST LOAD → default search');
            runSearch(1, false); // ❗ false = nie dodaje ?pg=1
        }

    });

});