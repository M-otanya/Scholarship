<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/scholarship_stats.php';
require_once __DIR__ . '/../includes/auth.php';


require_role(['Administrator', 'Super-admin']);

$summary = [];
$states = [];
$themes = [];
$scopes = [];
$deadlines = [];
$error = null;

try {
    $summary = getScholarshipSummary($pdo);
    $states = getScholarshipsByState($pdo);
    $themes = getScholarshipsByTheme($pdo);
    $scopes = getScholarshipsByScope($pdo);
    $deadlines = getUpcomingDeadlines($pdo);

} catch (PDOException $e) {
    error_log('Admin Dashboard: ' . $e->getMessage());
    $error = 'Unable to load dashboard statistics.';
}

// Safely encode database values for JavaScript.
function dashboard_json(array $data): string
{
    return json_encode(
        $data,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?: '[]';
}

// Escape output.
function dashboard_h($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Admin Dashboard | Pathways to Scholarships</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body>

<nav class="navbar navbar-dark bg-primary">
    <div class="container">

        <a class="navbar-brand" href="../index.php">
            Pathways to Scholarships
        </a>

        <a class="btn btn-outline-light btn-sm" href="../scholarship_facts.php">
            Public Scholarship Facts
        </a>

    </div>
</nav>

<main class="container my-5">

    <h1>Admin Dashboard</h1>

    <p class="text-muted">
        Overview of scholarship records and database statistics.
    </p>

    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= dashboard_h($error) ?>
        </div>

    <?php else: ?>

        <!-- Dashboard Summary Cards -->

        <section class="mb-5">

            <h2 class="h4 mb-3">Scholarship Summary</h2>

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
                        $summary['state_scholarships'] ?? 0,

                    'Expired Deadlines' =>
                        $summary['expired_deadlines'] ?? 0
                ];
                ?>

                <?php foreach ($cards as $label => $count): ?>

                    <div class="col-md-6 col-lg">

                        <div class="card shadow-sm h-100">

                            <div class="card-body text-center">

                                <h2 class="h6 text-muted">
                                    <?= dashboard_h($label) ?>
                                </h2>

                                <p class="display-6 fw-bold mb-0">
                                    <?= (int) $count ?>
                                </p>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- Dashboard Charts -->

        <section class="mb-5">

            <h2 class="h4 mb-3">Scholarship Statistics</h2>

            <div class="row g-4">

                <div class="col-lg-6">

                    <div class="card shadow-sm h-100">
                        <div class="card-body">

                            <h3 class="h5">
                                Scholarships by Scope
                            </h3>

                            <div style="height: 300px;">
                                <canvas id="scopeChart"></canvas>
                            </div>

                        </div>
                    </div>

                </div>

                <div class="col-lg-6">

                    <div class="card shadow-sm h-100">
                        <div class="card-body">

                            <h3 class="h5">
                                Scholarships by State
                            </h3>

                            <div style="height: 300px;">
                                <canvas id="stateChart"></canvas>
                            </div>

                        </div>
                    </div>

                </div>

                <div class="col-12">

                    <div class="card shadow-sm">
                        <div class="card-body">

                            <h3 class="h5">
                                Scholarships by National Theme
                            </h3>

                            <div style="height: 300px;">
                                <canvas id="themeChart"></canvas>
                            </div>

                        </div>
                    </div>

                </div>

            </div>

        </section>


        <!-- Upcoming Scholarship Deadlines -->

        <section class="mb-5">

            <h2 class="h4 mb-3">
                Upcoming Scholarship Deadlines
            </h2>

            <div class="table-responsive">

                <table class="table table-striped table-bordered">

                    <thead>
                        <tr>
                            <th>Scholarship</th>
                            <th>Application Deadline</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($deadlines as $deadline): ?>

                            <tr>
                                <td>
                                    <?= dashboard_h(
                                        $deadline['scholarship_name']
                                    ) ?>
                                </td>

                                <td>
                                    <?= dashboard_h(
                                        $deadline['application_deadline']
                                    ) ?>
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


        <!-- Administrative Navigation -->

        <section class="mb-5">

            <h2 class="h4 mb-3">Administrative Tools</h2>

            <div class="d-flex flex-wrap gap-2">

                <a href="scholarships.php" class="btn btn-primary">
                    Manage Scholarships
                </a>

                <a href="import.php" class="btn btn-outline-primary">
                    Import Scholarships
                </a>

                <a href="import_history.php" class="btn btn-outline-primary">
                    Import History
                </a>

            </div>

        </section>

    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>

<script>
    // Real scholarship statistics retrieved from MySQL.
    window.scholarshipChartData = {
        scopes: <?= dashboard_json($scopes) ?>,
        states: <?= dashboard_json($states) ?>,
        themes: <?= dashboard_json($themes) ?>
    };
</script>

<script src="../js/dashboard_charts.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
