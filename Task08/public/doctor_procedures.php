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
    SELECT pc.id, pc.completion_date, pc.actual_price, pc.notes, pc.appointment_id,
           s.name AS service_name,
           p.surname || ' ' || p.name ||
              CASE WHEN p.patronymic IS NULL OR p.patronymic = '' THEN '' ELSE ' ' || p.patronymic END AS patient_full_name
    FROM procedures_completed pc
    JOIN services  s ON s.id = pc.service_id
    JOIN patients  p ON p.id = pc.patient_id
    WHERE pc.employee_id = :doctor_id
    ORDER BY pc.completion_date DESC
");
$st->execute([':doctor_id' => $doctorId]);
$rows = $st->fetchAll();

$title = 'Оказанные услуги';
require __DIR__ . '/../src/layout/header.php';
?>

<h1>Оказанные услуги: <?= h(build_full_name($doctor)) ?> (<?= h((string)$doctor['specialization_name']) ?>)</h1>

<div class="row" style="margin: 10px 0">
    <a class="btn" href="procedure_form.php?doctor_id=<?= (int)$doctorId ?>">Добавить услугу</a>
</div>

<?php if (!$rows): ?>
    <p class="muted">Записей нет.</p>
<?php else: ?>
<table>
    <thead>
    <tr>
        <th class="right">ID</th>
        <th>Дата</th>
        <th>Пациент</th>
        <th>Услуга</th>
        <th class="right">Стоимость</th>
        <th class="right">Приём</th>
        <th>Заметки</th>
        <th>CRUD</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="right"><?= (int)$r['id'] ?></td>
            <td><?= h((string)$r['completion_date']) ?></td>
            <td><?= h((string)$r['patient_full_name']) ?></td>
            <td><?= h((string)$r['service_name']) ?></td>
            <td class="right"><?= number_format((float)$r['actual_price'], 2, '.', '') ?></td>
            <td class="right"><?= $r['appointment_id'] === null ? '—' : (int)$r['appointment_id'] ?></td>
            <td><?= h((string)($r['notes'] ?? '')) ?></td>
            <td>
                <div class="row">
                    <a class="btn" href="procedure_form.php?doctor_id=<?= (int)$doctorId ?>&id=<?= (int)$r['id'] ?>">Редактировать</a>
                    <a class="btn" href="procedure_delete.php?doctor_id=<?= (int)$doctorId ?>&id=<?= (int)$r['id'] ?>">Удалить</a>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php require __DIR__ . '/../src/layout/footer.php'; ?>