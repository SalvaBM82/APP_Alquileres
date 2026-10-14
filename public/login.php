<?php
require __DIR__ . '/../app/bootstrap.php';
if (current_user()) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare('SELECT id,nombre,email,password_hash,rol,activo FROM usuarios WHERE email=? LIMIT 1');
    $stmt->execute([trim($_POST['email'] ?? '')]); $u = $stmt->fetch();
    if ($u && (int)$u['activo'] === 1 && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true); unset($u['password_hash']); $_SESSION['user'] = $u;
        header('Location: index.php'); exit;
    }
    $error = 'Email o contraseña incorrectos, o usuario inactivo.';
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=e(app_setting('titulo_web','APP Alquileres · Gestión de propiedades'))?> · Acceso</title><link rel="icon" type="image/svg+xml" href="assets/favicon.svg"><link rel="stylesheet" href="assets/app.css"><style>:root{--blue:<?=e(app_setting('color_principal','#2859a6'))?>}</style></head><body class="login-body"><form class="login-card" method="post"><img src="assets/logo.svg" alt="APP Alquileres" style="display:block;width:260px;max-width:100%;height:auto;margin:0 auto 18px"><h1 style="display:none">APP Alquileres</h1><p class="muted">Gestión de inquilinos y cuentas</p><?php if($error): ?><div class="notice error"><?=e($error)?></div><?php endif; ?><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>Email</label><input type="email" name="email" required autocomplete="username"><label>Contraseña</label><input type="password" name="password" required autocomplete="current-password"><button class="btn primary wide">Entrar</button><small>Instalación inicial: visita setup.php una sola vez y después elimínalo.</small></form></body></html>
