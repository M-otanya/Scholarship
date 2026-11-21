<?php
require_once __DIR__ . '/auth.php';

// Initialize session before HTML output.
auth_session();

$siteBase = auth_base();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pathways to Scholarships</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="<?= h($siteBase) ?>/css/style.css"
    >
</head>
<body>
