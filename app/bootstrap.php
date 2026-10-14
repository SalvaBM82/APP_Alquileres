<?php
declare(strict_types=1);
$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Madrid');
if (session_status() !== PHP_SESSION_ACTIVE) { session_name('APPALQUILERES'); session_start(); }
try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4", $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    exit('No se pudo conectar a la base de datos. Revisa config/config.php e importa database.sql.');
}
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money($v): string { return number_format((float)$v, 2, ',', '.') . ' €'; }
function url(string $page='dashboard', array $params=[]): string {
    $cfg = require __DIR__ . '/../config/config.php';
    return $cfg['base_url'] . '/index.php?' . http_build_query(array_merge(['page'=>$page],$params));
}
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.');
    }
}
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void { if (!current_user()) { header('Location: login.php'); exit; } }
function require_admin(): void { require_login(); if ((current_user()['rol'] ?? '') !== 'ADMIN') { http_response_code(403); exit('No tienes permiso para realizar esta acción.'); } }
function flash(string $msg): void { $_SESSION['flash'] = $msg; }
