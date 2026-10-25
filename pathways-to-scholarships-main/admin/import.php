<?php
declare(strict_types=1);
require __DIR__ . '/../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['import_csrf'])) $_SESSION['import_csrf'] = bin2hex(random_bytes(32));

function upload_read_csv(string $path): array {
    $handle = fopen($path, 'rb');
    if (!$handle) throw new RuntimeException('Cannot open CSV file.');
    try {
        $headers = fgetcsv($handle);
        if ($headers === false) throw new RuntimeException('CSV is empty.');
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headers[0]);
        $headers = array_map('trim', $headers);
        upload_check_headers($headers);
        $rows = []; $line = 1;
        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if ($cells === [null] || !array_filter($cells, static fn($v) => trim((string)$v) !== '')) continue;
            if (count($cells) !== count($headers)) throw new RuntimeException("Column mismatch at CSV record $line.");
            $rows[] = ['line'=>$line, 'data'=>array_combine($headers, $cells)];
            if (count($rows) > 500) throw new RuntimeException('Maximum 500 data rows per file.');
        }
        return $rows;
    } finally { fclose($handle); }
}
function upload_check_headers(array $headers): void {
    if (count($headers) < 2 || in_array('', $headers, true) || count(array_unique(array_map('strtolower', $headers))) !== count($headers)) {
        throw new RuntimeException('At least two unique, nonempty column headers are required.');
    }
}
function upload_read_xlsx(string $path): array {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('Excel support requires PhpSpreadsheet. Run composer require phpoffice/phpspreadsheet in the project folder.');
    require_once $autoload;
    if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) throw new RuntimeException('PhpSpreadsheet is not installed.');
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($path);
    try {
        $sheet = $spreadsheet->getSheet(0);
        if ($sheet->getHighestDataRow() > 10000) throw new RuntimeException('Spreadsheet has too many rows.');
        $all = $sheet->toArray(null, false, false, false);
        $headerRow = array_shift($all);
        if (!$headerRow) throw new RuntimeException('Excel sheet is empty.');
        $headers = array_map(static fn($v) => trim((string)($v ?? '')), $headerRow);
        while ($headers && end($headers) === '') array_pop($headers);
        upload_check_headers($headers);
        $rows = [];
        foreach ($all as $index => $cells) {
            $cells = array_slice(array_pad($cells, count($headers), null), 0, count($headers));
            if (!array_filter($cells, static fn($v) => trim((string)($v ?? '')) !== '')) continue;
            $rows[] = ['line'=>$index + 2, 'data'=>array_combine($headers, array_map(static fn($v) => (string)($v ?? ''), $cells))];
            if (count($rows) > 500) throw new RuntimeException('Maximum 500 data rows per file.');
        }
        return $rows;
    } finally { $spreadsheet->disconnectWorksheets(); }
}
function upload_stage(PDO $pdo, string $filename, array $rows): int {
    if (!$rows) throw new RuntimeException('File has no scholarship data rows.');
    $student = pathinfo($filename, PATHINFO_FILENAME);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO import_batches (file_name, contributing_student, total_rows, ready_rows, status) VALUES (?, ?, ?, 0, 'pending')");
        $stmt->execute([$filename, $student, count($rows)]);
        $batchId = (int)$pdo->lastInsertId();
        $insert = $pdo->prepare("INSERT INTO import_staging_rows (batch_id, row_number, raw_data, row_status) VALUES (?, ?, ?, 'Pending')");
        foreach ($rows as $row) {
            $insert->execute([$batchId, $row['line'], json_encode($row['data'], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)]);
        }
        $pdo->commit();
        return $batchId;
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}

$errors = []; $successes = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['import_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'Invalid form token. Refresh and try again.';
    } elseif (empty($_FILES['files']) || !is_array($_FILES['files']['name'])) {
        $errors[] = 'Choose one or more files.';
    } else {
        $uploads = $_FILES['files'];
        $count = count($uploads['name']);
        if ($count > 10) $errors[] = 'Maximum 10 files per upload.';
        else for ($i=0; $i<$count; $i++) {
            $name = basename((string)$uploads['name'][$i]);
            try {
                if ($uploads['error'][$i] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed (PHP error '.$uploads['error'][$i].').');
                $size = (int)$uploads['size'][$i];
                if ($size < 1 || $size > 2*1024*1024) throw new RuntimeException('File must be between 1 byte and 2 MB.');
                $tmp = $uploads['tmp_name'][$i];
                if (!is_uploaded_file($tmp)) throw new RuntimeException('Invalid upload.');
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['csv','xlsx'], true)) throw new RuntimeException('Only CSV and XLSX files are allowed.');
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                if ($ext === 'csv') {
                    if (!in_array($mime, ['text/plain','text/csv','application/csv','application/vnd.ms-excel','text/x-csv','application/octet-stream'], true)) throw new RuntimeException('Invalid CSV file type.');
                    $rows = upload_read_csv($tmp);
                } else {
                    if (!in_array($mime, ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip','application/octet-stream'], true)) throw new RuntimeException('Invalid XLSX file type.');
                    if (!class_exists('ZipArchive')) throw new RuntimeException('PHP ZIP extension is required for XLSX.');
                    $zip = new ZipArchive();
                    if ($zip->open($tmp) !== true) throw new RuntimeException('Invalid XLSX archive.');
                    $isWorkbook = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('xl/workbook.xml') !== false;
                    $zip->close();
                    if (!$isWorkbook) throw new RuntimeException('Not a valid XLSX workbook.');
                    $rows = upload_read_xlsx($tmp);
                }
                $batchId = upload_stage($pdo, $name, $rows);
                $successes[] = ['name'=>$name, 'batch'=>$batchId, 'count'=>count($rows)];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Import upload '.$name.': '.$e->getMessage());
                $errors[] = $name . ': ' . ($e instanceof RuntimeException ? $e->getMessage() : 'Could not process this file. Check PHP error log.');
            }
        }
    }
}
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-5" style="max-width:850px">
<h1>Import scholarships</h1>
<p>
    <a href="/pathways-to-scholarships/templates/Scholarship_Validation_Template.xlsx"
       class="btn btn-outline-primary"
       download>
        Download Scholarship Template
    </a>
</p>
<p class="text-muted">Upload up to 10 CSV or XLSX files. Each file creates a separate staging batch. Nothing is published until a later commit step.</p>
<?php foreach ($errors as $message): ?><div class="alert alert-danger"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
<?php if ($successes): ?>
<div class="alert alert-success"><strong>Files staged successfully:</strong><ul class="mb-0">
<?php foreach ($successes as $item): ?><li><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?> — <?= (int)$item['count'] ?> rows — <a href="validate.php?batch_id=<?= (int)$item['batch'] ?>">Review batch <?= (int)$item['batch'] ?></a></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card p-4 shadow-sm">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['import_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<label class="form-label" for="files">Choose CSV or XLSX files (2 MB per file)</label>
<input class="form-control mb-3" type="file" id="files" name="files[]" accept=".csv,.xlsx" multiple required>
<button class="btn btn-primary" type="submit">Upload to staging</button>
</form>
<p class="mt-3 small text-muted">Files should be named LastName_FirstName.csv or .xlsx. Uploading does not validate scholarship fields; use Review batch to validate. Development only: administrator authentication is required before deployment.</p>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
