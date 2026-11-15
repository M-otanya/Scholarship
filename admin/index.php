<?php
declare(strict_types=1);

// Load shared database statistics and authentication helpers.
require_once __DIR__ . '/../includes/scholarship_stats.php';
require_once __DIR__ . '/../includes/auth.php';

// Restrict dashboard access to administrative roles.
// The project's current auth helper is a development stub.
require_role(['Administrator', 'Super-admin']);

// Initialize dashboard data.
$summary = [];
$states = [];
$themes = [];
$scopes = [];
$deadlines = [];
$error = null;

// Retrieve scholarship statistics from MySQL.
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

// Escape database values before displaying HTML.
function dashboard_h($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Safely convert PHP arrays into JavaScript data.
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

// Load shared website layout.
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>

<main class="container my-5">

    <h1>Admin Dashboard</h1>

    <p class="text-muted">
        Overview of scholarship records,
        database statistics, and upcoming deadlines.
    </p>

    <?php if ($error): ?>

        <div class="alert alert-danger" role="alert">
            <?= dashboard_h($error) ?>
        </div>

    <?php else: ?>

        <!-- Scholarship Summary Cards -->
        <section class="mb-5">

            <h2 class="h4 mb-3">Scholarship Summary</h2>

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

            <div class="row g-3">

                <?php foreach ($cards as $label => $count): ?>

                    <div class="col-md-6 col-lg">

                        <div class="card shadow-sm h-100">

                            <div class="card-body text-center">

                                <h3 class="h6 text-muted">
                                    <?= dashboard_h($label) ?>
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


        <!-- Scholarship Statistics Charts -->
        <section class="mb-5">

            <h2 class="h4 mb-3">Scholarship Statistics</h2>

            <div class="row g-4">

                <!-- Chart 1: National vs State -->
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


                <!-- Chart 2: Scholarships by State -->
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


                <!-- Chart 3: National Themes -->
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

                <a href="scholarships.php"
                   class="btn btn-primary">
                    Manage Scholarships
                </a>

                <a href="import.php"
                   class="btn btn-outline-primary">
                    Import Scholarships
                </a>

                <a href="import_history.php"
                   class="btn btn-outline-primary">
                    Import History
                </a>

                <a href="../scholarship_facts.php"
                   class="btn btn-outline-secondary">
                    Public Scholarship Facts
                </a>

            </div>

        </section>

    <?php endif; ?>

</main>


<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>

<!-- Pass PHP database statistics to JavaScript -->
<script>
    window.scholarshipChartData = {
        scopes: <?= dashboard_json($scopes) ?>,
        states: <?= dashboard_json($states) ?>,
        themes: <?= dashboard_json($themes) ?>
    };
</script>

<!-- Load existing dashboard charts -->
<script src="../js/dashboard_charts.js"></script>

<?php
// Load shared footer from parent directory.
require __DIR__ . '/../includes/footer.php';
?>
