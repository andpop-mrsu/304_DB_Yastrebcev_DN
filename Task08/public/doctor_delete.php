<?php
declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';

$pdo = db();
$id = get_int($_GET, 'id');
if ($id === null) redirect('index.php');

$st = $pdo->prepare("
    SELECT e.*, sp.name AS specialization_name
    FROM employees e
    JOIN specializations sp ON sp.id = e.specialization_id
    WHERE e.id = :id
");
$st->execute([':id' => $id]);
$doctor = $st->fetch();
if (!$doctor) redirect('index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $del = $pdo->prepare("DELETE FROM employees WHERE id = :id");
        $del->execute([':id' => $id]);
        redirect('index.php');
    } catch (Throwable $e) {
        $error = 'Не удалось удалить врача. Скорее всего, есть связанные записи (график/услуги).';
    }
}

$title = 'Удалить врача';
require __DIR__ . '/../src/layout/header.php';
?>

<h1>Удаление врача</h1>

<?php if ($error !== ''): ?>
    <div class="err"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <div><strong>ФИО:</strong> <?= h(build_full_name($doctor)) ?></div>
    <div><strong>Специализация:</strong> <?= h((string)$doctor['specialization_name']) ?></div>
</div>

<form method="post">
    <p>Точно удалить эту запись?</p>
    <button class="btn" type="submit">Да, удалить</button>
    <a class="btn" href="index.php">Отмена</a>
</form>

<?php require __DIR__ . '/../src/layout/footer.php'; ?>