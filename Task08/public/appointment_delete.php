<?php
declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';

$pdo = db();
$doctorId = get_int($_GET, 'doctor_id');
$id = get_int($_GET, 'id');
if ($doctorId === null || $id === null) redirect('index.php');

$st = $pdo->prepare("
    SELECT a.id, a.appointment_datetime, a.status,
           p.surname || ' ' || p.name ||
              CASE WHEN p.patronymic IS NULL OR p.patronymic = '' THEN '' ELSE ' ' || p.patronymic END AS patient_full_name,
           s.name AS service_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN services  s ON s.id = a.service_id
    WHERE a.id = :id AND a.employee_id = :doctor_id
");
$st->execute([':id' => $id, ':doctor_id' => $doctorId]);
$row = $st->fetch();
if (!$row) redirect("doctor_schedule.php?doctor_id={$doctorId}");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $del = $pdo->prepare("DELETE FROM appointments WHERE id = :id AND employee_id = :doctor_id");
    $del->execute([':id' => $id, ':doctor_id' => $doctorId]);
    redirect("doctor_schedule.php?doctor_id={$doctorId}");
}

$title = 'Удалить запись графика';
require __DIR__ . '/../src/layout/header.php';
?>

<h1>Удалить запись графика</h1>

<div class="card">
    <div><strong>Дата/время:</strong> <?= h((string)$row['appointment_datetime']) ?></div>
    <div><strong>Пациент:</strong> <?= h((string)$row['patient_full_name']) ?></div>
    <div><strong>Услуга:</strong> <?= h((string)$row['service_name']) ?></div>
    <div><strong>Статус:</strong> <?= h((string)$row['status']) ?></div>
</div>

<form method="post">
    <p>Точно удалить?</p>
    <button class="btn" type="submit">Да, удалить</button>
    <a class="btn" href="doctor_schedule.php?doctor_id=<?= (int)$doctorId ?>">Отмена</a>
</form>

<?php require __DIR__ . '/../src/layout/footer.php'; ?>