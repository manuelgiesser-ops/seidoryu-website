<?php
declare(strict_types=1);
define('SEIDORYU_EDITOR',true);define('SEIDORYU_MEDIA',true);
require __DIR__.'/redaktion/backend.php';require __DIR__.'/redaktion/media-library.php';
header('X-Content-Type-Options: nosniff');header('X-Robots-Tag: noindex, noarchive');
try{
  $name=$_GET['f']??'';
  if(!is_string($name)||!isset(MEDIA_MAP[$name])){http_response_code(404);exit;}
  if(!media_fetch($name)){header('Cache-Control: no-store');http_response_code(404);exit;}
  $file=media_dir().'/'.$name;
  $mime=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][strtolower(pathinfo($name,PATHINFO_EXTENSION))];
  header('Content-Type: '.$mime);header('Cache-Control: public, max-age=86400');header('Content-Length: '.filesize($file));readfile($file);
}catch(Throwable $e){header('Cache-Control: no-store');http_response_code(503);}
