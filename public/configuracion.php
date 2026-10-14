<?php
require __DIR__ . '/../app/bootstrap.php';
require_admin();
$user=current_user();
$defaults=['nombre_app'=>'APP Alquileres','titulo_web'=>'APP Alquileres · Gestión de propiedades','subtitulo_app'=>'Gestión de propiedades','color_principal'=>'#2859a6'];
$pdo->exec("INSERT IGNORE INTO configuracion_app(clave,valor) VALUES ('titulo_web','APP Alquileres · Gestión de propiedades')");
$settings=$defaults;
$q=$pdo->query('SELECT clave,valor FROM configuracion_app');
foreach($q as $row){if(array_key_exists($row['clave'],$settings))$settings[$row['clave']]=$row['valor'];}
$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $name=trim($_POST['nombre_app']??'');$title=trim($_POST['titulo_web']??'');$subtitle=trim($_POST['subtitulo_app']??'');$color=strtolower(trim($_POST['color_principal']??'#2859a6'));
 if($name==='')$error='El nombre de la aplicación no puede estar vacío.';
 elseif($title==='')$error='El título de la web no puede estar vacío.';
 elseif(mb_strlen($title)>160)$error='El título de la web no puede superar los 160 caracteres.';
 elseif(!preg_match('/^#[0-9a-f]{6}$/',$color))$error='El color debe ser un código hexadecimal válido, por ejemplo #2859a6.';
 else{
  $save=$pdo->prepare('INSERT INTO configuracion_app(clave,valor) VALUES(?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
  foreach(['nombre_app'=>$name,'titulo_web'=>$title,'subtitulo_app'=>$subtitle,'color_principal'=>$color] as $key=>$value)$save->execute([$key,$value]);
  $settings=['nombre_app'=>$name,'titulo_web'=>$title,'subtitulo_app'=>$subtitle,'color_principal'=>$color];$ok='Configuración guardada.';
 }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($settings['titulo_web'])?> · Configuración</title><link rel="icon" type="image/svg+xml" href="assets/favicon.svg"><link rel="stylesheet" href="assets/app.css"><style>:root{--blue:<?=e($settings['color_principal'])?>}</style></head><body>
<header class="topbar"><a class="brand" href="index.php"><img src="assets/logo.svg" alt="<?=e($settings['nombre_app'])?>" style="width:230px;max-width:38vw;height:auto"></a><div class="userbox"><span><?=e($user['nombre'])?> · <?=e($user['rol'])?></span><a class="btn outline" href="logout.php">Cerrar sesión</a></div></header>
<div class="layout"><nav class="sidebar" aria-label="Menú principal">
<div class="nav-heading">MENÚ PRINCIPAL</div><a class="nav-item" href="index.php"><span class="nav-icon">🏠</span><span>Dashboard</span></a>
<div class="nav-divider"><span>ALQUILERES</span></div><a class="nav-item" href="ubicaciones.php"><span class="nav-icon">🏢</span><span>Ubicaciones e inmuebles</span></a><a class="nav-item" href="index.php?page=inquilinos"><span class="nav-icon">👥</span><span>Inquilinos</span></a>
<div class="nav-divider"><span>ECONOMÍA</span></div><a class="nav-item" href="index.php?page=cargos"><span class="nav-icon">📋</span><span>Cargos</span></a><a class="nav-item" href="index.php?page=ingresos"><span class="nav-icon">💶</span><span>Ingresos</span></a><a class="nav-item" href="index.php?page=fianzas"><span class="nav-icon">🔐</span><span>Fianzas</span></a>
<div class="nav-divider"><span>HERRAMIENTAS</span></div><a class="nav-item" href="estadisticas.php"><span class="nav-icon">📊</span><span>Estadísticas</span></a><?php if($user['rol']==='ADMIN'):?><a class="nav-item" href="index.php?page=usuarios"><span class="nav-icon">⚙️</span><span>Usuarios</span></a><a class="nav-item" href="backup.php"><span class="nav-icon">💾</span><span>Copias de seguridad</span></a><?php endif;?>
<div class="nav-spacer"></div><a class="nav-item active" href="configuracion.php" aria-current="page"><span class="nav-icon">🛠️</span><span>Configuración</span></a><a class="nav-item nav-help" href="ayuda.php"><span class="nav-icon">❔</span><span>Ayuda y manual</span></a>
</nav><main class="content"><div class="page-head"><div><h1>Configuración</h1><p class="muted">Preferencias generales y punto central para futuras herramientas.</p></div></div>
<?php if($ok):?><div class="notice"><?=e($ok)?></div><?php endif;?><?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?>
<section class="panel" id="preferencias"><h2>Identidad de la aplicación</h2><p class="muted">Personaliza la identidad de la aplicación. Los cambios de título y color se aplican a las páginas que usan la interfaz común.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="form-grid"><div><label for="nombre_app">Nombre de la aplicación</label><input id="nombre_app" name="nombre_app" maxlength="80" required value="<?=e($settings['nombre_app'])?>"></div><div><label for="titulo_web">Título de la web (pestaña del navegador)</label><input id="titulo_web" name="titulo_web" maxlength="160" required value="<?=e($settings['titulo_web'])?>"><small>Se usa como título base en las distintas páginas.</small></div><div><label for="subtitulo_app">Texto descriptivo</label><input id="subtitulo_app" name="subtitulo_app" maxlength="120" value="<?=e($settings['subtitulo_app'])?>"></div><div><label for="color_principal">Color principal (HEX)</label><div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><input id="color_principal" name="color_principal" type="text" inputmode="text" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" placeholder="#2859a6" value="<?=e($settings['color_principal'])?>" required style="max-width:150px;font-family:monospace;text-transform:uppercase"><input id="color_selector" type="color" aria-label="Elegir color visualmente" value="<?=e($settings['color_principal'])?>" style="width:58px;height:42px;padding:3px;cursor:pointer"><small>Introduce el código hexadecimal (por ejemplo, #2859A6) o selecciónalo con el selector.</small></div></div><div><label>Vista previa del logo</label><div style="padding:12px;border:1px solid var(--line);border-radius:9px"><img src="assets/logo.svg" alt="Logo APP Alquileres" style="max-width:100%;width:270px;height:auto"></div></div></div><p class="actions" style="margin-top:18px"><button class="btn primary" type="submit">Guardar configuración</button><a class="btn outline" href="index.php">Volver al inicio</a></p></form><script>const hexInput=document.getElementById("color_principal");const colorPicker=document.getElementById("color_selector");const validHex=/^#[0-9a-fA-F]{6}$/;hexInput.addEventListener("input",()=>{if(validHex.test(hexInput.value))colorPicker.value=hexInput.value;});colorPicker.addEventListener("input",()=>{hexInput.value=colorPicker.value.toUpperCase();});</script></section>

</main></div></body></html>