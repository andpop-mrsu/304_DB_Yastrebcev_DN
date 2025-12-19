<?php
declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';

$pdo = db();
$doctorId = get_int($_GET, 'doctor_id');
$id = get_int($_GET, 'id');
if ($doctorId === null || $id === null) redirect('index.php');

$st = $pdo->prepare("
    SELECT pc.id, pc.completion_date, pc.actual_price,
           s.name AS service_name,
           p.surname || ' ' || p.name ||
              CASE WHEN p.patronymic IS NULL OR p.patronymic = '' THEN '' ELSE ' ' || p.patronymic END AS patient_full_name
    FROM procedures_completed pc
    JOIN services s ON s.id = pc.service_id
    JOIN patients p ON p.id = pc.patient_id
    WHERE pc.id = :id AND pc.employee_id = :doctor_id
");
$st->execute([':id' => $id, ':doctor_id' => $doctorId]);
$row = $st->fetch();
if (!$row) redirect("doctor_procedures.php?doctor_id={$doctorId}");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $del = $pdo->prepare("DELETE FROM procedures_completed WHERE id = :id AND employee_id = :doctor_id");
    $del->execute([':id' => $id, ':doctor_id' => $doctorId]);
    redirect("doctor_procedures.php?doctor_id={$doctorId}");
}

$title = 'Удалить оказанную услугу';
require __DIR__ . '/../src/layout/header.php';
?>

<h1>Удалить оказанную услугу</h1>

<div class="card">
    <div><strong>Дата:</strong> <?= h((string)$row['completion_date']) ?></div>
    <div><strong>Пациент:</strong> <?= h((string)$row['patient_full_name']) ?></div>
    <div><strong>Услуга:</strong> <?= h((string)$row['service_name']) ?></div>
    <div><strong>Стоимость:</strong> <?= number_format((float)$row['actual_price'], 2, '.', '') ?></div>
</div>

<form method="post">
    <p>Точно удалить?</p>
    <button class="btn" type="submit">Да, удалить</button>
    <a class="btn" href="doctor_procedures.php?doctor_id=<?= (int)$doctorId ?>">Отмена</a>
</form>

<?php require __DIR__ . '/../src/layout/footer.php'; ?>