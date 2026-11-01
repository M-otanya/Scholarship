<?php
declare(strict_types=1);

// Scholarship Detail Page - Iteration 5
require_once __DIR__ . '/includes/db.php';

// Escape output to prevent HTML injection.
function detail_h($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Format a date for display.
function detail_date($value): string
{
    if (empty($value)) {
        return 'Not specified';
    }

    $date = strtotime((string)$value);

    return $date
        ? date('F j, Y', $date)
        : 'Not specified';
}

// Format a monetary award.
function detail_award($min, $max): string
{
    if ($min === null && $max === null) {
        return 'Not specified';
    }

    if ($min !== null && $max !== null) {
        if ((float)$min !== (float)$max) {
            return '$' . number_format((float)$min)
                . ' - $' . number_format((float)$max);
        }
    }

    return '$' . number_format((float)($max ?? $min));
}

// Validate scholarship ID from the URL.
$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$scholarship = null;
$grades = [];
$fields = [];
$categories = [];
$error = '';

if (!$id) {
    http_response_code(400);
    $error = 'Invalid scholarship ID.';
} else {
    try {

        // Retrieve scholarship information.
        $sql = "
            SELECT
                s.*,
                p.provider_name,
                st.name AS state_name,
                nt.theme_name
            FROM scholarships s
            LEFT JOIN providers p
                ON s.provider_id = p.provider_id
            LEFT JOIN states st
                ON s.state_id = st.state_id
            LEFT JOIN national_themes nt
                ON s.national_theme_id = nt.national_theme_id
            WHERE s.scholarship_id = :id
              AND s.status = 'active'
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$scholarship) {
            http_response_code(404);
            $error = 'Scholarship not found.';
        } else {

            // Retrieve eligible grade levels.
            $stmt = $pdo->prepare("
                SELECT g.grade_number
                FROM scholarship_grades sg
                JOIN grade_levels g
                    ON sg.grade_id = g.grade_id
                WHERE sg.scholarship_id = ?
                ORDER BY g.grade_number
            ");

            $stmt->execute([$id]);
            $grades = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Retrieve fields of study.
            $stmt = $pdo->prepare("
                SELECT f.field_name
                FROM scholarship_fields sf
                JOIN fields_of_study f
                    ON sf.field_id = f.field_id
                WHERE sf.scholarship_id = ?
                ORDER BY f.field_name
            ");

            $stmt->execute([$id]);
            $fields = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Retrieve scholarship categories.
            $stmt = $pdo->prepare("
                SELECT c.category_name
                FROM scholarship_categories sc
                JOIN categories c
                    ON sc.category_id = c.category_id
                WHERE sc.scholarship_id = ?
                ORDER BY c.category_name
            ");

            $stmt->execute([$id]);
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

    } catch (PDOException $e) {
        error_log($e->getMessage());
        http_response_code(500);
        $error = 'Unable to load scholarship details.';
    }
}
?>

<?php require __DIR__ . '/includes/header.php'; ?>
<?php require __DIR__ . '/includes/nav.php'; ?>

<main class="container py-5">

    <a href="scholarships.php"
       class="btn btn-outline-secondary mb-4">
        &larr; Back to Scholarships
    </a>

    <?php if ($error): ?>

        <div class="alert alert-warning">
            <?= detail_h($error) ?>
        </div>

    <?php else: ?>

        <div class="card shadow-sm">

            <div class="card-header bg-primary text-white">
                <h1 class="h3 mb-0">
                    <?= detail_h($scholarship['scholarship_name']) ?>
                </h1>
            </div>

            <div class="card-body">

                <?php if (
                    ($scholarship['discovery_source'] ?? '') === 'Sample Data'
                ): ?>

                    <div class="alert alert-info">
                        This is sample scholarship data for testing.
                    </div>

                <?php endif; ?>

                <!-- Scholarship Information -->
                <h2 class="h5">Scholarship Information</h2>

                <p>
                    <strong>Provider:</strong>
                    <?= detail_h(
                        $scholarship['provider_name'] ?: 'Not specified'
                    ) ?>
                </p>

                <p>
                    <strong>Description:</strong>
                </p>

                <p>
                    <?= nl2br(detail_h(
                        $scholarship['description']
                        ?: 'No description available.'
                    )) ?>
                </p>

                <hr>

                <!-- Award Information -->
                <h2 class="h5">Award Information</h2>

                <p>
                    <strong>Award Amount:</strong>
                    <?= detail_award(
                        $scholarship['award_min'] ?? null,
                        $scholarship['award_max'] ?? null
                    ) ?>
                </p>

                <p>
                    <strong>Number of Awards:</strong>
                    <?= detail_h(
                        $scholarship['number_of_awards'] ?? 'Not specified'
                    ) ?>
                </p>

                <p>
                    <strong>Application Deadline:</strong>

                    <?php if (!empty($scholarship['rolling_deadline'])): ?>
                        Rolling deadline
                    <?php else: ?>
                        <?= detail_date(
                            $scholarship['application_deadline'] ?? null
                        ) ?>
                    <?php endif; ?>
                </p>

                <hr>

                <!-- Eligibility Requirements -->
                <h2 class="h5">Eligibility Requirements</h2>

                <p>
                    <strong>State:</strong>
                    <?= detail_h(
                        $scholarship['state_name'] ?: 'Not specified'
                    ) ?>
                </p>

                <p>
                    <strong>Scope:</strong>
                    <?= detail_h(
                        $scholarship['scope'] ?: 'Not specified'
                    ) ?>
                </p>

                <p>
                    <strong>Grade Levels:</strong>
                    <?= $grades
                        ? detail_h(implode(', ', $grades))
                        : 'Not specified' ?>
                </p>

                <p>
                    <strong>Fields of Study:</strong>
                    <?= $fields
                        ? detail_h(implode(', ', $fields))
                        : 'Not specified' ?>
                </p>

                <p>
                    <strong>Categories:</strong>
                    <?= $categories
                        ? detail_h(implode(', ', $categories))
                        : 'Not specified' ?>
                </p>

                <p>
                    <strong>Minimum GPA:</strong>
                    <?= detail_h(
                        $scholarship['minimum_gpa'] ?? 'Not specified'
                    ) ?>
                </p>

                <p>
                    <strong>Additional Requirements:</strong>
                </p>

                <p>
                    <?= nl2br(detail_h(
                        $scholarship['eligibility_requirements']
                        ?: 'No additional requirements specified.'
                    )) ?>
                </p>

                <hr>

                <!-- Application Information -->
                <h2 class="h5">Application Information</h2>

                <p>
                    <strong>Application Method:</strong>
                    <?= detail_h(
                        $scholarship['application_method']
                        ?: 'Not specified'
                    ) ?>
                </p>

                <p>
                    <strong>Last Verified:</strong>
                    <?= detail_date(
                        $scholarship['verification_date'] ?? null
                    ) ?>
                </p>

                <!-- Display stored official URL -->
                <p>
                    <strong>Official URL:</strong>

                    <?php
                    $officialUrl = $scholarship['official_url'] ?? '';
                    ?>

                    <?php if ($officialUrl): ?>
                        <?= detail_h($officialUrl) ?>
                    <?php else: ?>
                        Not specified
                    <?php endif; ?>
                </p>

                <!-- Demo Application Button -->
                <a href="application_placeholder.php"
                   class="btn btn-primary mt-3">
                    Visit Official Scholarship Website
                </a>

            </div>

        </div>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
