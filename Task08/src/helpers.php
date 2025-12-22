<?php
declare(strict_types=1);

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function get_int(array $src, string $key): ?int
{
    if (!isset($src[$key]) || $src[$key] === '') return null;
    $v = (string)$src[$key];
    if (!ctype_digit($v)) return null;
    return (int)$v;
}

function get_str(array $src, string $key): string
{
    return trim((string)($src[$key] ?? ''));
}

function sql_to_datetime_local(?string $sql): string
{
    if (!$sql) return '';
    $sql = trim($sql);
    if (strlen($sql) < 16) return '';
    $sql = str_replace(' ', 'T', $sql);
    return substr($sql, 0, 16);
}

function datetime_local_to_sql(string $v): string
{
    $v = trim($v);
    if ($v === '') return '';
    $v = str_replace('T', ' ', $v);
    if (strlen($v) === 16) {
        $v .= ':00';
    }
    return $v;
}

function build_full_name(array $row): string
{
    $pat = trim((string)($row['patronymic'] ?? ''));
    return trim($row['surname'] . ' ' . $row['name'] . ' ' . $pat);
}