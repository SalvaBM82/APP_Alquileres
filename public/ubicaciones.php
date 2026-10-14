<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$user=current_user();$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$action=$_POST['action']??'';
 try{
  if($action==='ubicacion_save'){
   $id=(int)($_POST['id']??0);$nombre=trim($_POST['nombre']??'');$tipo=$_POST['tipo']??'INDEPENDIENTE';
   if($nombre==='')throw new RuntimeException('El nombre de la ubicación es obligatorio.');
   if(!in_array($tipo,['INDEPENDIENTE','MULTIUNIDAD'],true))throw new RuntimeException('Selecciona un tipo de ubicación válido.');
   $data=[$nombre,$tipo,trim($_POST['direccion']??''),trim($_POST['descripcion']??''),($_POST['estado']??'ACTIVA')==='INACTIVA'?'INACTIVA':'ACTIVA'];
   if($id){$data[]=$id;$pdo->prepare('UPDATE ubicaciones SET nombre=?,tipo=?,direccion=?,descripcion=?,estado=? WHERE id=?')->execute($data);$ok='Ubicación actualizada.';}
   else{$pdo->prepare('INSERT INTO ubicaciones(nombre,tipo,direccion,descripcion,estado) VALUES(?,?,?,?,?)')->execute($data);$id=(int)$pdo->lastInsertId();if($tipo==='INDEPENDIENTE')$pdo->prepare("INSERT INTO unidades(ubicacion_id,nombre,tipo,estado) VALUES(?,'Vivienda principal','VIVIENDA','ACTIVA')")->execute([$id]);$ok='Ubicación creada.';}
  }elseif($action==='unidad_save'){
   $id=(int)($_POST['id']??0);$loc=(int)($_POST['ubicacion_id']??0);$nombre=trim($_POST['nombre']??'');$tipo=$_POST['tipo_unidad']??'VIVIENDA';
   if($nombre==='')throw new RuntimeException('El nombre de la unidad es obligatorio.');
   if(!in_array($tipo,['VIVIENDA','HABITACION','LOCAL','OTRA'],true))throw new RuntimeException('Selecciona un tipo de unidad válido.');
   $q=$pdo->prepare("SELECT COUNT(*) FROM ubicaciones WHERE id=?");$q->execute([$loc]);if(!$q->fetchColumn())throw new RuntimeException('Selecciona una ubicación existente.');
   $data=[$loc,$nombre,$tipo,trim($_POST['descripcion_unidad']??''),($_POST['estado_unidad']??'ACTIVA')==='INACTIVA'?'INACTIVA':'ACTIVA'];
   if($id){$data[]=$id;$pdo->prepare('UPDATE unidades SET ubicacion_id=?,nombre=?,tipo=?,descripcion=?,estado=? WHERE id=?')->execute($data);$ok='Unidad actualizada.';}
   else{$pdo->prepare('INSERT INTO unidades(ubicacion_id,nombre,tipo,descripcion,estado) VALUES(?,?,?,?,?)')->execute($data);$ok='Unidad creada.';}
  }elseif($action==='unidad_delete'){
   $id=(int)($_POST['id']??0);$q=$pdo->prepare('SELECT COUNT(*) FROM inquilinos WHERE unidad_id=?');$q->execute([$id]);if((int)$q->fetchColumn())throw new RuntimeException('Esta unidad tiene inquilinos asociados. Reasígnalos antes de eliminarla.');
   $pdo->prepare('DELETE FROM unidades WHERE id=?')->execute([$id]);$ok='Unidad eliminada.';
  }elseif($action==='ubicacion_delete'){
   $id=(int)($_POST['id']??0);$q=$pdo->prepare('SELECT COUNT(*) FROM unidades WHERE ubicacion_id=?');$q->execute([$id]);if((int)$q->fetchColumn())throw new RuntimeException('Esta ubicación todavía contiene unidades. Elimina o mueve sus unidades antes.');
   $pdo->prepare('DELETE FROM ubicaciones WHERE id=?')->execute([$id]);$ok='Ubicación eliminada.';
  }
 }catch(Throwable $e){$error=$e instanceof PDOException?'No se pudo guardar. Comprueba que no haya nombres duplicados.':$e->getMessage();}
}
$editLoc=null;$editUnit=null;
if(isset($_GET['editar_ubicacion'])){$q=$pdo->prepare('SELECT * FROM ubicaciones WHERE id=?');$q->execute([(int)$_GET['editar_ubicacion']]);$editLoc=$q->fetch()?:null;}
if(isset($_GET['editar_unidad'])){$q=$pdo->prepare('SELECT * FROM unidades WHERE id=?');$q->execute([(int)$_GET['editar_unidad']]);$editUnit=$q->fetch()?:null;}
$locations=$pdo->query("SELECT l.*,(SELECT COUNT(*) FROM unidades u WHERE u.ubicacion_id=l.id) unidades,(SELECT COUNT(*) FROM inquilinos t JOIN unidades u ON u.id=t.unidad_id WHERE u.ubicacion_id=l.id) inquilinos FROM ubicaciones l ORDER BY l.estado='ACTIVA' DESC,l.nombre")->fetchAll();
$units=$pdo->query("SELECT u.*,l.nombre ubicacion_nombre,(SELECT COUNT(*) FROM inquilinos t WHERE t.unidad_id=u.id) inquilinos FROM unidades u JOIN ubicaciones l ON l.id=u.ubicacion_id ORDER BY l.nombre,u.nombre")->fetchAll();
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ubicaciones e inmuebles · APP Alquileres</title><link rel="stylesheet" href="assets/app.css">
<style>
.locations-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin:18px 0}
.location-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr));gap:16px}
.location-card{padding:20px;border:1px solid var(--border,#e3e7ed);border-radius:14px;background:var(--panel,#fff);min-width:0}
.location-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.location-card h3{margin:0 0 6px}.location-address{margin:0;color:var(--muted,#667085);font-size:.92rem}
.location-stats{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}
.location-stat{padding:9px 12px;border-radius:9px;background:var(--surface,#f5f7fa);font-size:.9rem}
.location-stat strong{display:block;font-size:1.15rem}
.location-units{border-top:1px solid var(--border,#e3e7ed);margin-top:14px;padding-top:14px}
.location-units-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}
.unit-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 0;border-bottom:1px solid var(--border,#edf0f4)}
.unit-row:last-child{border-bottom:0}.unit-main{min-width:0}.unit-main strong{display:block}.unit-meta{font-size:.85rem;color:var(--muted,#667085)}
.unit-actions{display:flex;gap:6px;flex-shrink:0}.unit-actions .btn,.location-actions .btn{white-space:nowrap}
.location-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:14px}
.status-pill{display:inline-block;padding:4px 8px;border-radius:999px;font-size:.78rem;background:#e8f7ed;color:#17663a}
.status-pill.inactive{background:#f1f2f4;color:#606773}
.modal-backdrop{border:0;border-radius:16px;padding:0;width:min(620px,calc(100% - 28px));max-width:620px;max-height:88vh;box-shadow:0 20px 70px #0003}
.modal-backdrop::backdrop{background:#18223088;backdrop-filter:blur(2px)}
.modal-inner{padding:22px;max-height:88vh;overflow:auto;background:var(--panel,#fff);color:inherit}
.modal-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
.modal-head h2{margin:0;font-size:1.25rem}.modal-head button{font-size:1.5rem;line-height:1;border:0;background:transparent;cursor:pointer}
.modal-inner .form-grid{margin:0}.modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:18px}
@media(max-width:600px){.location-card{padding:15px}.location-card-head{flex-direction:column}.unit-row{align-items:flex-start}.unit-actions{flex-direction:column}.modal-inner{padding:16px}}
</style></head>
<body>
<header class="topbar"><a class="brand" href="index.php"><span class="brand-icon">⌂</span><span>APP Alquileres<small>Ubicaciones e inmuebles</small></span></a><a class="btn outline" href="index.php">Volver al inicio</a></header>
<main class="content">
<div class="page-head"><div><h1>Ubicaciones e inmuebles</h1><p class="muted">Organiza tus propiedades y sus viviendas, pisos, habitaciones o locales desde un único lugar.</p></div></div>
<?php if($ok):?><div class="notice"><?=e($ok)?></div><?php endif;?><?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?>
<div class="locations-toolbar"><div><strong><?=count($locations)?> ubicación<?=count($locations)===1?'':'es'?></strong><span class="muted"> · <?=count($units)?> unidades registradas</span></div><button class="btn primary" type="button" data-open-modal="location-modal">+ Nueva ubicación</button></div>
<?php if(!$locations):?><section class="panel empty-state"><h3>Empieza añadiendo una ubicación</h3><p class="muted">Puede ser una vivienda independiente o un edificio con varias unidades.</p><button class="btn primary" type="button" data-open-modal="location-modal">Crear primera ubicación</button></section><?php else:?>
<div class="location-grid">
<?php foreach($locations as $l): $locationUnits=array_values(array_filter($units,static fn($u)=>(int)$u['ubicacion_id']===(int)$l['id']));?>
<article class="location-card">
<div class="location-card-head"><div><h3><?=e($l['nombre'])?></h3><p class="location-address"><?=e($l['direccion']?:'Sin dirección indicada')?></p></div><span class="status-pill <?=$l['estado']==='ACTIVA'?'':'inactive'?>"><?=e($l['estado']==='ACTIVA'?'Activa':'Inactiva')?></span></div>
<div class="location-stats"><div class="location-stat"><strong><?=$l['unidades']?></strong>Unidades</div><div class="location-stat"><strong><?=$l['inquilinos']?></strong>Inquilinos</div><div class="location-stat"><strong><?=$l['tipo']==='INDEPENDIENTE'?'Independiente':'Multiunidad'?></strong>Tipo</div></div>
<?php if(!empty($l['descripcion'])):?><p class="muted"><?=e($l['descripcion'])?></p><?php endif;?>
<div class="location-units"><div class="location-units-head"><strong>Unidades de esta ubicación</strong><button class="btn outline" type="button" data-open-modal="unit-modal" data-location-id="<?=$l['id']?>">+ Añadir</button></div>
<?php if(!$locationUnits):?><p class="muted">Todavía no hay unidades.</p><?php else: foreach($locationUnits as $u):?>
<div class="unit-row"><div class="unit-main"><strong><?=e($u['nombre'])?></strong><div class="unit-meta"><?=e(['VIVIENDA'=>'Vivienda','HABITACION'=>'Habitación','LOCAL'=>'Local','OTRA'=>'Otra unidad'][$u['tipo']]??$u['tipo'])?> · <?=$u['inquilinos']?> inquilino<?=$u['inquilinos']==1?'':'s'?> · <?=e($u['estado']==='ACTIVA'?'Activa':'Inactiva')?></div></div><div class="unit-actions"><a class="btn outline" href="ubicaciones.php?editar_unidad=<?=$u['id']?>">Editar</a><?php if((int)$u['inquilinos']===0):?><form method="post" onsubmit="return confirm('¿Eliminar esta unidad?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="unidad_delete"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="btn danger">Borrar</button></form><?php endif;?></div></div>
<?php endforeach; endif;?></div>
<div class="location-actions"><a class="btn outline" href="index.php?ubicacion=<?=$l['id']?>">Ver gestión de alquileres</a><a class="btn outline" href="ubicaciones.php?editar_ubicacion=<?=$l['id']?>">Editar ubicación</a><?php if((int)$l['unidades']===0):?><form method="post" onsubmit="return confirm('¿Eliminar esta ubicación?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="ubicacion_delete"><input type="hidden" name="id" value="<?=$l['id']?>"><button class="btn danger">Borrar</button></form><?php endif;?></div>
</article>
<?php endforeach;?>
</div><?php endif;?>
<footer>APP Alquileres · Gestión multiubicación</footer></main>

<dialog class="modal-backdrop" id="location-modal"><div class="modal-inner"><div class="modal-head"><h2><?=$editLoc?'Editar ubicación':'Nueva ubicación'?></h2><button type="button" data-close-modal aria-label="Cerrar">×</button></div>
<form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="ubicacion_save"><input type="hidden" name="id" value="<?=e($editLoc['id']??0)?>">
<div class="full"><label>Nombre *</label><input name="nombre" required value="<?=e($editLoc['nombre']??'')?>"></div><div class="full"><label>Tipo de ubicación</label><select name="tipo"><option value="INDEPENDIENTE" <?=($editLoc['tipo']??'INDEPENDIENTE')==='INDEPENDIENTE'?'selected':''?>>Vivienda independiente</option><option value="MULTIUNIDAD" <?=($editLoc['tipo']??'')==='MULTIUNIDAD'?'selected':''?>>Edificio / ubicación con varias unidades</option></select></div><div class="full"><label>Dirección</label><input name="direccion" value="<?=e($editLoc['direccion']??'')?>"></div><div class="full"><label>Descripción / notas</label><textarea name="descripcion" rows="2"><?=e($editLoc['descripcion']??'')?></textarea></div><div class="full"><label>Estado</label><select name="estado"><option value="ACTIVA" <?=($editLoc['estado']??'ACTIVA')==='ACTIVA'?'selected':''?>>Activa</option><option value="INACTIVA" <?=($editLoc['estado']??'')==='INACTIVA'?'selected':''?>>Inactiva</option></select></div><div class="full modal-actions"><button type="button" class="btn outline" data-close-modal>Cancelar</button><button class="btn primary">Guardar ubicación</button></div></form></div></dialog>

<dialog class="modal-backdrop" id="unit-modal"><div class="modal-inner"><div class="modal-head"><h2><?=$editUnit?'Editar unidad':'Nueva unidad'?></h2><button type="button" data-close-modal aria-label="Cerrar">×</button></div>
<form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="unidad_save"><input type="hidden" name="id" value="<?=e($editUnit['id']??0)?>">
<div class="full"><label>Ubicación *</label><select name="ubicacion_id" id="unit-location" required><?php foreach($locations as $l):?><option value="<?=$l['id']?>" <?=((int)($editUnit['ubicacion_id']??0)===(int)$l['id'])?'selected':''?>><?=e($l['nombre'])?></option><?php endforeach;?></select></div><div class="full"><label>Nombre de la unidad *</label><input name="nombre" required value="<?=e($editUnit['nombre']??'')?>" placeholder="Ej. Piso 1, Habitación A"></div><div class="full"><label>Tipo de unidad</label><select name="tipo_unidad"><?php foreach(['VIVIENDA'=>'Vivienda','HABITACION'=>'Habitación','LOCAL'=>'Local','OTRA'=>'Otra unidad'] as $v=>$label):?><option value="<?=$v?>" <?=($editUnit['tipo']??'VIVIENDA')===$v?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div><div class="full"><label>Estado</label><select name="estado_unidad"><option value="ACTIVA" <?=($editUnit['estado']??'ACTIVA')==='ACTIVA'?'selected':''?>>Activa</option><option value="INACTIVA" <?=($editUnit['estado']??'')==='INACTIVA'?'selected':''?>>Inactiva</option></select></div><div class="full"><label>Descripción</label><textarea name="descripcion_unidad" rows="2"><?=e($editUnit['descripcion']??'')?></textarea></div><div class="full modal-actions"><button type="button" class="btn outline" data-close-modal>Cancelar</button><button class="btn primary">Guardar unidad</button></div></form></div></dialog>
<script>
document.querySelectorAll('[data-open-modal]').forEach(button=>button.addEventListener('click',()=>{const modal=document.getElementById(button.dataset.openModal);if(button.dataset.locationId){const select=document.getElementById('unit-location');if(select)select.value=button.dataset.locationId;}if(modal)modal.showModal();}));
document.querySelectorAll('[data-close-modal]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog').close()));
document.querySelectorAll('dialog').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();}));
<?php if($editLoc):?>document.getElementById('location-modal').showModal();<?php endif;?>
<?php if($editUnit):?>document.getElementById('unit-modal').showModal();<?php endif;?>
</script></body></html>