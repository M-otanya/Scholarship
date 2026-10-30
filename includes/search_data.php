<?php
// Shared search logic for full-page and AJAX requests.
require_once __DIR__ . '/db.php';
function search_h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function search_selected($a,$b): string { return (string)$a === (string)$b ? ' selected' : ''; }
function search_qs(array $changes): string { return '?' . http_build_query(array_merge($_GET, $changes)); }
$filters = [];
foreach (['state','grade','field','category','theme'] as $key) {
    $filters[$key] = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
}
$keyword = substr(trim((string)($_GET['q'] ?? '')), 0, 120);
$scope = in_array($_GET['scope'] ?? '', ['national','state/local'], true) ? $_GET['scope'] : '';
$sort = in_array($_GET['sort'] ?? '', ['deadline','name','award_high','award_low'], true) ? $_GET['sort'] : 'deadline';
$showExpired = ($_GET['expired'] ?? '') === '1';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10; $options = []; $scholarships = []; $total = 0; $pages = 1; $loadError = false;
try {
    foreach (['state'=>'SELECT state_id AS id, name AS label FROM states ORDER BY name', 'grade'=>'SELECT grade_id AS id, grade_number AS label FROM grade_levels ORDER BY grade_number', 'field'=>'SELECT field_id AS id, field_name AS label FROM fields_of_study ORDER BY field_name', 'category'=>'SELECT category_id AS id, category_name AS label FROM categories ORDER BY category_name', 'theme'=>'SELECT national_theme_id AS id, theme_name AS label FROM national_themes ORDER BY theme_name'] as $key=>$sql) {
        if (!defined('SEARCH_AJAX')) $options[$key] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    $where = ["s.status = 'active'"]; $params = [];
    if (!$showExpired) $where[] = '(s.application_deadline IS NULL OR s.application_deadline >= CURRENT_DATE() OR s.rolling_deadline = 1)';
    if ($filters['state']) { $where[] = "(s.state_id = :state OR s.scope = 'national')"; $params[':state']=$filters['state']; }
    if ($filters['grade']) { $where[] = 'EXISTS (SELECT 1 FROM scholarship_grades sg WHERE sg.scholarship_id=s.scholarship_id AND sg.grade_id=:grade)'; $params[':grade']=$filters['grade']; }
    if ($filters['field']) { $where[] = 'EXISTS (SELECT 1 FROM scholarship_fields sf WHERE sf.scholarship_id=s.scholarship_id AND sf.field_id=:field)'; $params[':field']=$filters['field']; }
    if ($filters['category']) { $where[] = 'EXISTS (SELECT 1 FROM scholarship_categories sc WHERE sc.scholarship_id=s.scholarship_id AND sc.category_id=:category)'; $params[':category']=$filters['category']; }
    if ($filters['theme']) { $where[] = 's.national_theme_id=:theme'; $params[':theme']=$filters['theme']; }
    if ($scope !== '') { $where[] = 's.scope=:scope'; $params[':scope']=$scope; }
    if ($keyword !== '') { $where[] = '(s.scholarship_name LIKE :kw1 OR s.description LIKE :kw2 OR p.provider_name LIKE :kw3 OR s.eligibility_requirements LIKE :kw4)'; foreach (['kw1','kw2','kw3','kw4'] as $k) $params[':'.$k]='%'.$keyword.'%'; }
    $from = ' FROM scholarships s LEFT JOIN providers p ON p.provider_id=s.provider_id WHERE '.implode(' AND ', $where);
    $count=$pdo->prepare('SELECT COUNT(*)'.$from); $count->execute($params); $total=(int)$count->fetchColumn();
    $pages=max(1,(int)ceil($total/$perPage)); $page=min($page,$pages);
    $orders=['deadline'=>'s.application_deadline IS NULL ASC, s.application_deadline ASC, s.scholarship_name ASC', 'name'=>'s.scholarship_name ASC', 'award_high'=>'COALESCE(s.award_max,s.award_min) IS NULL ASC, COALESCE(s.award_max,s.award_min) DESC', 'award_low'=>'COALESCE(s.award_min,s.award_max) IS NULL ASC, COALESCE(s.award_min,s.award_max) ASC'];
    $stmt=$pdo->prepare('SELECT s.scholarship_id,s.scholarship_name,s.description,s.award_min,s.award_max,s.application_deadline,s.rolling_deadline,s.scope,s.discovery_source,p.provider_name'.$from.' ORDER BY '.$orders[$sort].', s.scholarship_id ASC LIMIT :lim OFFSET :off');
    foreach ($params as $key=>$value) $stmt->bindValue($key,$value);
    $stmt->bindValue(':lim',$perPage,PDO::PARAM_INT); $stmt->bindValue(':off',($page-1)*$perPage,PDO::PARAM_INT);
    $stmt->execute(); $scholarships=$stmt->fetchAll(PDO::FETCH_ASSOC);
    // Log only explicit filter submissions, not pagination, sorting, or initial visits.
    if (($_GET['log_search'] ?? '') === '1') {
        $log=$pdo->prepare('INSERT INTO search_log (state_id, grade_id, field_id) VALUES (:state,:grade,:field)');
        foreach (['state','grade','field'] as $key) $log->bindValue(':'.$key,$filters[$key] ?: null,$filters[$key] ? PDO::PARAM_INT : PDO::PARAM_NULL);
        try { $log->execute(); } catch (PDOException $e) { error_log('Search logging: '.$e->getMessage()); }
    }
} catch (PDOException $e) { error_log($e->getMessage()); $loadError=true; http_response_code(500); }
