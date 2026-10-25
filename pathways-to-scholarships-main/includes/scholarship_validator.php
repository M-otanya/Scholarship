<?php
declare(strict_types=1);

function validate_scholarship(array $data): array
{
    $errors = [];
    $name = trim((string)($data['scholarship_name'] ?? ''));
    $url = trim((string)($data['official_url'] ?? ''));
    if ($name === '') $errors[] = 'Scholarship name is required.';
    elseif (mb_strlen($name) > 255) $errors[] = 'Scholarship name must be 255 characters or fewer.';
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        $errors[] = 'Enter a valid http or https official URL.';
    }
    foreach (['provider_id', 'state_id', 'national_theme_id'] as $key) {
        $value = $data[$key] ?? '';
        if ($value !== '' && $value !== null && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors[] = 'Invalid ' . str_replace('_', ' ', $key) . '.';
        }
    }
    if (!in_array((string)($data['scope'] ?? ''), ['', 'national', 'state/local'], true)) $errors[] = 'Invalid scholarship scope.';
    foreach (['renewable', 'financial_need_required', 'essay_required', 'recommendation_required', 'transcript_required', 'community_service_required', 'leadership_required'] as $key) {
        if (!in_array((string)($data[$key] ?? ''), ['', 'Yes', 'No', 'Unknown'], true)) $errors[] = 'Invalid ' . str_replace('_', ' ', $key) . '.';
    }
    if (!in_array((string)($data['rolling_deadline'] ?? ''), ['', '0', '1'], true)) $errors[] = 'Invalid rolling deadline.';
    foreach (['award_min', 'award_max', 'max_potential_value'] as $key) {
        $v = (string)($data[$key] ?? '');
        if ($v !== '' && (!is_numeric($v) || !is_finite((float)$v) || (float)$v < 0 || (float)$v > 9999999999.99)) {
            $errors[] = 'Invalid ' . str_replace('_', ' ', $key) . '.';
        }
    }
    $gpa = (string)($data['minimum_gpa'] ?? '');
    if ($gpa !== '' && (!is_numeric($gpa) || (float)$gpa < 0 || (float)$gpa > 4)) $errors[] = 'Minimum GPA must be between 0 and 4.';
    $min = (string)($data['award_min'] ?? '');
    $max = (string)($data['award_max'] ?? '');
    if ($min !== '' && $max !== '' && is_numeric($min) && is_numeric($max) && (float)$min > (float)$max) $errors[] = 'Minimum award cannot exceed maximum award.';
    $number = (string)($data['number_of_awards'] ?? '');
    if ($number !== '' && (!ctype_digit($number) || (float)$number > 2147483647)) $errors[] = 'Number of awards must be a nonnegative whole number.';
    foreach (['application_open_date', 'application_deadline', 'verification_date'] as $key) {
        $v = (string)($data[$key] ?? '');
        if ($v === '') continue;
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if (!$dt || $dt->format('Y-m-d') !== $v) $errors[] = 'Invalid ' . str_replace('_', ' ', $key) . '.';
    }
    $open = (string)($data['application_open_date'] ?? '');
    $deadline = (string)($data['application_deadline'] ?? '');
    if ($open !== '' && $deadline !== '' && $open > $deadline && preg_match('/^\d{4}-\d{2}-\d{2}$/', $open) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
        $errors[] = 'Application deadline must not be before the open date.';
    }
    foreach (['grades', 'categories', 'study_fields'] as $key) {
        if (isset($data[$key])) {
            if (!is_array($data[$key])) $errors[] = 'Invalid ' . $key . ' selection.';
            else $errors = array_merge($errors, array_map(static fn($e) => $key . ': ' . $e, validate_scholarship_ids($data[$key])));
        }
    }
    return array_values(array_unique($errors));
}

function validate_provider(array $data): array
{
    $errors = [];
    $name = trim((string)($data['provider_name'] ?? ''));
    $type = trim((string)($data['provider_type'] ?? ''));
    if ($name === '') $errors[] = 'Provider name is required.';
    elseif (mb_strlen($name) > 150) $errors[] = 'Provider name must be 150 characters or fewer.';
    if (mb_strlen($type) > 100) $errors[] = 'Provider type must be 100 characters or fewer.';
    return $errors;
}

function validate_scholarship_ids(mixed $ids): array
{
    if (!is_array($ids) || !$ids || count($ids) > 500) return ['Select 1 to 500 valid IDs.'];
    foreach ($ids as $id) {
        if (!is_scalar($id) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return ['Each ID must be a positive integer.'];
        }
    }
    if (count(array_unique(array_map('intval', $ids))) !== count($ids)) return ['Duplicate IDs are not allowed.'];
    return [];
}
