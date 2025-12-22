<?php
declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';

$pdo = db();
$id = get_int($_GET, 'id');

$specStmt = $pdo->prepare("SELECT id, name FROM specializations ORDER BY name");
$specStmt->execute();
$specs = $specStmt->fetchAll();

$values = [
    'surname' => '',
    'name' => '',
    'patronymic' => '',
    'specialization_id' => '',
    'salary_percentage' => '40',
    'hire_date' => date('Y-m-d'),
    'status' => 'работает',
    'dismissal_date' => '',
];

if ($id !== null) {
    $st = $pdo->prepare("SELECT * FROM employees WHERE id = :id");
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
        redirect('index.php');
    }
    $values = [
        'surname' => (string)$row['surname'],
        'name' => (string)$row['name'],
        'patronymic' => (string)($row['patronymic'] ?? ''),
        'specialization_id' => (string)$row['specialization_id'],
        'salary_percentage' => (string)$row['salary_percentage'],
        'hire_date' => (string)$row['hire_date'],
        'status' => (string)$row['status'],
        'dismissal_date' => (string)($row['dismissal_date'] ?? ''),
    ];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['surname'] = get_str($_POST, 'surname');
    $values['name'] = get_str($_POST, 'name');
    $values['patronymic'] = get_str($_POST, 'patronymic');
    $values['specialization_id'] = get_str($_POST, 'specialization_id');
    $values['salary_percentage'] = get_str($_POST, 'salary_percentage');
    $values['hire_date'] = get_str($_POST, 'hire_date');
    $values['status'] = get_str($_POST, 'status');
    $values['dismissal_date'] = get_str($_POST, 'dismissal_date');

    if ($values['surname'] === '') $errors[] = 'Фамилия обязательна.';
    if ($values['name'] === '') $errors[] = 'Имя обязательно.';

    $allowedSpecIds = array_map(fn($x) => (string)$x['id'], $specs);
    if (!in_array($values['specialization_id'], $allowedSpecIds, true)) {
    $errors[] = 'Выберите корректную специализацию.';
    }

    $salary = filter_var($values['salary_percentage'], FILTER_VALIDATE_FLOAT);
    if ($salary === false || $salary <= 0 || $salary > 100) {
        $errors[] = 'Процент зарплаты должен быть > 0 и <= 100.';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['hire_date'])) {
        $errors[] = 'Дата приема должна быть в формате YYYY-MM-DD.';
    }

    if (!in_array($values['status'], ['работает', 'уволен'], true)) {
        $errors[] = 'Некорректный статус.';
    }

    if ($values['status'] === 'уволен') {
        if ($values['dismissal_date'] === '') {
            $errors[] = 'Для статуса "уволен" нужно указать дату увольнения.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['dismissal_date'])) {
            $errors[] = 'Дата увольнения должна быть в формате YYYY-MM-DD .';
        }
    } else {
        $values['dismissal_date'] = '';
    }

    if ($values['status'] === 'уволен' && $values['dismissal_date'] !== '' && $values['dismissal_date'] < $values['hire_date']) {
    $errors[] = 'Дата увольнения не может быть раньше даты приема.';
    }

    if (!$errors) {
        if ($id === null) {
            $ins = $pdo->prepare("
                INSERT INTO employees
                    (surname, name, patronymic, specialization_id, salary_percentage, hire_date, status, dismissal_date)
                VALUES
                    (:surname, :name, :patronymic, :specialization_id, :salary_percentage, :hire_date, :status, :dismissal_date)
            ");
            $ins->execute([
                ':surname' => $values['surname'],
                ':name' => $values['name'],
                ':patronymic' => $values['patronymic'] !== '' ? $values['patronymic'] : null,
                ':specialization_id' => (int)$values['specialization_id'],
                ':salary_percentage' => (float)$values['salary_percentage'],
                ':hire_date' => $values['hire_date'],
                ':status' => $values['status'],
                ':dismissal_date' => $values['dismissal_date'] !== '' ? $values['dismissal_date'] : null,
            ]);
        } else {
            $upd = $pdo->prepare("
                UPDATE employees
                   SET surname = :surname,
                       name = :name,
                       patronymic = :patronymic,
                       specialization_id = :specialization_id,
                       salary_percentage = :salary_percentage,
                       hire_date = :hire_date,
                       status = :status,
                       dismissal_date = :dismissal_date
                 WHERE id = :id
            ");
            $upd->execute([
                ':surname' => $values['surname'],
                ':name' => $values['name'],
                ':patronymic' => $values['patronymic'] !== '' ? $values['patronymic'] : null,
                ':specialization_id' => (int)$values['specialization_id'],
                ':salary_percentage' => (float)$values['salary_percentage'],
                ':hire_date' => $values['hire_date'],
                ':status' => $values['status'],
                ':dismissal_date' => $values['dismissal_date'] !== '' ? $values['dismissal_date'] : null,
                ':id' => $id,
            ]);
        }

        redirect('index.php');
    }
}

$title = $id === null ? 'Добавить врача' : 'Редактировать врача';
require __DIR__ . '/../src/layout/header.php';
?>

<h1><?= h($title) ?></h1>

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
    <label>Фамилия *</label>
    <input name="surname" value="<?= h($values['surname']) ?>" required>

    <label>Имя *</label>
    <input name="name" value="<?= h($values['name']) ?>" required>

    <label>Отчество</label>
    <input name="patronymic" value="<?= h($values['patronymic']) ?>">

    <label>Специализация *</label>
    <select name="specialization_id" required>
        <option value="">— выберите —</option>
        <?php foreach ($specs as $sp): ?>
            <?php $sid = (int)$sp['id']; ?>
            <option value="<?= $sid ?>" <?= ($values['specialization_id'] === (string)$sid) ? 'selected' : '' ?>>
                <?= h((string)$sp['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Процент зарплаты (0..100) *</label>
    <input name="salary_percentage" value="<?= h($values['salary_percentage']) ?>" required>

    <label>Дата приема *</label>
    <input type="date" name="hire_date" value="<?= h($values['hire_date']) ?>" required>

    <label>Статус *</label>
    <select name="status" required>
        <option value="работает" <?= ($values['status'] === 'работает') ? 'selected' : '' ?>>работает</option>
        <option value="уволен" <?= ($values['status'] === 'уволен') ? 'selected' : '' ?>>уволен</option>
    </select>

    <label>Дата увольнения (если уволен)</label>
    <input type="date" name="dismissal_date" value="<?= h($values['dismissal_date']) ?>">

    <div style="margin-top:14px">
        <button class="btn" type="submit">Сохранить</button>
        <a class="btn" href="index.php">Отмена</a>
    </div>
</form>

<?php require __DIR__ . '/../src/layout/footer.php'; ?>