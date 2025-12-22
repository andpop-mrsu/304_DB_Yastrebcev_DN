<?php
declare(strict_types=1);
/** @var string $title */
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title><?= h($title) ?></title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 24px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        th { background: #f5f5f5; text-align: left; }
        .right { text-align: right; }
        .muted { color: #666; }
        .btn { display:inline-block; padding:6px 10px; border:1px solid #ccc; border-radius:8px; text-decoration:none; color:#000; background:#fafafa; }
        .btn:hover { background:#f0f0f0; }
        .row { display:flex; gap:8px; flex-wrap:wrap; }
        .err { background:#ffecec; border:1px solid #ffb3b3; padding:10px; border-radius:10px; }
        .ok  { background:#eef9ee; border:1px solid #bfe6bf; padding:10px; border-radius:10px; }
        input, select, textarea { padding:6px; }
        label { display:block; margin:10px 0 4px; }
        .card { margin: 10px 0; padding: 10px; border:1px solid #ddd; border-radius: 10px; }
    </style>
</head>
<body>
<div class="row" style="margin-bottom:14px">
    <a class="btn" href="index.php">На главную</a>
</div>