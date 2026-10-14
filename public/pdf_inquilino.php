<?php
require __DIR__ . '/../app/bootstrap.php'; require_login();
$id=(int)($_GET['id']??0);$s=$pdo->prepare('SELECT * FROM inquilinos WHERE id=?');$s->execute([$id]);$t=$s->fetch();if(!$t){http_response_code(404);exit('Inquilino no encontrado');}
$s=$pdo->prepare('SELECT fecha,tipo,concepto,importe FROM cargos WHERE inquilino_id=? ORDER BY fecha,id');$s->execute([$id]);$charges=$s->fetchAll();
$s=$pdo->prepare('SELECT fecha,concepto,importe,metodo,origen FROM ingresos WHERE inquilino_id=? ORDER BY fecha,id');$s->execute([$id]);$incomes=$s->fetchAll();
$s=$pdo->prepare('SELECT * FROM fianzas WHERE inquilino_id=? ORDER BY fecha,id');$s->execute([$id]);$deposits=$s->fetchAll();
$tc=array_sum(array_column($charges,'importe'));$ti=array_sum(array_column($incomes,'importe'));$bal=$ti-$tc;
$lines=['APP ALQUILERES - FICHA DE INQUILINO','Generado: '.date('d/m/Y H:i'),'','INQUILINO: '.$t['nombre'],'DNI/NIE: '.$t['dni_nie'].' | Telefono: '.$t['telefono'],'Email: '.$t['email'],'Unidad: '.$t['unidad'],'Entrada: '.$t['fecha_entrada'].' | Salida: '.$t['fecha_salida'].' | Estado: '.$t['estado'],'Alquiler mensual: '.money($t['alquiler_mensual']),'','RESUMEN FINANCIERO','Total cargos: '.money($tc),'Total ingresos: '.money($ti),'Balance (ingresos - cargos): '.money($bal),'','CARGOS'];
foreach($charges as $r){$lines[]=$r['fecha'].' | '.$r['tipo'].' | '.$r['concepto'].' | '.money($r['importe']);}
$lines[]='';$lines[]='INGRESOS';
foreach($incomes as $r){$lines[]=$r['fecha'].' | '.$r['concepto'].' | '.$r['metodo'].' | '.money($r['importe']).($r['origen']!=='NORMAL'?' ['.$r['origen'].']':'');}
$lines[]='';$lines[]='FIANZAS Y DANOS';
foreach($deposits as $r){$lines[]=$r['fecha'].' | Pagada '.money($r['pagada']).' | Devolver '.money($r['a_devolver']).' | Resto '.money($r['resto']).' | '.$r['estado'];if($r['detalle_retencion'])$lines[]='Detalle: '.$r['detalle_retencion'];}
if(trim((string)$t['notas'])!==''){$lines[]='';$lines[]='NOTAS';foreach(explode("\n",wordwrap((string)$t['notas'],85,"\n",true)) as $line)$lines[]=$line;}
$wrapped=[];foreach($lines as $line){$parts=explode("\n",wordwrap((string)$line,92,"\n",true));foreach($parts as $part)$wrapped[]=$part;}
$pages=array_chunk($wrapped,50);if(!$pages)$pages=[['APP ALQUILERES']];
function pdfstr(string $s): string { $s=function_exists('iconv')?(iconv('UTF-8','Windows-1252//TRANSLIT',$s)?:$s):$s;return str_replace(['\\','(',')',"\r"],['\\\\','\\(','\\)',''],$s); }
$objects=[];$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';$pageRefs=[];
foreach($pages as $pi=>$pageLines){$pageObj=4+$pi*2;$contentObj=$pageObj+1;$pageRefs[]=$pageObj.' 0 R';$stream="BT\n/F1 10 Tf\n45 755 Td\n";foreach($pageLines as $li=>$line){if($li>0)$stream.="0 -14 Td\n";$stream.='('.pdfstr($line).") Tj\n";}$stream.="ET\n";$objects[$pageObj]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentObj.' 0 R >>';$objects[$contentObj]="<< /Length ".strlen($stream)." >>\nstream\n".$stream."endstream";}
$objects[2]='<< /Type /Pages /Kids ['.implode(' ',$pageRefs).'] /Count '.count($pages).' >>';ksort($objects);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];
foreach($objects as $num=>$obj){$offsets[$num]=strlen($pdf);$pdf.=$num." 0 obj\n".$obj."\nendobj\n";}
$xref=strlen($pdf);$max=max(array_keys($objects));$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]??0);$pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="inquilino_'.$id.'_'.date('Ymd').'.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;
