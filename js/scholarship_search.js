$(function () {
    const $form = $('#scholarship-search');
    const $results = $('#search-results');
    let pending = null;
    let timer = null;
    function refresh(page, logSearch, pushHistory) {
        if (pending) pending.abort();
        const params = new URLSearchParams($form.serialize());
        if (page > 1) params.set('page', String(page));
        else params.delete('page');
        const url = 'scholarships.php?' + params.toString();
        const ajaxParams = new URLSearchParams(params);
        if (logSearch) ajaxParams.set('log_search', '1');
        $results.attr('aria-busy', 'true');
        pending = $.ajax({url: 'ajax_scholarships.php?' + ajaxParams.toString(), method: 'GET', dataType: 'html'})
            .done(function (html) {
                $results.html(html);
                if (pushHistory) history.pushState(null, '', url);
            })
            .fail(function (xhr, status) {
                if (status !== 'abort') $results.html('<div class="alert alert-danger">Search failed. Please try again.</div>');
            })
            .always(function () { $results.removeAttr('aria-busy'); });
    }
    $form.on('submit', function (e) { e.preventDefault(); clearTimeout(timer); refresh(1, true, true); });
    $form.on('change', 'select, input[type=checkbox]', function () { clearTimeout(timer); refresh(1, true, true); });
    $form.on('input', 'input[name=q]', function () { clearTimeout(timer); timer=setTimeout(function () { refresh(1, true, true); }, 450); });
    $results.on('click', '.pagination a', function (e) {
        if ($(this).closest('.disabled').length) { e.preventDefault(); return; }
        e.preventDefault();
        const link = new URL(this.href);
        refresh(parseInt(link.searchParams.get('page') || '1', 10), false, true);
    });
    window.addEventListener('popstate', function () { window.location.reload(); });
});
