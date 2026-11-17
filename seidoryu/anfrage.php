<?php
declare(strict_types=1);
define('SEIDORYU_EDITOR',true);
require __DIR__.'/redaktion/backend.php';
security_headers();header('Content-Type: application/json; charset=utf-8');
try{
session_begin();
if(!with_state(fn($s)=>!empty($s['inquiry_enabled'])))throw new RuntimeException('Online-Anfragen derzeit deaktiviert.');
if($_SERVER['REQUEST_METHOD']==='GET'){echo json_encode(['csrf'=>$_SESSION['csrf']]);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
csrf();
$i=['id'=>bin2hex(random_bytes(12)),'created'=>time(),'name'=>clean('name',180),'email'=>clean('email',250),'phone'=>clean('phone',80,false),'interest'=>clean('interest',100),'message'=>clean('message',3000,false)];
if(!filter_var($i['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Bitte E-Mail-Adresse prüfen.');
with_state(function(&$s)use($i){
if(empty($s['inquiry_enabled']))throw new RuntimeException('Online-Anfragen deaktiviert.');
$now=time();$ip=hash('sha256',($_SERVER['REMOTE_ADDR']??'unknown').':inquiry');$s['inquiry_attempts']=array_filter($s['inquiry_attempts']??[],fn($x)=>$x['until']>$now);$a=$s['inquiry_attempts'][$ip]??['count'=>0,'until'=>$now+3600];if($a['count']>=4)throw new RuntimeException('Zu viele Anfragen. Bitte später versuchen.');$a['count']++;$s['inquiry_attempts'][$ip]=$a;
$s['inquiries']=array_values(array_filter($s['inquiries']??[],fn($x)=>($x['created']??0)>$now-90*86400));if(count($s['inquiries'])>=1000)throw new RuntimeException('Bitte direkt per E-Mail kontaktieren.');$s['inquiries'][]=$i;
},true);
$_SESSION['csrf']=bin2hex(random_bytes(32));
echo json_encode(['message'=>'Deine Anfrage wurde gespeichert. Das Trainerteam kann sie nun bearbeiten. Vielen Dank!'],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){http_response_code(400);echo json_encode(['error'=>'Anfrage nicht gespeichert. Bitte direkt per E-Mail kontaktieren.'],JSON_UNESCAPED_UNICODE);}
