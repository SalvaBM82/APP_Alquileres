<?php
require __DIR__ . '/../app/bootstrap.php'; require_admin();
$name=basename($_GET['name']??'');
if(!preg_match('/^(backup|uploaded)_[0-9-]+_[0-9-]+\.sql$/',$name)) { http_response_code(400); exit('Nombre no válido'); }
$path=__DIR__.'/../backups/'.$name;
if(!is_file($path)){http_response_code(404);exit('Copia no encontrada');}
header('Content-Type: application/sql');header('Content-Disposition: attachment; filename="'.$name.'"');header('Content-Length: '.filesize($path));readfile($path);exit;
