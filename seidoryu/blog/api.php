<?php
declare(strict_types=1);
define('SEIDORYU_EDITOR',true);
require __DIR__.'/../redaktion/backend.php';
security_headers();
try {
 if(isset($_GET['image'])){
    $name=$_GET['image'];
    if(!is_string($name)||!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/D',$name)){http_response_code(404);exit;}
    $allowed=with_state(fn($s)=>count(array_filter($s['posts'],fn($p)=>($p['status']??'')==='published'&&($p['image']??'')===$name))>0);
    if(!$allowed&&isset($_COOKIE['seidoryu_editor'])){session_begin();$allowed=current_user()!==null;session_write_close();}
    $file=data_dir().'/'.$name;
    if(!$allowed||!is_file($file)){http_response_code(404);exit;}
    $ext=pathinfo($name,PATHINFO_EXTENSION);header('Content-Type: '.(['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$ext]));header('Content-Length: '.filesize($file));readfile($file);exit;
 }
 header('Content-Type: application/json; charset=utf-8');
 $posts=with_state(function($s){$out=[];foreach($s['posts'] as $p){if(($p['status']??'')!=='published')continue;unset($p['status']);$p['image']=image_url($p['image']??'');$out[]=$p;}return $out;});
 echo json_encode($posts,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
} catch(Throwable $e){http_response_code(503);header('Content-Type: application/json; charset=utf-8');echo '{"error":"Blogspeicher nicht verfügbar"}';}
