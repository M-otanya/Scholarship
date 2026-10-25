<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/import_validator.php';
require __DIR__ . '/../includes/security.php';
start_app_session();

$batchId = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
if (!$batchId || $batchId < 1) {
    http_response_code(400);
    exit('Invalid batch ID.');
}

// Only validated batches can be committed. This page intentionally does not
// provide a rollback action; rollback-by-batch is a separate Iteration 3 task.
$batchStmt = $pdo->prepare('SELECT batch_id, file_name, status FROM import_batches WHERE batch_id = ?');
$batchStmt->execute([$batchId]);
$batch = $batchStmt->fetch(PDO::FETCH_ASSOC);
if (!$batch) {
    http_response_code(404);
    exit('Batch not found.');
}

function commit_esc(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function commit_optional_money(array $raw, string ...$names): ?float {
    $value = import_get($raw, ...$names);
    return $value === '' ? null : import_money($value);
}

function commit_optional_date(array $raw, string ...$names): ?string {
    $value = import_get($raw, ...$names);
    return $value === '' ? null : import_date($value);
}

function commit_optional_yes_no(array $raw, string ...$names): ?string {
    $value = import_get($raw, ...$names);
    return $value === '' ? null : import_yes_no($value);
}

$error = '';
$success = isset($_GET['committed']) && $_GET['committed'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    try {
        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT status, contributing_student FROM import_batches WHERE batch_id = ? FOR UPDATE');
        $lock->execute([$batchId]);
        $lockedBatch = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$lockedBatch || $lockedBatch['status'] !== 'validated') {
            throw new RuntimeException('This batch must be validated and not previously committed.');
        }

        $rowStmt = $pdo->prepare('SELECT raw_data, row_status FROM import_staging_rows WHERE batch_id = ? ORDER BY staging_id');
        $rowStmt->execute([$batchId]);
        $rows = $rowStmt->fetchAll(PDO::FETCH_ASSOC);
        $eligible = array_values(array_filter($rows, static fn(array $r): bool => in_array($r['row_status'], ['Ready', 'Warning'], true)));
        if (!$eligible) throw new RuntimeException('No Ready or Warning rows are available to commit.');

        $stateCodes = ['national' => 'US', 'us' => 'US'];
        $stateIds = [];
        foreach ($pdo->query('SELECT state_id, name, abbreviation FROM states') as $s) {
            $code = strtoupper((string)$s['abbreviation']);
            $stateCodes[strtolower(trim((string)$s['name']))] = $code;
            $stateCodes[strtolower($code)] = $code;
            $stateIds[$code] = (int)$s['state_id'];
        }
        if (!isset($stateIds['US'])) throw new RuntimeException('National (US) is missing from the states table.');

        $gradeIds = [];
        foreach ($pdo->query('SELECT grade_id, grade_number FROM grade_levels') as $g) {
            $gradeIds[(int)$g['grade_number']] = (int)$g['grade_id'];
        }

        $findProvider = $pdo->prepare('SELECT provider_id FROM providers WHERE provider_name = ? LIMIT 1');
        $addProvider = $pdo->prepare('INSERT INTO providers (provider_name, provider_type) VALUES (?, ?)');
        $updateProviderType = $pdo->prepare("UPDATE providers SET provider_type = ? WHERE provider_id = ? AND (provider_type IS NULL OR provider_type = '')");
        $findCategory = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ? LIMIT 1');
        $addCategory = $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)');
        $insertCategory = $pdo->prepare('INSERT INTO scholarship_categories (scholarship_id, category_id) VALUES (?, ?)');
        $findField = $pdo->prepare('SELECT field_id FROM fields_of_study WHERE field_name = ? LIMIT 1');
        $addField = $pdo->prepare('INSERT INTO fields_of_study (field_name) VALUES (?)');
        $insertField = $pdo->prepare('INSERT INTO scholarship_fields (scholarship_id, field_id) VALUES (?, ?)');
        $findExisting = $pdo->prepare('SELECT scholarship_id FROM scholarships WHERE normalized_url = ? LIMIT 1');
        $insert = $pdo->prepare('INSERT INTO scholarships (
            scholarship_name, provider_id, state_id, import_batch_id, official_url,
            normalized_url, discovery_source, scope, description, award_min, award_max, number_of_awards,
            renewable, max_potential_value, application_open_date, application_deadline,
            rolling_deadline, residency_requirement, minimum_gpa, financial_need_required,
            essay_required, recommendation_required, transcript_required,
            community_service_required, leadership_required, eligibility_requirements,
            application_method, notes, contributing_student, verification_date
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )');
        $insertGrade = $pdo->prepare('INSERT INTO scholarship_grades (scholarship_id, grade_id) VALUES (?, ?)');

        $seen = [];
        $inserted = 0;
        foreach ($eligible as $row) {
            $raw = json_decode((string)$row['raw_data'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($raw)) throw new RuntimeException('Invalid staged scholarship data.');

            // Re-run the same cleaning and required-field validation at commit time.
            $checked = import_validate_row($raw, $stateCodes, [], $seen);
            if (in_array($checked['status'], ['Error', 'Duplicate'], true)) {
                throw new RuntimeException('A staged row is no longer valid. Validate the batch again before committing.');
            }
            $clean = $checked['cleaned'];
            $url = import_get($raw, 'Official scholarship URL', 'official_url', 'url');
            $normalized = $clean['_normalized_url'];
            $stateCode = $clean['_state_code'] ?? null;
            $grades = $clean['_grades_detected'] ?? [];
            if (!$normalized || !$stateCode || !isset($stateIds[$stateCode]) || !$grades) {
                throw new RuntimeException('A required cleaned value is missing.');
            }

            $findExisting->execute([$normalized]);
            if ($findExisting->fetchColumn() !== false) {
                throw new RuntimeException('A scholarship URL already exists in the database. Revalidate this batch before committing.');
            }

            $providerName = import_get($raw, 'Scholarship provider/organization', 'provider', 'provider_name');
            $findProvider->execute([$providerName]);
            $providerId = $findProvider->fetchColumn();
            if ($providerId === false) {
                $addProvider->execute([$providerName, import_get($raw, 'Provider type') ?: null]);
                $providerId = (int)$pdo->lastInsertId();
            } else {
                $providerType = import_get($raw, 'Provider type');
                if ($providerType !== '') $updateProviderType->execute([$providerType, (int)$providerId]);
            }

            $deadlineText = import_get($raw, 'Application deadline');
            $rolling = preg_match('/^rolling(?: deadline)?$/i', $deadlineText) === 1;
            $deadline = $rolling ? null : commit_optional_date($raw, 'Application deadline');
            $award = commit_optional_money($raw, 'Award amount');
            $insert->execute([
                import_get($raw, 'Scholarship name', 'scholarship_name', 'name'),
                (int)$providerId,
                $stateIds[$stateCode],
                $batchId,
                $url,
                $normalized,
                import_get($raw, 'Discovery source') ?: null,
                $clean['_scope'],
                import_get($raw, 'Description') ?: null,
                $award,
                $award,
                (($n = import_get($raw, 'Number of awards')) !== '' && ctype_digit($n)) ? (int)$n : null,
                commit_optional_yes_no($raw, 'Renewable'),
                commit_optional_money($raw, 'Maximum potential value'),
                commit_optional_date($raw, 'Application open date'),
                $deadline,
                $rolling ? 1 : 0,
                import_get($raw, 'Residency requirement', 'Residency requirements') ?: null,
                (($gpa = import_get($raw, 'Minimum GPA')) !== '' && is_numeric($gpa)) ? (float)$gpa : null,
                commit_optional_yes_no($raw, 'Financial need required'),
                commit_optional_yes_no($raw, 'Essay required'),
                commit_optional_yes_no($raw, 'Recommendation required'),
                commit_optional_yes_no($raw, 'Transcript/test score required'),
                commit_optional_yes_no($raw, 'Community service requirement'),
                commit_optional_yes_no($raw, 'Leadership requirement'),
                import_get($raw, 'Other major eligibility requirements', 'Eligibility requirements') ?: null,
                import_get($raw, 'Application method') ?: null,
                import_get($raw, 'Notes') ?: null,
                $lockedBatch['contributing_student'],
                commit_optional_date($raw, 'Verification date'),
            ]);
            $scholarshipId = (int)$pdo->lastInsertId();
            foreach ($grades as $grade) {
                if (!isset($gradeIds[$grade])) throw new RuntimeException('Missing grade reference: ' . $grade);
                $insertGrade->execute([$scholarshipId, $gradeIds[$grade]]);
            }
            // Save category and field-of-study relationships using existing tables.
            foreach ([
                [import_get($raw, 'Scholarship category'), $findCategory, $addCategory, $insertCategory],
                [import_get($raw, 'Field/area of interest'), $findField, $addField, $insertField],
            ] as [$cell, $findRef, $addRef, $linkRef]) {
                $linked = [];
                foreach (preg_split('/[,;]+/', $cell) as $part) {
                    $label = import_clean_text($part);
                    if ($label === '') continue;
                    $findRef->execute([$label]);
                    $referenceId = $findRef->fetchColumn();
                    if ($referenceId === false) {
                        $addRef->execute([$label]);
                        $referenceId = (int)$pdo->lastInsertId();
                    }
                    if (!isset($linked[(int)$referenceId])) {
                        $linkRef->execute([$scholarshipId, (int)$referenceId]);
                        $linked[(int)$referenceId] = true;
                    }
                }
            }
            $inserted++;
        }

        $updateBatch = $pdo->prepare("UPDATE import_batches SET status = 'committed' WHERE batch_id = ?");
        $updateBatch->execute([$batchId]);
        $pdo->commit();
        header('Location: commit.php?batch_id=' . $batchId . '&committed=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Import commit batch ' . $batchId . ': ' . $e->getMessage());
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Commit failed. No scholarships were saved. Check the PHP error log.';
    }
}

