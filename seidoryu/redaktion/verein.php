<?php
declare(strict_types=1);
define('SEIDORYU_EDITOR',true);
require __DIR__.'/backend.php';
security_headers();
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
session_begin();
$me=current_user();if(!$me)go();
$error='';$ok=false;
function valid_date(string $d):bool{return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/D',$d)&&checkdate((int)substr($d,5,2),(int)substr($d,8,2),(int)substr($d,0,4));}
try{
if($_SERVER['REQUEST_METHOD']==='POST'){
csrf();$action=clean('action',30);
if($action==='course'){
$c=['title'=>clean('title',180),'start'=>clean('start',10),'deadline'=>clean('deadline',10),'duration'=>clean('duration',50),'units'=>clean('units',20),'times'=>clean('times',50),'hours'=>clean('hours',50),'price'=>clean('price',40),'flyer'=>clean('flyer',1000,false)];
if(!valid_date($c['start'])||!valid_date($c['deadline'])||$c['deadline']<$c['start'])throw new RuntimeException('Bitte Kursdaten prüfen.');
if($c['flyer']&&!local_site_link($c['flyer']))throw new RuntimeException('Flyer-Link muss auf eine Datei der eigenen Website verweisen (/seidoryu/...).');
with_state(function(&$s)use($c){$s['course']=$c;},true);
}elseif($action==='clear_course'){with_state(function(&$s){unset($s['course']);},true);}
elseif($action==='event'){
$event=['id'=>bin2hex(random_bytes(12)),'title'=>clean('event_title',180),'date'=>clean('event_date',10),'description'=>clean('event_description',1000)];
if(!valid_date($event['date']))throw new RuntimeException('Bitte Datum prüfen.');
with_state(function(&$s)use($event){$s['events'][]=$event;},true);
}elseif($action==='delete_event'){
$id=clean('event_id',30);with_state(function(&$s)use($id){$s['events']=array_values(array_filter($s['events']??[],fn($x)=>$x['id']!==$id));},true);
}elseif($action==='inquiry_enable'){
if($me['role']!=='admin')throw new RuntimeException('Keine Berechtigung.');
$on=isset($_POST['enable']);if($on&&!isset($_POST['privacy_confirmed']))throw new RuntimeException('Datenschutz und Zuständigkeit zuerst bestätigen.');
with_state(function(&$s)use($on){$s['inquiry_enabled']=$on;},true);
}elseif($action==='delete_inquiry'){
$id=clean('inquiry_id',30);with_state(function(&$s)use($id){$s['inquiries']=array_values(array_filter($s['inquiries']??[],fn($x)=>$x['id']!==$id));},true);
}else throw new RuntimeException('Unbekannte Aktion.');
header('Location: /seidoryu/redaktion/verein.php?saved=1',true,303);exit;
}
}catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Speicherung fehlgeschlagen.';}
$s=with_state(function(&$s){$s['inquiries']=array_values(array_filter($s['inquiries']??[],fn($x)=>($x['created']??0)>time()-90*86400));return $s;},true);
$c=$s['course']??['title'=>'','start'=>'','deadline'=>'','duration'=>'3 Monate','units'=>'26','times'=>'Mi & Fr','hours'=>'18–19 Uhr','price'=>'','flyer'=>''];
$t=e($_SESSION['csrf']);$events=$s['events']??[];usort($events,fn($a,$b)=>strcmp($a['date'],$b['date']));
?><!doctype html><html lang="de-AT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Vereinsverwaltung · SeiDoRyu</title><link rel="stylesheet" href="/seidoryu/redaktion/editor.css?v=1"></head><body><header><a class="brand" href="/seidoryu/redaktion/"><span>道</span><div>SeiDoRyu<small>Redaktion</small></div></a><a class="toplink" href="/seidoryu/redaktion/">Zurück zu Beiträgen ↗</a></header><main><div class="intro"><div><div class="eyebrow">Vereinsverwaltung</div><h1>Kurse, Termine &amp; Anfragen.</h1></div></div>
<?php if($error):?><p class="notice error" role="alert"><?=e($error)?></p><?php elseif(isset($_GET['saved'])):?><p class="notice">Gespeichert.</p><?php endif;?>
<section class="panel"><h2>Aktueller Anfängerkurs</h2><p>Nur bestätigte Termine und Preise eintragen. Nach Ende des Einstiegszeitraums erscheint automatisch wieder der Hinweis „Termin auf Anfrage“.</p><form method="post"><input type="hidden" name="csrf" value="<?=$t?>"><input type="hidden" name="action" value="course"><label>Kursname<input name="title" required maxlength="180" value="<?=e($c['title'])?>"></label><div class="fields"><label>Beginn<input name="start" type="date" required value="<?=e($c['start'])?>"></label><label>Einstieg bis<input name="deadline" type="date" required value="<?=e($c['deadline'])?>"></label><label>Dauer<input name="duration" required maxlength="50" value="<?=e($c['duration'])?>"></label><label>Einheiten<input name="units" required maxlength="20" value="<?=e($c['units'])?>"></label><label>Trainingstage<input name="times" required maxlength="50" value="<?=e($c['times'])?>"></label><label>Uhrzeit<input name="hours" required maxlength="50" value="<?=e($c['hours'])?>"></label><label>Bestätigter Preis<input name="price" required maxlength="40" value="<?=e($c['price'])?>"></label><label>Flyer-Link (eigene Website)<input name="flyer" type="text" placeholder="/seidoryu/dateien/kursflyer.pdf" value="<?=e($c['flyer'])?>"></label></div><button class="primary">Kurs veröffentlichen</button></form><?php if(isset($s['course'])):?><form method="post" style="margin-top:12px"><input type="hidden" name="csrf" value="<?=$t?>"><button class="quiet" name="action" value="clear_course">Kurs ausblenden</button></form><?php endif;?></section>
<section class="panel account"><h2>Veranstaltungskalender</h2><form method="post"><input type="hidden" name="csrf" value="<?=$t?>"><input type="hidden" name="action" value="event"><div class="fields"><label>Veranstaltung<input name="event_title" required maxlength="180"></label><label>Datum<input name="event_date" type="date" required></label></div><label>Beschreibung<textarea name="event_description" required rows="3" maxlength="1000"></textarea></label><button class="primary">Termin veröffentlichen</button></form><?php foreach($events as $event):?><div class="user-row"><span><?=e($event['date'])?> · <?=e($event['title'])?></span><form method="post"><input type="hidden" name="csrf" value="<?=$t?>"><input type="hidden" name="event_id" value="<?=e($event['id'])?>"><button class="quiet" name="action" value="delete_event">Entfernen</button></form></div><?php endforeach;?></section>
<section class="panel account"><h2>Probetraining-Anfragen</h2><p>Direkt übermittelte Anfragen erscheinen ausschließlich hier. Bitte regelmäßig prüfen und bearbeiten. Daten werden bei Aufruf dieser Verwaltung nach 90 Tagen entfernt; bearbeitete Anfragen können sofort gelöscht werden.</p><?php if($me['role']==='admin'):?><form method="post"><input type="hidden" name="csrf" value="<?=$t?>"><input type="hidden" name="action" value="inquiry_enable"><label class="check"><input type="checkbox" name="enable" <?=!empty($s['inquiry_enabled'])?'checked':''?>> Online-Anfragen aktivieren</label><label class="check"><input type="checkbox" name="privacy_confirmed"> Vollständiger Datenschutz ist freigegeben; regelmäßige Bearbeitung und Löschung sind organisiert.</label><button class="primary">Einstellung speichern</button></form><?php endif;?><?php foreach(array_reverse($s['inquiries']??[]) as $i):?><div class="post-row"><strong><?=e($i['name'])?></strong><p><?=e(date('d.m.Y H:i',$i['created']))?> · <?=e($i['interest'])?><br><?=e($i['email'])?><br><?=e($i['phone'])?></p><p style="white-space:pre-wrap"><?=e($i['message'])?></p><a class="quiet" href="mailto:<?=e(rawurlencode($i['email']))?>">Antworten</a><form method="post" style="display:inline-block"><input type="hidden" name="csrf" value="<?=$t?>"><input type="hidden" name="inquiry_id" value="<?=e($i['id'])?>"><button class="quiet" name="action" value="delete_inquiry">Löschen</button></form></div><?php endforeach;?><?php if(empty($s['inquiries'])):?><p>Keine Anfragen vorhanden.</p><?php endif;?></section></main><footer>SeiDoRyu · Interner Redaktionsbereich</footer></body></html>