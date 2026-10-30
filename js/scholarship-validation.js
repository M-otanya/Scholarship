
$(function () {
    const form = $('#scholarshipForm');
    if (!form.length) return;

    function showError(field, message) {
        const input = form.find('[name="' + field + '"]');
        if (!input.length) return;

        input.addClass('is-invalid');

        let feedback = input.siblings('.invalid-feedback');

        if (!feedback.length) {
            feedback = $('<div class="invalid-feedback"></div>');
            feedback.insertAfter(input);
        }

        feedback.text(message);
    }

    function clearError(input) {
        $(input).removeClass('is-invalid');
        $(input).siblings('.invalid-feedback').text('');
    }

    form.on('input change', ':input', function () {
        clearError(this);
    });

    form.on('submit', function (event) {
        form.find('.is-invalid').each(function () {
            clearError(this);
        });

        let first = null;

        function invalid(field, message) {
            showError(field, message);

            if (!first) {
                first = form.find('[name="' + field + '"]');
            }
        }

        const name = $.trim(
            form.find('[name="scholarship_name"]').val() || ''
        );

        const url = $.trim(
            form.find('[name="official_url"]').val() || ''
        );

        // Required scholarship name
        if (!name) {
            invalid('scholarship_name', 'Scholarship name is required.');
        } else if (name.length > 255) {
            invalid('scholarship_name', 'Name must be 255 characters or fewer.');
        }

        // Official URL validation
        let validUrl = false;

        try {
            const parsed = new URL(url);
            validUrl = ['http:', 'https:'].includes(parsed.protocol)
                && Boolean(parsed.hostname);
        } catch (e) {
            validUrl = false;
        }

        if (!validUrl) {
            invalid('official_url', 'Enter a valid http or https official URL.');
        }

        // Nonnegative numeric fields
        for (const field of [
            'award_min',
            'award_max',
            'max_potential_value',
            'minimum_gpa'
        ]) {
            const value = form.find('[name="' + field + '"]').val();

            if (value !== '' &&
                (!Number.isFinite(Number(value)) || Number(value) < 0)) {
                invalid(field, 'Enter a valid nonnegative number.');
            }
        }

        // Minimum and maximum award
        const min = form.find('[name="award_min"]').val();
        const max = form.find('[name="award_max"]').val();

        if (min !== '' && max !== '' && Number(min) > Number(max)) {
            invalid('award_max', 'Maximum award must be at least the minimum.');
        }

        // GPA validation
        const gpa = form.find('[name="minimum_gpa"]').val();

        if (gpa !== '' && Number(gpa) > 4) {
            invalid('minimum_gpa', 'GPA cannot exceed 4.');
        }

        // Number of awards
        const count = form.find('[name="number_of_awards"]').val();

        if (count !== '' &&
            (!/^\d+$/.test(count) || Number(count) > 2147483647)) {
            invalid('number_of_awards', 'Enter a nonnegative whole number.');
        }

        // Application dates
        const open = form.find('[name="application_open_date"]').val();
        const end = form.find('[name="application_deadline"]').val();

        if (open && end && open > end) {
            invalid('application_deadline', 'Deadline cannot be before the opening date.');
        }

        // Stop submission if validation fails
        if (first && first.length) {
            event.preventDefault();
            first.trigger('focus');
        }
    });
});