$countStmt = $pdo->prepare("SELECT row_status, COUNT(*) AS total FROM import_staging_rows WHERE batch_id = ? GROUP BY row_status");
$countStmt->execute([$batchId]);
$counts = ['Ready' => 0, 'Warning' => 0, 'Duplicate' => 0, 'Error' => 0];
foreach ($countStmt as $r) $counts[$r['row_status']] = (int)$r['total'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-5" style="max-width:850px">
    <h1>Commit scholarship batch</h1>
    <p><strong>File:</strong> <?= commit_esc($batch['file_name']) ?> | <strong>Batch:</strong> <?= (int)$batchId ?></p>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= commit_esc($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">Batch committed successfully.</div><?php endif; ?>
    <p>Ready: <?= $counts['Ready'] ?> | Warning: <?= $counts['Warning'] ?> | Duplicate: <?= $counts['Duplicate'] ?> | Error: <?= $counts['Error'] ?></p>
    <p>Only Ready and Warning rows will be published. Duplicate and Error rows remain in staging.</p>
    <?php if ($batch['status'] === 'validated' && $counts['Ready'] + $counts['Warning'] > 0): ?>
        <form method="post">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">Confirm commit</button>
        </form>
    <?php else: ?>
        <p>This batch is not available for commit. Validate it first, or it has already been committed.</p>
    <?php endif; ?>
    <p class="mt-3"><a href="validate.php?batch_id=<?= (int)$batchId ?>">Back to staging review</a></p>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
