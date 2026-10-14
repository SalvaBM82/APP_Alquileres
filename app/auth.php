<?php
declare(strict_types=1);
/* Autenticación centralizada para APP Alquileres. */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.');
    }
}
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void {
    if (!current_user()) { header('Location: login.php'); exit; }
}
function require_admin(): void {
    require_login();
    if ((current_user()['rol'] ?? '') !== 'ADMIN') {
        http_response_code(403);
        exit('No tienes permiso para realizar esta acción.');
    }
}
