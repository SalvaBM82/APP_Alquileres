<?php
require __DIR__ . '/../app/bootstrap.php'; require_admin();
$dir=__DIR__.'/../backups';
if(!is_dir($dir)) mkdir($dir,0750,true);
$msg='';$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$action=$_POST['action']??'';
 try{
  if($action==='create'){
   $cfg=require __DIR__.'/../config/config.php';$stamp=date('Y-m-d_H-i-s');$name='backup_'.$stamp.'.sql';$path=$dir.'/'.$name;
   $cmd='';$mysqldump=trim((string)shell_exec('where mysqldump 2>NUL'));
   if($mysqldump!==''){$bin=trim(explode("\n",$mysqldump)[0]);$cmd='"'.$bin.'" --host='.escapeshellarg($cfg['db_host']).' --user='.escapeshellarg($cfg['db_user']).' '.($cfg['db_pass']!==''?'--password='.escapeshellarg($cfg['db_pass']).' ':'').'--default-character-set=utf8mb4 '.escapeshellarg($cfg['db_name']).' > '.escapeshellarg($path).' 2>&1';@shell_exec($cmd);}
   if(!is_file($path)||filesize($path)<50){@unlink($path);throw new RuntimeException('No se ha podido crear la copia automáticamente. Usa Exportar en phpMyAdmin o configura mysqldump.');}
   $msg='Copia creada: '.$name;
  }elseif($action==='delete'){
   $name=basename($_POST['name']??'');if(!preg_match('/^backup_[0-9-]+_[0-9-]+\.sql$/',$name))throw new RuntimeException('Nombre de copia no válido.');
   if(!is_file($dir.'/'.$name))throw new RuntimeException('No se encuentra la copia.');unlink($dir.'/'.$name);$msg='Copia eliminada.';
  }elseif($action==='restore'){
   $name=basename($_POST['name']??'');if(!preg_match('/^backup_[0-9-]+_[0-9-]+\.sql$/',$name)||!is_file($dir.'/'.$name))throw new RuntimeException('Selecciona una copia válida.');
   throw new RuntimeException('La restauración automática está deshabilitada para evitar sobrescribir datos accidentalmente. Descarga la copia y restáurala en phpMyAdmin tras hacer una copia de seguridad actual.');
  }elseif($action==='upload_restore'){
   if(empty($_FILES['sqlfile']['tmp_name'])||($_FILES['sqlfile']['error']??1)!==UPLOAD_ERR_OK)throw new RuntimeException('Selecciona un archivo SQL válido.');
   if((int)$_FILES['sqlfile']['size']>20*1024*1024)throw new RuntimeException('El archivo supera el límite de 20 MB.');
   $name='uploaded_'.date('Y-m-d_H-i-s').'.sql';if(!move_uploaded_file($_FILES['sqlfile']['tmp_name'],$dir.'/'.$name))throw new RuntimeException('No se pudo guardar el archivo SQL.');
   throw new RuntimeException('Archivo recibido y guardado como '.$name.'. No se ejecutó para evitar borrar datos sin confirmación; restáuralo en phpMyAdmin.');
  }
 }catch(Throwable $e){$err=$e->getMessage();}
}
$files=glob($dir.'/*.sql')?:[];usort($files,fn($a,$b)=>filemtime($b)<=>filemtime($a));
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Copias de seguridad</title><link rel="stylesheet" href="assets/app.css"></head><body><header class="topbar"><a class="brand" href="index.php"><span class="brand-icon">⌂</span><span>APP Alquileres<small>Copias de seguridad</small></span></a><a class="btn outline" href="index.php">Volver</a></header><main class="content"><h1>Copias de seguridad</h1><p class="muted">Incluye inquilinos, cargos, ingresos, fianzas y usuarios. Solo accesible para administradores.</p><?php if($msg):?><div class="notice"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="notice error"><?=e($err)?></div><?php endif;?><section class="panel"><h3>Crear copia</h3><p>Guarda una copia SQL en la carpeta privada backups del proyecto. Descárgala también fuera del servidor.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create"><button class="btn primary">Crear copia ahora</button></form></section><section class="panel"><h3>Subir archivo SQL</h3><p>El archivo se guarda para su revisión, no se ejecuta automáticamente.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="upload_restore"><input type="file" name="sqlfile" accept=".sql" required><button class="btn outline">Subir SQL</button></form></section><section class="panel"><h3>Copias guardadas</h3><div class="table-wrap"><table><tr><th>Archivo</th><th>Fecha</th><th>Tamaño</th><th>Acciones</th></tr><?php foreach($files as $f):?><tr><td><?=e(basename($f))?></td><td><?=date('d/m/Y H:i',filemtime($f))?></td><td><?=number_format(filesize($f)/1024,1,',','.')?> KB</td><td class="actions"><a class="btn outline" href="download_backup.php?name=<?=rawurlencode(basename($f))?>">Descargar</a><form method="post" onsubmit="return confirm('¿Eliminar esta copia?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?=e(basename($f))?>"><button class="btn danger">Borrar</button></form></td></tr><?php endforeach;?><?php if(!$files):?><tr><td colspan="4" class="empty">Todavía no hay copias guardadas.</td></tr><?php endif;?></table></div><p class="muted">Restaurar es una operación destructiva; por ahora se debe hacer desde phpMyAdmin después de descargar la copia actual.</p><a class="btn outline" href="http://localhost/phpmyadmin/" target="_blank" rel="noopener">Abrir phpMyAdmin</a></section></main></body></html>
