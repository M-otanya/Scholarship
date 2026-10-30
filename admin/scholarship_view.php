<?php
declare(strict_types=1);
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/auth.php';
start_app_session();
require_role(['Administrator', 'Super-admin']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(400);
    exit('Invalid scholarship ID.');
}

try {
    $stmt = $pdo->prepare('SELECT s.*, p.provider_name, st.name AS state_name, nt.theme_name, b.file_name AS import_file
        FROM scholarships s
        LEFT JOIN providers p ON p.provider_id = s.provider_id
        LEFT JOIN states st ON st.state_id = s.state_id
        LEFT JOIN national_themes nt ON nt.national_theme_id = s.national_theme_id
        LEFT JOIN import_batches b ON b.batch_id = s.import_batch_id
        WHERE s.scholarship_id = ?');
    $stmt->execute([$id]);
    $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$scholarship) {
        http_response_code(404);
        exit('Scholarship not found.');
    }
    $relations = [
        'Eligible grades' => ['scholarship_grades', 'grade_levels', 'grade_id', 'grade_number'],
        'Categories' => ['scholarship_categories', 'categories', 'category_id', 'category_name'],
        'Fields of study' => ['scholarship_fields', 'fields_of_study', 'field_id', 'field_name'],
    ];
    $selected = [];
    foreach ($relations as $label => [$bridge, $lookup, $key, $name]) {
        $q = $pdo->prepare("SELECT l.$name FROM $bridge r JOIN $lookup l ON l.$key = r.$key WHERE r.scholarship_id = ? ORDER BY l.$name");
        $q->execute([$id]);
        $selected[$label] = $q->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to load scholarship details. Check the database schema.');
}

$details = [
    'Scholarship name' => $scholarship['scholarship_name'],
    'Provider' => $scholarship['provider_name'],
    'Status' => $scholarship['status'],
    'Verification date' => $scholarship['verification_date'],
    'State' => $scholarship['state_name'],
    'Scope' => $scholarship['scope'],
    'National theme' => $scholarship['theme_name'],
    'Description' => $scholarship['description'],
    'Official URL' => $scholarship['official_url'],
    'Discovery source' => $scholarship['discovery_source'],
    'Minimum award' => $scholarship['award_min'],
    'Maximum award' => $scholarship['award_max'],
    'Number of awards' => $scholarship['number_of_awards'],
    'Renewable' => $scholarship['renewable'],
    'Maximum potential value' => $scholarship['max_potential_value'],
    'Application opens' => $scholarship['application_open_date'],
    'Application deadline' => $scholarship['application_deadline'],
    'Rolling deadline' => $scholarship['rolling_deadline'] ? 'Yes' : 'No',
    'Residency requirement' => $scholarship['residency_requirement'],
    'Minimum GPA' => $scholarship['minimum_gpa'],
    'Financial need required' => $scholarship['financial_need_required'],
    'Essay required' => $scholarship['essay_required'],
    'Recommendation required' => $scholarship['recommendation_required'],
    'Transcript required' => $scholarship['transcript_required'],
    'Community service required' => $scholarship['community_service_required'],
    'Leadership required' => $scholarship['leadership_required'],
    'Eligibility requirements' => $scholarship['eligibility_requirements'],
    'Application method' => $scholarship['application_method'],
    'Notes' => $scholarship['notes'],
    'Contributing student' => $scholarship['contributing_student'],
    'Import batch' => $scholarship['import_batch_id'],
    'Import file' => $scholarship['import_file'],
    'Created at' => $scholarship['created_at'],
    'Updated at' => $scholarship['updated_at'],
];
foreach ($selected as $label => $values) {
    $details[$label] = implode(', ', $values);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($scholarship['scholarship_name']) ?> | Scholarship Details</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-primary"><div class="container"><a class="navbar-brand" href="../index.php">Pathways to Scholarships</a></div></nav>
<main class="container py-4">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <a href="scholarships.php" class="btn btn-outline-secondary">&larr; Back to scholarships</a>
  <a href="scholarship_form.php?id=<?= (int)$id ?>" class="btn btn-primary">Edit scholarship</a>
</div>
<h1 class="h3 mb-3"><?= h($scholarship['scholarship_name']) ?></h1>
<div class="table-responsive"><table class="table table-striped table-bordered"><tbody>
<?php foreach ($details as $label => $value): ?>
<tr><th scope="row" style="width:30%" class="align-top"><?= h($label) ?></th><td style="white-space:pre-wrap;overflow-wrap:anywhere"><?php if ($label === 'Official URL' && is_string($value) && filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($value, PHP_URL_SCHEME)), ['https', 'http'], true)): ?><a href="<?= h($value) ?>" target="_blank" rel="noopener noreferrer"><?= h($value) ?></a><?php else: ?><?= $value === null || $value === '' ? '—' : h($value) ?><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</main>
</body></html>
