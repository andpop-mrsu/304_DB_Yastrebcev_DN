<?php
declare(strict_types=1);

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

function resolveDatabasePath(): string
{
    $localPath = __DIR__ . DIRECTORY_SEPARATOR . 'clinic.db';
    if (is_file($localPath)) {
        return $localPath;
    }

    $task07Path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Task07' . DIRECTORY_SEPARATOR . 'clinic.db';
    if (is_file($task07Path)) {
        return $task07Path;
    }

    return $localPath;
}

function createConnection(string $dbPath): PDO
{
    if (!is_file($dbPath)) {
        throw new RuntimeException("Файл БД не найден: {$dbPath}");
    }

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON;');
    return $pdo;
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function buildFullName(array $row): string
{
    $patronymic = trim((string)($row['patronymic'] ?? ''));
    $fullName = trim($row['surname'] . ' ' . $row['name'] . ' ' . $patronymic);
    return $fullName;
}

$connection = createConnection(resolveDatabasePath());

$doctorsStmt = $connection->prepare("
    SELECT id, surname, name, patronymic
    FROM employees
    ORDER BY surname, name, patronymic
");
$doctorsStmt->execute();
$doctors = $doctorsStmt->fetchAll();

$doctorById = [];
foreach ($doctors as $doctor) {
    $doctorId = (int)$doctor['id'];
    $doctorById[$doctorId] = buildFullName($doctor);
}

$selectedDoctorId = null;
if (isset($_GET['doctor_id']) && $_GET['doctor_id'] !== '') {
    $doctorIdInput = (string)$_GET['doctor_id'];
    if (ctype_digit($doctorIdInput)) {
        $candidateId = (int)$doctorIdInput;
        if (array_key_exists($candidateId, $doctorById)) {
            $selectedDoctorId = $candidateId;
        }
    }
}

$servicesSql = "
    SELECT
        e.id AS doctor_id,
        e.surname || ' ' || e.name ||
            CASE WHEN e.patronymic IS NULL OR e.patronymic = ''
                 THEN '' ELSE ' ' || e.patronymic END AS doctor_full_name,
        date(pc.completion_date) AS work_date,
        s.name AS service_name,
        pc.actual_price AS price
    FROM procedures_completed pc
    JOIN employees e ON e.id = pc.employee_id
    JOIN services  s ON s.id = pc.service_id
";
if ($selectedDoctorId !== null) {
    $servicesSql .= " WHERE e.id = :doctor_id ";
}
$servicesSql .= " ORDER BY e.surname, pc.completion_date ";

$servicesStmt = $connection->prepare($servicesSql);
if ($selectedDoctorId !== null) {
    $servicesStmt->bindValue(':doctor_id', $selectedDoctorId, PDO::PARAM_INT);
}
$servicesStmt->execute();
$rows = $servicesStmt->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Оказанные услуги клиники</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 24px; }
        form { margin-bottom: 16px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        th { background: #f5f5f5; text-align: left; }
        .right { text-align: right; }
        .muted { color: #666; }
    </style>
</head>
<body>

<h1>Список оказанных услуг</h1>

<form method="get">
    <label for="doctor_id">Фильтр по врачу:</label>
    <select name="doctor_id" id="doctor_id">
        <option value="">Все врачи</option>
        <?php foreach ($doctors as $doctor): ?>
            <?php
                $doctorId = (int)$doctor['id'];
                $fullName = buildFullName($doctor);
            ?>
            <option value="<?= $doctorId ?>" <?= ($selectedDoctorId === $doctorId) ? 'selected' : '' ?>>
                <?= $doctorId ?> — <?= escapeHtml($fullName) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Показать</button>
</form>

<?php if (!$rows): ?>
    <p class="muted">Нет выполненных процедур по выбранному фильтру.</p>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>№ врача</th>
            <th>ФИО врача</th>
            <th>Дата</th>
            <th>Услуга</th>
            <th class="right">Стоимость</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td class="right"><?= (int)$row['doctor_id'] ?></td>
                <td><?= escapeHtml((string)$row['doctor_full_name']) ?></td>
                <td><?= escapeHtml((string)$row['work_date']) ?></td>
                <td><?= escapeHtml((string)$row['service_name']) ?></td>
                <td class="right"><?= number_format((float)$row['price'], 2, '.', '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>