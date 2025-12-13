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
        fwrite(STDERR, "Файл БД не найден: {$dbPath}\n");
        exit(1);
    }

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON;');
    return $pdo;
}

function buildFullName(array $row): string
{
    $patronymic = trim((string)($row['patronymic'] ?? ''));
    return trim($row['surname'] . ' ' . $row['name'] . ' ' . $patronymic);
}

function getDisplayWidth(string $text): int
{
    if (function_exists('mb_strwidth')) {
        return mb_strwidth($text, 'UTF-8');
    }
    return strlen($text);
}

function padCell(string $text, int $width, string $align = 'left'): string
{
    $current = getDisplayWidth($text);
    $padSize = max(0, $width - $current);

    return ($align === 'right')
        ? str_repeat(' ', $padSize) . $text
        : $text . str_repeat(' ', $padSize);
}

function buildBorderLine(string $left, string $mid, string $right, array $widths): string
{
    $line = $left;
    foreach ($widths as $i => $w) {
        $line .= str_repeat('─', $w + 2);
        $line .= ($i === array_key_last($widths)) ? $right : $mid;
    }
    return $line;
}

function renderAsciiTable(array $headers, array $rows, array $aligns = []): void
{
    $widths = [];
    foreach ($headers as $i => $h) {
        $widths[$i] = getDisplayWidth((string)$h);
    }
    foreach ($rows as $row) {
        foreach ($row as $i => $cell) {
            $widths[$i] = max($widths[$i], getDisplayWidth((string)$cell));
        }
    }

    echo buildBorderLine('┌', '┬', '┐', $widths) . PHP_EOL;

    echo '│';
    foreach ($headers as $i => $h) {
        echo ' ' . padCell((string)$h, $widths[$i]) . ' │';
    }
    echo PHP_EOL;

    echo buildBorderLine('├', '┼', '┤', $widths) . PHP_EOL;

    foreach ($rows as $row) {
        echo '│';
        foreach ($row as $i => $cell) {
            $align = $aligns[$i] ?? 'left';
            echo ' ' . padCell((string)$cell, $widths[$i], $align) . ' │';
        }
        echo PHP_EOL;
    }

    echo buildBorderLine('└', '┴', '┘', $widths) . PHP_EOL;
}

function promptUser(string $prompt): string
{
    if (function_exists('readline')) {
        $value = readline($prompt);
        return $value === false ? '' : trim($value);
    }

    echo $prompt;
    $value = fgets(STDIN);
    return $value === false ? '' : trim($value);
}

try {
    $connection = createConnection(resolveDatabasePath());

    $doctorsStmt = $connection->prepare("
        SELECT id, surname, name, patronymic
        FROM employees
        ORDER BY surname, name, patronymic
    ");
    $doctorsStmt->execute();
    $doctors = $doctorsStmt->fetchAll();

    if (!$doctors) {
        echo "В таблице employees нет врачей.\n";
        exit(0);
    }

    $doctorById = [];
    echo "Врачи в базе данных:\n";
    foreach ($doctors as $doctor) {
        $doctorId = (int)$doctor['id'];
        $doctorById[$doctorId] = buildFullName($doctor);
        printf("  %d) %s\n", $doctorId, $doctorById[$doctorId]);
    }
    echo PHP_EOL;

    $selectedDoctorId = null;
    while (true) {
        $input = promptUser("Введите номер врача для фильтра (Enter — по всем): ");
        if ($input === '') {
            $selectedDoctorId = null;
            break;
        }
        if (!ctype_digit($input)) {
            echo "Ошибка: введите число (id врача) или нажмите Enter.\n";
            continue;
        }

        $candidateId = (int)$input;
        if (!array_key_exists($candidateId, $doctorById)) {
            echo "Ошибка: врача с id=$candidateId нет. Попробуйте снова.\n";
            continue;
        }

        $selectedDoctorId = $candidateId;
        break;
    }

    $sql = "
        SELECT
            e.id AS doctor_id,
            e.surname || ' ' || e.name ||
                CASE WHEN e.patronymic IS NULL OR e.patronymic = ''
                     THEN '' ELSE ' ' || e.patronymic END AS doctor_full_name,
            date(pc.completion_date) AS work_date,
            s.name AS service_name,
            printf('%.2f', pc.actual_price) AS price
        FROM procedures_completed pc
        JOIN employees e ON e.id = pc.employee_id
        JOIN services  s ON s.id = pc.service_id
    ";
    if ($selectedDoctorId !== null) {
        $sql .= " WHERE e.id = :doctor_id ";
    }
    $sql .= " ORDER BY e.surname, pc.completion_date ";

    $servicesStmt = $connection->prepare($sql);
    if ($selectedDoctorId !== null) {
        $servicesStmt->bindValue(':doctor_id', $selectedDoctorId, PDO::PARAM_INT);
    }
    $servicesStmt->execute();
    $rows = $servicesStmt->fetchAll();

    echo PHP_EOL;
    if ($selectedDoctorId === null) {
        echo "Оказанные услуги (все врачи)\n";
    } else {
        echo "Оказанные услуги (врач #$selectedDoctorId: {$doctorById[$selectedDoctorId]})\n";
    }

    if (!$rows) {
        echo "Нет выполненных процедур по выбранному фильтру.\n";
        exit(0);
    }

    $headers = ['№ врача', 'ФИО врача', 'Дата', 'Услуга', 'Стоимость'];
    $tableRows = [];
    foreach ($rows as $row) {
        $tableRows[] = [
            (string)$row['doctor_id'],
            (string)$row['doctor_full_name'],
            (string)$row['work_date'],
            (string)$row['service_name'],
            (string)$row['price'],
        ];
    }

    renderAsciiTable($headers, $tableRows, [
        0 => 'right',
        4 => 'right',
    ]);

} catch (PDOException $e) {
    fwrite(STDERR, "Ошибка БД: " . $e->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Ошибка: " . $e->getMessage() . PHP_EOL);
    exit(1);
}