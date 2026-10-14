<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
header('Location: index.php?page=usuarios');
exit;
