<?php
declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';

$pdo = db();
$doctorId = get_int($_GET, 'doctor_id');
if ($doctorId === null) redirect('index.php');

$docSt = $pdo->prepare("
    SELECT e.id, e.surname, e.name, e.patronymic, sp.name AS specialization_name
    FROM employees e
    JOIN specializations sp ON sp.id = e.specialization_id
    WHERE e.id = :id
");
$docSt->execute([':id' => $doctorId]);
$doctor = $docSt->fetch();
if (!$doctor) redirect('index.php');

$st = $pdo->prepare("
    SELECT a.id, a.appointment_datetime, a.status, a.notes,
           p.surname || ' ' || p.name ||
              CASE WHEN p.patronymic IS NULL OR p.patronymic = '' THEN '' ELSE ' ' || p.patronymic END AS patient_full_name,
           s.name AS service_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN services  s ON s.id = a.service_id
    WHERE a.employee_id = :doctor_id
    ORDER BY a.appointment_datetime
");
$st->execute([':doctor_id' => $doctorId]);
$rows = $st->fetchAll();

$title = 'График врача';
require __DIR__ . '/../src/layout/header.php';
?>

<h1>График: <?= h(build_full_name($doctor)) ?> (<?= h((string)$doctor['specialization_name']) ?>)</h1>

<div class="row" style="margin: 10px 0">
    <a class="btn" href="appointment_form.php?doctor_id=<?= (int)$doctorId ?>">Добавить запись</a>
</div>

<?php if (!$rows): ?>
    <p class="muted">Записей нет.</p>
<?php else: ?>
<table>
    <thead>
    <tr>
        <th class="right">ID</th>
        <th>Дата/время</th>
        <th>Пациент</th>
        <th>Услуга</th>
        <th>Статус</th>
        <th>Заметки</th>
        <th>CRUD</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="right"><?= (int)$r['id'] ?></td>
            <td><?= h((string)$r['appointment_datetime']) ?></td>
            <td><?= h((string)$r['patient_full_name']) ?></td>
            <td><?= h((string)$r['service_name']) ?></td>
            <td><?= h((string)$r['status']) ?></td>
            <td><?= h((string)($r['notes'] ?? '')) ?></td>
            <td>
                <div class="row">
                    <a class="btn" href="appointment_form.php?doctor_id=<?= (int)$doctorId ?>&id=<?= (int)$r['id'] ?>">Редактировать</a>
                    <a class="btn" href="appointment_delete.php?doctor_id=<?= (int)$doctorId ?>&id=<?= (int)$r['id'] ?>">Удалить</a>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php require __DIR__ . '/../src/layout/footer.php'; ?>