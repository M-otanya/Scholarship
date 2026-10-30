<?php
function search_award(array $s): string {
    if ($s['award_min']===null && $s['award_max']===null) return 'Not specified';
    if ($s['award_min']!==null && $s['award_max']!==null && (float)$s['award_min'] !== (float)$s['award_max']) return '$'.number_format((float)$s['award_min']).'–$'.number_format((float)$s['award_max']);
    return '$'.number_format((float)($s['award_max'] ?? $s['award_min']));
}
function search_deadline(array $s): string { return $s['rolling_deadline'] ? 'Rolling' : ($s['application_deadline'] ? date('M j, Y',strtotime($s['application_deadline'])) : 'Not specified'); }
?>
<?php if ($loadError): ?><div class="alert alert-danger">Unable to load scholarships. Check the PHP error log.</div>
<?php else: ?>
<p role="status"><?= number_format($total) ?> scholarship(s) found.</p>
<?php if (!$scholarships): ?><div class="alert alert-info">No scholarships match your search. Try changing the filters.</div><?php endif; ?>
<?php if ($scholarships): ?>
<div class="table-responsive d-none d-md-block"><table class="table table-striped table-hover align-middle"><thead><tr><th>Scholarship</th><th>Provider</th><th>Award</th><th>Deadline</th><th>Scope</th></tr></thead><tbody>
<?php foreach ($scholarships as $s): ?><tr><td><strong><?= search_h($s['scholarship_name']) ?></strong><?php if ($s['discovery_source']==='Sample Data'): ?><div class="small text-muted">Sample data — not a real scholarship</div><?php endif; ?></td><td><?= search_h($s['provider_name'] ?: 'Not specified') ?></td><td><?= search_h(search_award($s)) ?></td><td><?= search_h(search_deadline($s)) ?></td><td><?= search_h($s['scope'] ?: 'Not specified') ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="d-md-none row g-3"><?php foreach ($scholarships as $s): ?><div class="col-12"><article class="card h-100"><div class="card-body"><h2 class="h5 card-title"><?= search_h($s['scholarship_name']) ?></h2><p class="mb-2"><strong>Provider:</strong> <?= search_h($s['provider_name'] ?: 'Not specified') ?></p><p class="mb-2"><strong>Award:</strong> <?= search_h(search_award($s)) ?></p><p class="mb-2"><strong>Deadline:</strong> <?= search_h(search_deadline($s)) ?></p><p class="mb-2"><strong>Scope:</strong> <?= search_h($s['scope'] ?: 'Not specified') ?></p><?php if ($s['description']): ?><p class="card-text"><?= nl2br(search_h($s['description'])) ?></p><?php endif; ?><?php if ($s['discovery_source']==='Sample Data'): ?><span class="badge bg-secondary">Sample data — not a real scholarship</span><?php endif; ?></div></article></div><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($total>$perPage): ?><nav class="mt-4" aria-label="Scholarship results pages"><ul class="pagination flex-wrap"><li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= search_h(search_qs(['page'=>max(1,$page-1)])) ?>">Previous</a></li><?php for ($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?><li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="<?= search_h(search_qs(['page'=>$i])) ?>"><?= $i ?></a></li><?php endfor; ?><li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= search_h(search_qs(['page'=>min($pages,$page+1)])) ?>">Next</a></li></ul></nav><?php endif; ?>
<?php endif; ?>
