$(function () {
    const form = $('#scholarshipForm');
    if (!form.length) return;
    function showError(field, message) {
        const input = form.find('[name="' + field + '"]');
        input.addClass('is-invalid');
        let feedback = input.siblings('.invalid-feedback');
        if (!feedback.length) feedback = $('<div class="invalid-feedback"></div>').insertAfter(input);
        feedback.text(message);
    }
    function clearError(input) {
        $(input).removeClass('is-invalid').siblings('.invalid-feedback').text('');
    }
    form.on('input change', ':input', function () { clearError(this); });
    form.on('submit', function (event) {
        form.find('.is-invalid').each(function () { clearError(this); });
        let first = null;
        function invalid(field, message) {
            showError(field, message);
            if (!first) first = form.find('[name="' + field + '"]');
        }
        const name = $.trim(form.find('[name="scholarship_name"]').val() || '');
        const url = $.trim(form.find('[name="official_url"]').val() || '');
        if (!name) invalid('scholarship_name', 'Scholarship name is required.');
        else if (name.length > 255) invalid('scholarship_name', 'Name must be 255 characters or fewer.');
        if (!/^https?:\/\/[^\s]+$/i.test(url)) invalid('official_url', 'Enter an http or https URL.');
        for (const field of ['award_min', 'award_max', 'max_potential_value', 'minimum_gpa']) {
            const value = form.find('[name="' + field + '"]').val();
            if (value !== '' && (!Number.isFinite(Number(value)) || Number(value) < 0)) invalid(field, 'Enter a valid nonnegative amount.');
        }
        const min = form.find('[name="award_min"]').val(), max = form.find('[name="award_max"]').val();
        if (min !== '' && max !== '' && Number(min) > Number(max)) invalid('award_max', 'Maximum award must be at least the minimum.');
        const gpa = form.find('[name="minimum_gpa"]').val();
        if (gpa !== '' && Number(gpa) > 4) invalid('minimum_gpa', 'GPA cannot exceed 4.');
        const count = form.find('[name="number_of_awards"]').val();
        if (count !== '' && (!/^\d+$/.test(count) || Number(count) > 2147483647)) invalid('number_of_awards', 'Enter a nonnegative whole number.');
        const open = form.find('[name="application_open_date"]').val(), end = form.find('[name="application_deadline"]').val();
        if (open && end && open > end) invalid('application_deadline', 'Deadline cannot be before the opening date.');
        if (first) { event.preventDefault(); first.trigger('focus'); }
    });
});
