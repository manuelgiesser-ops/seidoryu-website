<?php
declare(strict_types=1);
if(!defined('SEIDORYU_MEDIA')){http_response_code(404);exit;}
const MEDIA_MAP = [
    'logo-200-150x150.png' => '/wp-content/uploads/2019/09/logo-200-150x150.png',
    'Stefan-11.jpg' => '/wp-content/uploads/2020/01/Stefan-11.jpg',
    'Stefan-15.jpg' => '/wp-content/uploads/2020/01/Stefan-15.jpg',
    'Stefan-5.jpg' => '/wp-content/uploads/2020/01/Stefan-5.jpg',
    'michael-tekki-1-4c.jpg' => '/wp-content/uploads/2019/09/michael-tekki-1-4c.jpg',
    'dan-team-4c.jpg' => '/wp-content/uploads/2019/09/dan-team-4c.jpg',
    'Kodo-Shoto-Uke.jpg' => '/wp-content/uploads/2019/09/Kodo-Shoto-Uke.jpg',
    'michael-benni-kumite-5.jpg' => '/wp-content/uploads/2019/09/michael-benni-kumite-5.jpg',
    'frederic-nicki-kumite-4.jpg' => '/wp-content/uploads/2019/09/frederic-nicki-kumite-4.jpg',
    'Kodo.jpg' => '/wp-content/uploads/2019/09/Kodo.jpg',
    'Gendo-1.jpg' => '/wp-content/uploads/2023/12/Gendo-1.jpg',
    'Frederic.jpg' => '/wp-content/uploads/2019/09/Frederic.jpg',
    'Stefan-4.jpg' => '/wp-content/uploads/2020/01/Stefan-4.jpg',
    'Sophia.jpg' => '/wp-content/uploads/2026/07/Sophia.jpg',
    'benni-michael-kumite-2.jpg' => '/wp-content/uploads/2019/09/benni-michael-kumite-2.jpg',
    'dan-team-seiza-4c.jpg' => '/wp-content/uploads/2019/09/dan-team-seiza-4c.jpg',
    'kodo-tsuki-4c.jpg' => '/wp-content/uploads/2019/09/kodo-tsuki-4c.jpg',
    'hans-shizentai-4c.jpg' => '/wp-content/uploads/2019/09/hans-shizentai-4c.jpg',
    'benni-frederic-kumite-2.jpg' => '/wp-content/uploads/2019/09/benni-frederic-kumite-2.jpg',
    'benni-shizentai-4c.jpg' => '/wp-content/uploads/2019/09/benni-shizentai-4c.jpg',
    'dan-team.jpg' => '/wp-content/uploads/2019/09/dan-team.jpg',
    'nicki-shizentai-4c.jpg' => '/wp-content/uploads/2019/09/nicki-shizentai-4c.jpg',
    'frederic-shoto-uke-bw.jpg' => '/wp-content/uploads/2019/09/frederic-shoto-uke-bw.jpg',
    'nicki-yoko-geri-bw.jpg' => '/wp-content/uploads/2019/09/nicki-yoko-geri-bw.jpg',
    'frederic-nicki-kumite-3.jpg' => '/wp-content/uploads/2019/09/frederic-nicki-kumite-3.jpg',
    'benni-michael-kumite-3.jpg' => '/wp-content/uploads/2019/09/benni-michael-kumite-3.jpg',
    'Nick.jpg' => '/wp-content/uploads/2019/09/Nick.jpg',
    'Benni.jpg' => '/wp-content/uploads/2019/09/Benni.jpg',
    'Michael.jpg' => '/wp-content/uploads/2019/09/Michael.jpg',
    'Hans.jpg' => '/wp-content/uploads/2019/09/Hans.jpg',
    'Nicki-Geri.jpg' => '/wp-content/uploads/2019/09/Nicki-Geri.jpg',
    'hans-shoto-uke-bw.jpg' => '/wp-content/uploads/2019/09/hans-shoto-uke-bw.jpg',
    'benni-tate-bw.jpg' => '/wp-content/uploads/2019/09/benni-tate-bw.jpg',
    'michael-shoto-uke-4c.jpg' => '/wp-content/uploads/2019/09/michael-shoto-uke-4c.jpg',
    'Michael-Tekki.jpg' => '/wp-content/uploads/2019/09/Michael-Tekki.jpg',
    'nicki-freddi-1.jpg' => '/wp-content/uploads/2019/09/nicki-freddi-1.jpg',
    'frederic-nicki-kumite-6.jpg' => '/wp-content/uploads/2019/09/frederic-nicki-kumite-6.jpg',
    'frederic-nicki-kumite-8.jpg' => '/wp-content/uploads/2019/09/frederic-nicki-kumite-8.jpg',
    'frederic-nicki-kumite-5.jpg' => '/wp-content/uploads/2019/09/frederic-nicki-kumite-5.jpg',
    'benni-frederic-kumite-3.jpg' => '/wp-content/uploads/2019/09/benni-frederic-kumite-3.jpg',
    'michael-benni-kumite-1.jpg' => '/wp-content/uploads/2019/09/michael-benni-kumite-1.jpg',
    'michael-benni-kumite-2.jpg' => '/wp-content/uploads/2019/09/michael-benni-kumite-2.jpg',
    'michael-benni-kumite-4.jpg' => '/wp-content/uploads/2019/09/michael-benni-kumite-4.jpg',
    'michael-benni-kumite-3.jpg' => '/wp-content/uploads/2019/09/michael-benni-kumite-3.jpg',
    'nicki-frederic-kumite-5.jpg' => '/wp-content/uploads/2019/09/nicki-frederic-kumite-5.jpg',
    'Michael-Shisentei-1.jpg' => '/wp-content/uploads/2019/09/Michael-Shisentei-1.jpg',
];
function media_dir():string {
  $dir=data_dir().'/site-media';
  if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Medienspeicher nicht verfügbar.');
  return $dir;
}
function media_valid(string $bytes,string $name):bool {
  if(strlen($bytes)<10||strlen($bytes)>12*1024*1024)return false;
  $i=@getimagesizefromstring($bytes);
  $expected=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][strtolower(pathinfo($name,PATHINFO_EXTENSION))]??'';
  return $i&&$i['mime']===$expected&&$i[0]*$i[1]<=40000000;
}
function media_save(string $name,string $bytes):bool {
  if(!isset(MEDIA_MAP[$name])||!media_valid($bytes,$name))return false;
  $dir=media_dir();$tmp=tempnam($dir,'image-');if(!$tmp)return false;
  try {if(file_put_contents($tmp,$bytes)!==strlen($bytes))return false;chmod($tmp,0600);return rename($tmp,$dir.'/'.$name);}
  finally{if(is_file($tmp))@unlink($tmp);}
}
function media_fetch(string $name):bool {
  if(!isset(MEDIA_MAP[$name]))return false;
  $file=media_dir().'/'.$name;
  if(is_file($file))return true;
  if(!function_exists('curl_init'))return false;
  $lock=fopen(media_dir().'/.lock-'.hash('sha256',$name),'c');if(!$lock)return false;
  try{
    if(!flock($lock,LOCK_EX))return false;
    if(is_file($file))return true;
    $failed=media_dir().'/.failed-'.hash('sha256',$name);
    if(is_file($failed)&&filemtime($failed)>time()-300)return false;
    $bytes='';$ch=curl_init('https://www.seidoryu.at'.MEDIA_MAP[$name]);
    curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'SeiDoRyu media migration/1.0',CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$bytes){if(strlen($bytes)+strlen($chunk)>12*1024*1024)return 0;$bytes.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if($ok&&$status===200&&media_save($name,$bytes)){@unlink($failed);return true;}
    @touch($failed);return false;
  }finally{flock($lock,LOCK_UN);fclose($lock);}
}
