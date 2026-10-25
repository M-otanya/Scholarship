<?php
require __DIR__ . '/includes/db.php';

try {
    $statement = $pdo->query(
        "SELECT s.scholarship_name, s.description, s.award_min, s.award_max,
                s.application_deadline, s.scope, s.discovery_source,
                p.provider_name
         FROM scholarships AS s
         LEFT JOIN providers AS p ON s.provider_id = p.provider_id
         WHERE s.status = 'active'
         ORDER BY s.application_deadline IS NULL, s.application_deadline ASC, s.scholarship_name ASC"
    );
    $scholarships = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    $scholarships = [];
    $loadError = true;
}

function escapeHtml($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<main class="container py-5">
    <h1 class="mb-2">Available Scholarships</h1>
    <p class="text-muted">Browse scholarships currently listed in our database.</p>
    <?php if (!empty($loadError)): ?>
        <div class="alert alert-danger" role="alert">Unable to load scholarships. Check the PHP error log.</div>
    <?php elseif (empty($scholarships)): ?>
        <div class="alert alert-info" role="status">No active scholarships have been added yet.</div>
    <?php else: ?>
        <p><?= count($scholarships) ?> scholarship(s) found.</p>
        <div class="row g-3">
            <?php foreach ($scholarships as $scholarship): ?>
                <div class="col-12 col-lg-6">
                    <article class="card h-100">
                        <div class="card-body">
                            <h2 class="h5 card-title"><?= escapeHtml($scholarship['scholarship_name']) ?></h2>
                            <p class="mb-2"><strong>Provider:</strong> <?= escapeHtml($scholarship['provider_name'] ?: 'Not specified') ?></p>
                            <p class="mb-2"><strong>Award:</strong>
                                <?php
                                if ($scholarship['award_min'] === null && $scholarship['award_max'] === null) {
                                    echo 'Not specified';
                                } elseif ($scholarship['award_min'] !== null && $scholarship['award_max'] !== null && (float)$scholarship['award_min'] !== (float)$scholarship['award_max']) {
                                    echo '$' . number_format((float)$scholarship['award_min']) . '–$' . number_format((float)$scholarship['award_max']);
                                } else {
                                    echo '$' . number_format((float)($scholarship['award_max'] ?? $scholarship['award_min']));
                                }
                                ?>
                            </p>
                            <p class="mb-2"><strong>Deadline:</strong> <?= $scholarship['application_deadline'] ? escapeHtml(date('M j, Y', strtotime($scholarship['application_deadline']))) : 'Not specified' ?></p>
                            <?php if ($scholarship['scope']): ?>
                                <p class="mb-2"><strong>Scope:</strong> <?= escapeHtml($scholarship['scope']) ?></p>
                            <?php endif; ?>
                            <?php if ($scholarship['description']): ?>
                                <p class="card-text"><?= nl2br(escapeHtml($scholarship['description'])) ?></p>
                            <?php endif; ?>
                            <?php if ($scholarship['discovery_source'] === 'Sample Data'): ?>
                                <span class="badge bg-secondary">Sample data — not a real scholarship</span>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
