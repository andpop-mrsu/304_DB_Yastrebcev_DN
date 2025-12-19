<?php
declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';

$pdo = db();

$stmt = $pdo->prepare("
    SELECT e.id, e.surname, e.name, e.patronymic,
           sp.name AS specialization_name,
           e.status
    FROM employees e
    JOIN specializations sp ON sp.id = e.specialization_id
    ORDER BY e.surname, e.name, e.patronymic
");
$stmt->execute();
$doctors = $stmt->fetchAll();

$title = 'Врачи клиники';
require __DIR__ . '/../src/layout/header.php';
?>

<h1>Врачи клиники</h1>

<?php if (!$doctors): ?>
    <p class="muted">Врачей нет.</p>
<?php else: ?>
<table>
    <thead>
    <tr>
        <th class="right">ID</th>
        <th>ФИО</th>
        <th>Специализация</th>
        <th>Статус</th>
        <th>Действия</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($doctors as $d): ?>
        <?php $fullName = build_full_name($d); ?>
        <tr>
            <td class="right"><?= (int)$d['id'] ?></td>
            <td><?= h($fullName) ?></td>
            <td><?= h((string)$d['specialization_name']) ?></td>
            <td><?= h((string)$d['status']) ?></td>
            <td>
                <div class="row">
                    <a class="btn" href="doctor_form.php?id=<?= (int)$d['id'] ?>">Редактировать</a>
                    <a class="btn" href="doctor_delete.php?id=<?= (int)$d['id'] ?>">Удалить</a>
                    <a class="btn" href="doctor_schedule.php?doctor_id=<?= (int)$d['id'] ?>">График</a>
                    <a class="btn" href="doctor_procedures.php?doctor_id=<?= (int)$d['id'] ?>">Оказанные услуги</a>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div style="margin-top:14px">
    <a class="btn" href="doctor_form.php">Добавить врача</a>
</div>

<?php require __DIR__ . '/../src/layout/footer.php'; ?>