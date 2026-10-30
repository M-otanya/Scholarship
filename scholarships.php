<?php
require __DIR__ . '/includes/search_data.php';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
$filterLabels = ['state'=>['State','All states'],'grade'=>['Grade level','All grade levels'],'field'=>['Field of study','All fields of study'],'category'=>['Category','All categories'],'theme'=>['National theme','All national themes']];
?>
<main class="container py-5">
<h1 class="mb-2">Search Scholarships</h1>
<p class="text-muted">Find scholarships using the imported dataset.</p>
<form id="scholarship-search" method="get" action="scholarships.php" class="card card-body mb-4">
<div class="row g-3">
<div class="col-12"><label for="q" class="form-label">Keyword search</label><input class="form-control" id="q" name="q" maxlength="120" value="<?= search_h($keyword) ?>" placeholder="Scholarship name, provider, eligibility..."></div>
<?php foreach ($filterLabels as $key => [$label,$allLabel]): ?>
<div class="col-md-4"><label class="form-label" for="<?= search_h($key) ?>"><?= search_h($label) ?></label><select class="form-select" id="<?= search_h($key) ?>" name="<?= search_h($key) ?>"><option value=""><?= search_h($allLabel) ?></option><?php foreach ($options[$key] ?? [] as $option): ?><option value="<?= (int)$option['id'] ?>"<?= search_selected($filters[$key],$option['id']) ?>><?= search_h($option['label']) ?></option><?php endforeach; ?></select></div>
<?php endforeach; ?>
<div class="col-md-4"><label class="form-label" for="scope">Scope</label><select class="form-select" id="scope" name="scope"><option value="">All scopes</option><option value="national"<?= search_selected($scope,'national') ?>>National</option><option value="state/local"<?= search_selected($scope,'state/local') ?>>State/local</option></select></div>
<div class="col-md-4"><label class="form-label" for="sort">Sort by</label><select class="form-select" id="sort" name="sort"><option value="deadline"<?= search_selected($sort,'deadline') ?>>Deadline (soonest)</option><option value="name"<?= search_selected($sort,'name') ?>>Name (A–Z)</option><option value="award_high"<?= search_selected($sort,'award_high') ?>>Award (highest)</option><option value="award_low"<?= search_selected($sort,'award_low') ?>>Award (lowest)</option></select></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="expired" name="expired" value="1" <?= $showExpired?'checked':'' ?>><label class="form-check-label" for="expired">Include expired scholarships</label></div></div>
<div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Search</button><a class="btn btn-outline-secondary" href="scholarships.php">Clear filters</a></div>
</div></form>
<div id="search-results" aria-live="polite"><?php require __DIR__ . '/includes/search_results.php'; ?></div>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="js/scholarship_search.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
