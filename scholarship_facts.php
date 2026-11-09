<?php
declare(strict_types=1);

/**
 * Iteration 6 - Public Scholarship Facts Page
 *
 * Displays scholarship statistics retrieved
 * from the existing MySQL database.
 */

require_once __DIR__ . '/includes/scholarship_stats.php';

// Initialize statistics.
$summary = [];
$states = [];
$themes = [];
$scopes = [];
$deadlines = [];
$error = null;

// Retrieve actual database statistics.
try {
    $summary = getScholarshipSummary($pdo);
    $states = getScholarshipsByState($pdo);
    $themes = getScholarshipsByTheme($pdo);
    $scopes = getScholarshipsByScope($pdo);
    $deadlines = getUpcomingDeadlines($pdo);

} catch (PDOException $e) {
    error_log('Scholarship Facts: ' . $e->getMessage());
    $error = 'Unable to load scholarship statistics.';
}

// Escape output to prevent HTML injection.
function facts_h($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>

<main class="container my-5">

    <h1>Scholarship Facts</h1>

    <p class="text-muted">
        Explore scholarship statistics, distribution,
        and upcoming application deadlines.
        Information is retrieved from the
        scholarship database.
    </p>

    <?php if ($error): ?>

        <div class="alert alert-danger" role="alert">
            <?= facts_h($error) ?>
        </div>

    <?php else: ?>

        <!-- Scholarship Summary Cards -->
        <section class="mb-5">

            <h2 class="h4 mb-3">Scholarship Overview</h2>

            <div class="row g-3">

                <?php
                $cards = [
                    'Total Scholarships' =>
                        $summary['total_scholarships'] ?? 0,

                    'Active Scholarships' =>
                        $summary['active_scholarships'] ?? 0,

                    'National Scholarships' =>
                        $summary['national_scholarships'] ?? 0,

                    'State / Local Scholarships' =>
                        $summary['state_scholarships'] ?? 0
                ];
                ?>

                <?php foreach ($cards as $label => $count): ?>

                    <div class="col-md-6 col-lg-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body text-center">

                                <h3 class="h6 text-muted">
                                    <?= facts_h($label) ?>
                                </h3>

                                <p class="display-6 fw-bold mb-0">
                                    <?= (int) $count ?>
                                </p>

                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- Scholarships by Scope -->
        <section class="mb-5">

            <h2 class="h4">Scholarships by Scope</h2>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>Scope</th>
                            <th>Scholarship Count</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($scopes as $scope): ?>

                            <tr>
                                <td>
                                    <?= facts_h($scope['scope']) ?>
                                </td>

                                <td>
                                    <?= (int) $scope['total'] ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($scopes)): ?>
                            <tr>
                                <td colspan="2">
                                    No scholarship data available.
                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>

                </table>
            </div>

        </section>


        <!-- Scholarships by State -->
        <section class="mb-5">

            <h2 class="h4">Scholarships by State</h2>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>State</th>
                            <th>Scholarship Count</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($states as $state): ?>

                            <tr>
                                <td>
                                    <?= facts_h($state['state_name']) ?>
                                </td>

                                <td>
                                    <?= (int) $state['total'] ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($states)): ?>
                            <tr>
                                <td colspan="2">
                                    No state scholarship data available.
                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- Scholarships by National Theme -->
        <section class="mb-5">

            <h2 class="h4">Scholarships by National Theme</h2>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>National Theme</th>
                            <th>Scholarship Count</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($themes as $theme): ?>

                            <tr>
                                <td>
                                    <?= facts_h($theme['theme_name']) ?>
                                </td>

                                <td>
                                    <?= (int) $theme['total'] ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($themes)): ?>
                            <tr>
                                <td colspan="2">
                                    No national theme data available.
                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- Upcoming Application Deadlines -->
        <section class="mb-5">

            <h2 class="h4">Upcoming Scholarship Deadlines</h2>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>Scholarship Name</th>
                            <th>Application Deadline</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($deadlines as $deadline): ?>

                            <tr>
                                <td>
                                    <a href="scholarship_detail.php?id=<?= (int) $deadline['scholarship_id'] ?>">
                                        <?= facts_h($deadline['scholarship_name']) ?>
                                    </a>
                                </td>

                                <td>
                                    <?= facts_h($deadline['application_deadline']) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($deadlines)): ?>
                            <tr>
                                <td colspan="2">
                                    No upcoming deadlines available.
                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
