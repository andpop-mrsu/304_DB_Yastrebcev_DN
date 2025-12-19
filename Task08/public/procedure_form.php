<?php
declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';

$pdo = db();
$doctorId = get_int($_GET, 'doctor_id');
if ($doctorId === null) redirect('index.php');

$id = get_int($_GET, 'id');

$docSt = $pdo->prepare("SELECT id, surname, name, patronymic FROM employees WHERE id = :id");
$docSt->execute([':id' => $doctorId]);
$doctor = $docSt->fetch();
if (!$doctor) redirect('index.php');

$patientsSt = $pdo->prepare("SELECT id, surname, name, patronymic FROM patients ORDER BY surname, name, patronymic");
$patientsSt->execute();
$patients = $patientsSt->fetchAll();

$servicesSt = $pdo->prepare("SELECT id, name FROM services ORDER BY name");
$servicesSt->execute();
$services = $servicesSt->fetchAll();

$allowedPatientIds = array_map(fn($x) => (string)$x['id'], $patients);
$allowedServiceIds = array_map(fn($x) => (string)$x['id'], $services);

$apptsSt = $pdo->prepare("
    SELECT id, appointment_datetime
    FROM appointments
    WHERE employee_id = :doctor_id
    ORDER BY appointment_datetime DESC
");
$apptsSt->execute([':doctor_id' => $doctorId]);
$appts = $apptsSt->fetchAll();

$values = [
    'appointment_id' => '',
    'patient_id' => '',
    'service_id' => '',
    'completion_date' => date('Y-m-d\TH:i'),
    'actual_price' => '0',
    'notes' => '',
];

if ($id !== null) {
    $st = $pdo->prepare("SELECT * FROM procedures_completed WHERE id = :id AND employee_id = :doctor_id");
    $st->execute([':id' => $id, ':doctor_id' => $doctorId]);
    $row = $st->fetch();
    if (!$row) redirect("doctor_procedures.php?doctor_id={$doctorId}");

    $values = [
        'appointment_id' => $row['appointment_id'] === null ? '' : (string)$row['appointment_id'],
        'patient_id' => (string)$row['patient_id'],
        'service_id' => (string)$row['service_id'],
        'completion_date' => sql_to_datetime_local((string)$row['completion_date']),
        'actual_price' => (string)$row['actual_price'],
        'notes' => (string)($row['notes'] ?? ''),
    ];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['appointment_id'] = get_str($_POST, 'appointment_id');
    $values['patient_id'] = get_str($_POST, 'patient_id');
    $values['service_id'] = get_str($_POST, 'service_id');
    $values['completion_date'] = get_str($_POST, 'completion_date');
    $values['actual_price'] = get_str($_POST, 'actual_price');
    $values['notes'] = get_str($_POST, 'notes');
    
    $appointmentId = null;
    if ($values['appointment_id'] !== '') {
        if (!ctype_digit($values['appointment_id'])) {
            $errors[] = 'Некорректный appointment_id.';
        } else {
            $chk = $pdo->prepare("SELECT 1 FROM appointments WHERE id = :id AND employee_id = :doctor_id");
            $chk->execute([':id' => (int)$values['appointment_id'], ':doctor_id' => $doctorId]);
            if (!$chk->fetchColumn()) {
                $errors[] = 'Выбранный appointment_id не принадлежит этому врачу.';
            } else {
                $appointmentId = (int)$values['appointment_id'];
            }
        }
    }

    if ($values['patient_id'] === '' || !in_array($values['patient_id'], $allowedPatientIds, true)) {
    $errors[] = 'Выберите корректного пациента.';
    }

    if ($values['service_id'] === '' || !in_array($values['service_id'], $allowedServiceIds, true)) {
    $errors[] = 'Выберите корректную услугу.';
    }

    $price = filter_var($values['actual_price'], FILTER_VALIDATE_FLOAT);
    if ($price === false || $price < 0) $errors[] = 'Стоимость должна быть числом >= 0.';

    if ($values['completion_date'] === '') $errors[] = 'Укажите дату/время выполнения.';
    $sqlDt = datetime_local_to_sql($values['completion_date']);
    if ($sqlDt === '') $errors[] = 'Некорректная дата/время.';

    if (!$errors) {
        if ($id === null) {
            $ins = $pdo->prepare("
                INSERT INTO procedures_completed
                    (appointment_id, employee_id, service_id, patient_id, completion_date, actual_price, notes)
                VALUES
                    (:appointment_id, :employee_id, :service_id, :patient_id, :completion_date, :actual_price, :notes)
            ");
            $ins->execute([
                ':appointment_id' => $appointmentId,
                ':employee_id' => $doctorId,
                ':service_id' => (int)$values['service_id'],
                ':patient_id' => (int)$values['patient_id'],
                ':completion_date' => $sqlDt,
                ':actual_price' => (float)$values['actual_price'],
                ':notes' => $values['notes'] !== '' ? $values['notes'] : null,
            ]);
        } else {
            $upd = $pdo->prepare("
                UPDATE procedures_completed
                   SET appointment_id = :appointment_id,
                       service_id = :service_id,
                       patient_id = :patient_id,
                       completion_date = :completion_date,
                       actual_price = :actual_price,
                       notes = :notes
                 WHERE id = :id AND employee_id = :employee_id
            ");
            $upd->execute([
                ':appointment_id' => $appointmentId,
                ':service_id' => (int)$values['service_id'],
                ':patient_id' => (int)$values['patient_id'],
                ':completion_date' => $sqlDt,
                ':actual_price' => (float)$values['actual_price'],
                ':notes' => $values['notes'] !== '' ? $values['notes'] : null,
                ':id' => $id,
                ':employee_id' => $doctorId,
            ]);
        }

        redirect("doctor_procedures.php?doctor_id={$doctorId}");
    }
}

$title = $id === null ? 'Добавить оказанную услугу' : 'Редактировать оказанную услугу';
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
    <label>Связать с записью графика (appointment_id, необязательно)</label>
    <select name="appointment_id">
        <option value="">— не связывать —</option>
        <?php foreach ($appts as $a): ?>
            <?php $aid = (int)$a['id']; ?>
            <option value="<?= $aid ?>" <?= ($values['appointment_id'] === (string)$aid) ? 'selected' : '' ?>>
                <?= $aid ?> — <?= h((string)$a['appointment_datetime']) ?>
            </option>
        <?php endforeach; ?>
    </select>

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

    <label>Дата/время выполнения *</label>
    <input type="datetime-local" name="completion_date" value="<?= h($values['completion_date']) ?>" required>

    <label>Фактическая стоимость (>=0) *</label>
    <input name="actual_price" value="<?= h($values['actual_price']) ?>" required>

    <label>Заметки</label>
    <textarea name="notes" rows="3" cols="40"><?= h($values['notes']) ?></textarea>

    <div style="margin-top:14px">
        <button class="btn" type="submit">Сохранить</button>
        <a class="btn" href="doctor_procedures.php?doctor_id=<?= (int)$doctorId ?>">Отмена</a>
    </div>
</form>

<?php require __DIR__ . '/../src/layout/footer.php'; ?>