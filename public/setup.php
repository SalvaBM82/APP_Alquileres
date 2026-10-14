<?php
require __DIR__ . '/../app/bootstrap.php';
$count = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$message = '';
if ($count > 0) { http_response_code(403); exit('La aplicación ya tiene usuarios. Elimina este archivo de instalación.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $message = 'Indica nombre, email válido y contraseña de al menos 8 caracteres.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO usuarios(nombre,email,password_hash,rol,activo) VALUES(?,?,?,?,1)');
        $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT), 'ADMIN']);
        exit('Administrador creado. Por seguridad, elimina ahora public/setup.php y accede desde login.php.');
    }
}
?><!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalación inicial</title><link rel="stylesheet" href="assets/app.css"><body class="login-body"><form method="post" class="login-card"><h1>Crear administrador</h1><p>Esta pantalla solo funciona cuando aún no existen usuarios.</p><?php if($message): ?><div class="notice error"><?=e($message)?></div><?php endif; ?><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>Nombre</label><input name="nombre" required><label>Email</label><input type="email" name="email" required><label>Contraseña (mínimo 8 caracteres)</label><input type="password" name="password" minlength="8" required><button class="btn primary wide">Crear administrador</button></form></body></html>
