<?php
declare(strict_types=1);
define('SEIDORYU_EDITOR',true);
require __DIR__.'/redaktion/backend.php';
security_headers();
header('Content-Type: application/json; charset=utf-8');
try {
  if ($_SERVER['REQUEST_METHOD']!=='GET') {http_response_code(405);exit;}
  $data=with_state(function($s){
    $c=$s['course']??null;
    $today=date('Y-m-d');
    if(!$c||($c['deadline']??'')<$today)$c=null;
    $events=array_values(array_filter($s['events']??[],fn($x)=>($x['date']??'')>=$today));
    if($c)$events[]=['title'=>$c['title'],'date'=>$c['start'],'description'=>'Anfängerkurs · '.$c['times'].' · '.$c['hours'],'id'=>'course'];
    usort($events,fn($a,$b)=>strcmp($a['date'],$b['date']));
    return ['course'=>$c,'events'=>$events,'inquiryEnabled'=>!empty($s['inquiry_enabled'])];
  });
  echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
} catch(Throwable $e){http_response_code(503);echo '{"error":"Nicht verfügbar"}';}
