<?php
declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';

$pdo = db();

$doctorId = get_int($_GET, 'doctor_id');
if ($doctorId === null) {
    redirect('index.php');
}

$id = get_int($_GET, 'id');

$docSt = $pdo->prepare("SELECT id, surname, name, patronymic FROM employees WHERE id = :id");
$docSt->execute([':id' => $doctorId]);
$doctor = $docSt->fetch();
if (!$doctor) {
    redirect('index.php');
}

$patientsSt = $pdo->prepare("
    SELECT id, surname, name, patronymic
    FROM patients
    ORDER BY surname, name, patronymic
");
$patientsSt->execute();
$patients = $patientsSt->fetchAll();

$servicesSt = $pdo->prepare("SELECT id, name FROM services ORDER BY name");
$servicesSt->execute();
$services = $servicesSt->fetchAll();

$allowedPatientIds = array_map(fn($x) => (string)$x['id'], $patients);
$allowedServiceIds = array_map(fn($x) => (string)$x['id'], $services);

$values = [
    'patient_id' => '',
    'service_id' => '',
    'appointment_datetime' => date('Y-m-d\TH:i'),
    'status' => 'запланирована',
    'notes' => '',
];

if ($id !== null) {
    $st = $pdo->prepare("SELECT * FROM appointments WHERE id = :id AND employee_id = :doctor_id");
    $st->execute([':id' => $id, ':doctor_id' => $doctorId]);
    $row = $st->fetch();
    if (!$row) {
        redirect("doctor_schedule.php?doctor_id={$doctorId}");
    }

    $values = [
        'patient_id' => (string)$row['patient_id'],
        'service_id' => (string)$row['service_id'],
        'appointment_datetime' => sql_to_datetime_local((string)$row['appointment_datetime']),
        'status' => (string)$row['status'],
        'notes' => (string)($row['notes'] ?? ''),
    ];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['patient_id'] = get_str($_POST, 'patient_id');
    $values['service_id'] = get_str($_POST, 'service_id');
    $values['appointment_datetime'] = get_str($_POST, 'appointment_datetime');
    $values['status'] = get_str($_POST, 'status');
    $values['notes'] = get_str($_POST, 'notes');

    if ($values['patient_id'] === '' || !in_array($values['patient_id'], $allowedPatientIds, true)) {
        $errors[] = 'Выберите корректного пациента.';
    }
    if ($values['service_id'] === '' || !in_array($values['service_id'], $allowedServiceIds, true)) {
        $errors[] = 'Выберите корректную услугу.';
    }

    if ($values['appointment_datetime'] === '') {
        $errors[] = 'Укажите дату и время.';
    }

    if (!in_array($values['status'], ['запланирована', 'выполнена', 'отменена'], true)) {
        $errors[] = 'Некорректный статус.';
    }

    $sqlDt = datetime_local_to_sql($values['appointment_datetime']);
    if ($sqlDt === '') {
        $errors[] = 'Некорректная дата/время.';
    }

    if (!$errors) {
        try {
            if ($id === null) {
                $ins = $pdo->prepare("
                    INSERT INTO appointments (patient_id, employee_id, service_id, appointment_datetime, status, notes)
                    VALUES (:patient_id, :employee_id, :service_id, :appointment_datetime, :status, :notes)
                ");
                $ins->execute([
                    ':patient_id' => (int)$values['patient_id'],
                    ':employee_id' => $doctorId,
                    ':service_id' => (int)$values['service_id'],
                    ':appointment_datetime' => $sqlDt,
                    ':status' => $values['status'],
                    ':notes' => $values['notes'] !== '' ? $values['notes'] : null,
                ]);
            } else {
                $upd = $pdo->prepare("
                    UPDATE appointments
                       SET patient_id = :patient_id,
                           service_id = :service_id,
                           appointment_datetime = :appointment_datetime,
                           status = :status,
                           notes = :notes
                     WHERE id = :id AND employee_id = :employee_id
                ");
                $upd->execute([
                    ':patient_id' => (int)$values['patient_id'],
                    ':service_id' => (int)$values['service_id'],
                    ':appointment_datetime' => $sqlDt,
                    ':status' => $values['status'],
                    ':notes' => $values['notes'] !== '' ? $values['notes'] : null,
                    ':id' => $id,
                    ':employee_id' => $doctorId,
                ]);
            }

            redirect("doctor_schedule.php?doctor_id={$doctorId}");
        } catch (PDOException $e) {
            $errors[] = 'Не удалось сохранить запись. Проверьте дату/время (возможно, это время уже занято).';
        }
    }
}

$title = $id === null ? 'Добавить запись графика' : 'Редактировать запись графика';
require __DIR__ . '/../src/layout/header.php';
?>

<h1><?= h($title) ?> — <?= h(build_full_name($doctor)) ?></h1>

<?php if ($errors): ?>
    <div class="err">
        <strong>Ошибки:</strong>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post">
    <label>Пациент *</label>
    <select name="patient_id" required>
        <option value="">— выберите —</option>
        <?php foreach ($patients as $p): ?>
            <?php $pid = (int)$p['id']; ?>
            <option value="<?= $pid ?>" <?= ($values['patient_id'] === (string)$pid) ? 'selected' : '' ?>>
                <?= $pid ?> — <?= h(build_full_name($p)) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Услуга *</label>
    <select name="service_id" required>
        <option value="">— выберите —</option>
        <?php foreach ($services as $s): ?>
            <?php $sid = (int)$s['id']; ?>
            <option value="<?= $sid ?>" <?= ($values['service_id'] === (string)$sid) ? 'selected' : '' ?>>
                <?= $sid ?> — <?= h((string)$s['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Дата/время *</label>
    <input type="datetime-local" name="appointment_datetime" value="<?= h($values['appointment_datetime']) ?>" required>

    <label>Статус *</label>
    <select name="status" required>
        <?php foreach (['запланирована','выполнена','отменена'] as $st): ?>
            <option value="<?= h($st) ?>" <?= ($values['status'] === $st) ? 'selected' : '' ?>><?= h($st) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Заметки</label>
    <textarea name="notes" rows="3" cols="40"><?= h($values['notes']) ?></textarea>

    <div style="margin-top:14px">
        <button class="btn" type="submit">Сохранить</button>
        <a class="btn" href="doctor_schedule.php?doctor_id=<?= (int)$doctorId ?>">Отмена</a>
    </div>
</form>

<?php require __DIR__ . '/../src/layout/footer.php'; ?>