<?php
declare(strict_types=1);
define('SEIDORYU_EDITOR',true);define('SEIDORYU_MEDIA',true);
require __DIR__.'/backend.php';require __DIR__.'/media-library.php';
security_headers();session_begin();$me=current_user();if(!$me)go();if($me['role']!=='admin'){http_response_code(403);exit;}
$msg='';$error='';
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf();$action=clean('action',20);
  if($action==='import'){
   $f=$_FILES['photos']??null;if(!$f||$f['error']!==UPLOAD_ERR_OK||$f['size']>100*1024*1024||!is_uploaded_file($f['tmp_name']))throw new RuntimeException('ZIP-Datei nicht verfügbar (max. 100 MB).');
   if(!class_exists('ZipArchive'))throw new RuntimeException('PHP-Zip nicht verfügbar.');
   $zip=new ZipArchive();if($zip->open($f['tmp_name'])!==true)throw new RuntimeException('ZIP konnte nicht geöffnet werden.');
   $n=0;try{for($j=0;$j<min($zip->numFiles,300);$j++){
      $stat=$zip->statIndex($j);if(!$stat||$stat['size']>12*1024*1024)continue;
      $name=preg_replace('/^\d{3}-/','',basename($stat['name']));
      if(!isset(MEDIA_MAP[$name]))continue;
      $bytes=$zip->getFromIndex($j,12*1024*1024);
      if(is_string($bytes)&&media_save($name,$bytes))$n++;
   }}finally{$zip->close();}
   $msg=$n.' passende Originaldateien lokal gespeichert.';
  }elseif($action==='fetch'){
   @set_time_limit(120);$n=0;$fail=0;$done=0;foreach(MEDIA_MAP as $name=>$path){if(is_file(media_dir().'/'.$name))continue;if($done++>=8)break;if(media_fetch($name))$n++;else$fail++;}
   $msg=$n.' zusätzliche Bilder gespeichert; '.$fail.' nicht abrufbar. Bei Bedarf erneut starten.';
  }else throw new RuntimeException('Unbekannte Aktion.');
 }
 $missing=array_keys(array_filter(MEDIA_MAP,fn($p,$name)=>!is_file(media_dir().'/'.$name),ARRAY_FILTER_USE_BOTH));
}catch(Throwable $e){$error='Medienspeicher oder Import nicht verfügbar.';$missing=array_keys(MEDIA_MAP);}
?><!doctype html><html lang="de-AT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Medienverwaltung · SeiDoRyu</title><link rel="stylesheet" href="/seidoryu/redaktion/editor.css?v=1"></head><body><header><a class="brand" href="/seidoryu/redaktion/"><span>道</span><div>SeiDoRyu<small>Redaktion</small></div></a></header><main><section class="panel"><div class="eyebrow">Website-Medien</div><h1>Originalfotos lokal speichern</h1><p><?=count(MEDIA_MAP)-count($missing)?> von <?=count(MEDIA_MAP)?> verwendeten Bilddateien liegen im eigenen Speicher.</p><?php if($msg):?><p class="notice"><?=e($msg)?></p><?php endif;?><?php if($error):?><p class="notice error"><?=e($error)?></p><?php endif;?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="action" value="import"><label>Fotoshooting-ZIP<input type="file" name="photos" accept=".zip,application/zip" required></label><button class="primary">ZIP importieren</button></form><?php if($missing):?><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="action" value="fetch"><button class="quiet">Fehlende Dateien übernehmen (je 8)</button></form><p class="hint">Noch nicht lokal gespeichert:</p><ul><?php foreach($missing as $name):?><li><?=e($name)?></li><?php endforeach;?></ul><?php else:?><p>Alle Bilder sind lokal gespeichert. Die Website benötigt für ihre Bilder keine externe Quelle mehr.</p><?php endif;?></section></main></body></html>