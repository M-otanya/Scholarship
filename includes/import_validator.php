<?php
declare(strict_types=1);

function import_clean_text(mixed $value): string {
    $value = str_replace("\xC2\xA0", ' ', (string)$value);
    return trim((string)preg_replace('/\s+/u', ' ', $value));
}
function import_key(string $key): string {
    return strtolower((string)preg_replace('/[^a-z0-9]+/', '', strtolower($key)));
}
function import_get(array $row, string ...$names): string {
    $indexed = [];
    foreach ($row as $k => $v) {
        if (is_array($v)) continue;
        $indexed[import_key((string)$k)] = import_clean_text($v);
    }
    foreach ($names as $name) if (array_key_exists(import_key($name), $indexed)) return $indexed[import_key($name)];
    return '';
}
function import_url(string $url): ?string {
    if ($url === '') return null;
    if (!preg_match('~^https?://~i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) return null;
    $p = parse_url($url);
    if (!$p || empty($p['host']) || !in_array(strtolower($p['scheme']), ['http','https'], true)) return null;
    $host = strtolower($p['host']);
    $path = rtrim($p['path'] ?? '', '/');
    $query = [];
    if (!empty($p['query'])) {
        parse_str($p['query'], $query);
        foreach (array_keys($query) as $k) if (preg_match('/^(utm_|fbclid$|gclid$)/i', (string)$k)) unset($query[$k]);
        ksort($query);
    }
    return $host . $path . ($query ? '?' . http_build_query($query) : '');
}
function import_money(string $value): ?float {
    if ($value === '') return null;
    $clean = preg_replace('/[,$\s]/', '', $value);
    if (!preg_match('/^\$?\d+(?:\.\d{1,2})?$/', $clean)) return null;
    return (float)ltrim($clean, '$');
}
function import_date(string $value): ?string {
    if ($value === '') return null;
    foreach (['Y-m-d', 'm/d/Y', 'n/j/Y', 'm-d-Y', 'n-j-Y', 'M j, Y', 'F j, Y'] as $format) {
        $dt = DateTimeImmutable::createFromFormat('!' . $format, $value);
        $issues = DateTimeImmutable::getLastErrors();
        if ($dt && (!$issues || ($issues['warning_count'] === 0 && $issues['error_count'] === 0))
            && $dt->format($format) === $value) return $dt->format('Y-m-d');
    }
    return null;
}
function import_grades(string $value): ?array {
    $value = strtolower(trim($value));
    $value = preg_replace('/\b(?:grades?|th|st|nd|rd)\b/i', ' ', $value);
    $value = preg_replace('/(?<=\d)(?:st|nd|rd|th)\b/i', '', $value);
    $value = str_replace(['–', '—', ' to '], '-', $value);
    $parts = preg_split('/\s*[,;\/]|\s+and\s+/', $value);
    $found = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        if (preg_match('/^(\d{1,2})\s*-\s*(\d{1,2})$/', $part, $m)) {
            $start = (int)$m[1]; $end = (int)$m[2];
            if ($start < 6 || $end > 12 || $start > $end) return null;
            foreach (range($start, $end) as $grade) $found[$grade] = $grade;
        } elseif (preg_match('/^\d{1,2}$/', $part)) {
            $grade = (int)$part;
            if ($grade < 6 || $grade > 12) return null;
            $found[$grade] = $grade;
        } else return null;
    }
    if (!$found) return null;
    ksort($found);
    return array_values($found);
}
function import_residency_states(string $value, array $stateCodes): array {
    // Recognize explicitly listed state names/codes, not free-form eligibility prose.
    $found = [];
    $parts = preg_split('/\s*[,;\/|]\s*|\s+and\s+/i', $value);
    foreach ($parts as $part) {
        $part = strtolower(trim($part));
        if (isset($stateCodes[$part]) && $stateCodes[$part] !== 'US') $found[$stateCodes[$part]] = true;
    }
    return array_keys($found);
}
function import_yes_no(string $value): ?string {
    return match(strtolower($value)) {
        'y','yes','true','1' => 'Yes', 'n','no','false','0' => 'No', 'u','unknown','' => 'Unknown', default => null
    };
}
function import_validate_row(array $raw, array $stateCodes, array $existingUrls, array &$seenUrls): array {
    $clean = [];
    foreach ($raw as $key => $value) $clean[$key] = import_clean_text($value);
    $errors = []; $warnings = []; $duplicate = false;
    $name = import_get($clean, 'Scholarship name', 'scholarship_name', 'name');
    $provider = import_get($clean, 'Scholarship provider/organization', 'provider', 'provider_name');
    $url = import_get($clean, 'Official scholarship URL', 'official_url', 'url');
    $state = import_get($clean, 'State/region', 'state');
    $scope = strtolower(import_get($clean, 'National vs. state/local', 'scope'));
    $grades = import_get($clean, 'Eligible grade levels', 'grades');
    if ($name === '') $errors[] = 'Missing scholarship name';
    if ($provider === '') $errors[] = 'Missing scholarship provider';
    $normalized = import_url($url);
    if ($url === '') $errors[] = 'Missing official URL';
    elseif ($normalized === null) $errors[] = 'Invalid official URL (must start with http:// or https://)';
    elseif (isset($seenUrls[$normalized]) || isset($existingUrls[$normalized])) {
        $duplicate = true;
        $warnings[] = 'Duplicate official URL (already staged or in scholarships table)';
    }
    if ($normalized !== null) $seenUrls[$normalized] = true;
    $clean['_normalized_url'] = $normalized;
    $residency = import_get($clean, 'Residency requirement', 'Residency requirements');
    $residencyStates = import_residency_states($residency, $stateCodes);
    if ($state === '') $errors[] = 'Missing state/region';
    else {
        $mapped = $stateCodes[strtolower($state)] ?? null;
        if ($mapped === null) $errors[] = 'Unrecognized state/region: ' . $state;
        else $clean['_state_code'] = $mapped;
    }
    if ($scope === '') $errors[] = 'Missing national vs. state/local classification';
    elseif (!in_array($scope, ['national','state','local','state/local','state / local'], true)) $errors[] = 'Invalid geographic classification';
    else {
        $clean['_scope'] = $scope === 'national' ? 'national' : 'state/local';
        if (count($residencyStates) >= 2 && $clean['_scope'] !== 'national') {
            $clean['_scope'] = 'national';
            $clean['_state_code'] = 'US';
            $warnings[] = 'Reclassified as national: residency includes two or more states';
        } elseif ($clean['_scope'] === 'national') {
            $clean['_state_code'] = 'US';
        } elseif (($clean['_state_code'] ?? '') === 'US') {
            $errors[] = 'State/local scholarship must specify a state';
        }
    }
    if ($grades === '') $errors[] = 'Missing eligible grade levels';
    else {
        $parsedGrades = import_grades($grades);
        if ($parsedGrades === null) $errors[] = 'Invalid eligible grade levels (use grades 6–12, e.g. 9-12)';
        else $clean['_grades_detected'] = $parsedGrades;
    }
    foreach (['Award amount','Maximum potential value'] as $field) {
        $v = import_get($clean, $field);
        if ($v !== '') {
            $money = import_money($v);
            if ($money === null) $warnings[] = 'Invalid amount: ' . $field;
            else $clean['_' . import_key($field)] = $money;
        }
    }
    foreach (['Renewable','Financial need required','Essay required','Recommendation required','Transcript/test score required','Community service requirement','Leadership requirement'] as $field) {
        $v = import_get($clean, $field);
        $yn = import_yes_no($v);
        if ($yn === null) $warnings[] = 'Invalid Yes/No/Unknown value: ' . $field;
        else $clean['_' . import_key($field)] = $yn;
    }
    foreach (['Application open date','Application deadline','Verification date'] as $field) {
        $v = import_get($clean, $field);
        if ($v === '') continue;
        if ($field === 'Application deadline' && preg_match('/^rolling(?: deadline)?$/i', $v)) {
            $clean['_rolling_deadline'] = true; continue;
        }
        $date = import_date($v);
        if ($date === null) $warnings[] = 'Invalid date: ' . $field;
        else {
            $clean['_' . import_key($field)] = $date;
            if ($field === 'Application deadline' && $date < date('Y-m-d')) $warnings[] = 'Application deadline has passed';
        }
    }
    $status = $errors ? 'Error' : ($duplicate ? 'Duplicate' : ($warnings ? 'Warning' : 'Ready'));
    return ['status'=>$status, 'messages'=>array_merge($errors, $warnings), 'cleaned'=>$clean];
}
function import_validate_batch(PDO $pdo, int $batchId): array {
    $stateCodes = ['national'=>'US','us'=>'US'];
    foreach ($pdo->query('SELECT name, abbreviation FROM states') as $state) {
        $stateCodes[strtolower(trim($state['name']))] = strtoupper($state['abbreviation']);
        $stateCodes[strtolower(trim($state['abbreviation']))] = strtoupper($state['abbreviation']);
    }
    $existingUrls = [];
    foreach ($pdo->query("SELECT normalized_url FROM scholarships WHERE normalized_url IS NOT NULL AND normalized_url <> ''") as $row) {
        $url = import_url('https://' . preg_replace('~^https?://~i', '', $row['normalized_url']));
        if ($url !== null) $existingUrls[$url] = true;
    }
    $stmt = $pdo->prepare('SELECT staging_id, raw_data FROM import_staging_rows WHERE batch_id = ? ORDER BY staging_id');
    $stmt->execute([$batchId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $counts = ['Ready'=>0,'Warning'=>0,'Duplicate'=>0,'Error'=>0];
    $seen = []; $results = [];
    foreach ($rows as $row) {
        $raw = json_decode($row['raw_data'], true);
        $result = is_array($raw) ? import_validate_row($raw, $stateCodes, $existingUrls, $seen) : ['status'=>'Error','messages'=>['Invalid stored JSON'],'cleaned'=>[]];
        $counts[$result['status']]++;
        $results[(int)$row['staging_id']] = $result;
    }
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE import_staging_rows SET row_status = ?, error_message = ? WHERE staging_id = ? AND batch_id = ?');
        foreach ($results as $id => $result) $update->execute([$result['status'], implode('; ', $result['messages']), $id, $batchId]);
        $batch = $pdo->prepare("UPDATE import_batches SET total_rows=?, ready_rows=?, warning_rows=?, duplicate_rows=?, error_rows=?, status='validated' WHERE batch_id=?");
        $batch->execute([count($rows), $counts['Ready'], $counts['Warning'], $counts['Duplicate'], $counts['Error'], $batchId]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    return $counts;
}
