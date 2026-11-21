<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/parent_helpers.php';
$parentId=parent_require_user();
try { $children=parent_children($pdo,$parentId); }
catch (PDOException $e) { error_log($e->getMessage()); http_response_code(500); exit('Unable to load dashboard. Run the Iteration 8 SQL migration first.'); }
$totalSaved=array_sum(array_map(static fn($c)=>(int)$c['saved_count'],$children));
$totalDue=array_sum(array_map(static fn($c)=>(int)$c['due_soon'],$children));
require __DIR__.'/../includes/header.php';
require __DIR__.'/../includes/nav.php';
?>
<main class="container my-5">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div><h1>Parent Dashboard</h1><p class="text-muted">Manage your children's scholarship journeys.</p></div>
    <a class="btn btn-primary" href="child_form.php">Add Child</a>
  </div>
  <div class="row g-3 mb-4">
    <?php foreach (['Children'=>count($children),'Saved Scholarships'=>$totalSaved,'Deadlines in Next 30 Days'=>$totalDue] as $label=>$value): ?>
      <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted"><?=h($label)?></div><div class="display-6 fw-bold"><?=(int)$value?></div>
      </div></div></div>
    <?php endforeach; ?>
  </div>
  <h2 class="h4">Your Children</h2>
  <?php if (!$children): ?><p>No child profiles yet. Add your first child to get started.</p><?php endif; ?>
  <div class="row g-3">
    <?php foreach ($children as $child): ?>
      <div class="col-md-6"><div class="card h-100 shadow-sm"><div class="card-body">
        <h3 class="h5"><?=h($child['child_name'])?></h3>
        <p>Grade <?=h($child['grade_number'])?> · <?=h($child['state_name'])?></p>
        <p class="mb-3"><?= (int)$child['saved_count'] ?> saved · <?= (int)$child['due_soon'] ?> deadlines within 30 days</p>
        <a class="btn btn-primary btn-sm" href="child_scholarships.php?child_id=<?=(int)$child['child_id']?>">View Scholarships</a>
        <a class="btn btn-outline-secondary btn-sm" href="child_form.php?child_id=<?=(int)$child['child_id']?>">Edit Child</a>
      </div></div></div>
    <?php endforeach; ?>
  </div>
  <a class="btn btn-link mt-3" href="children.php">Manage All Children</a>
</main>
<?php require __DIR__.'/../includes/footer.php'; ?>
