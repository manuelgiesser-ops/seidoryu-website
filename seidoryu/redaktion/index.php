<?php
declare(strict_types=1);
define('SEIDORYU_EDITOR',true);
require __DIR__.'/backend.php';
security_headers();
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' blob: data:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
session_begin();
$error='';$ready=true;$me=null;$state=['users'=>[],'posts'=>[]];
try {
    $state=with_state(fn($s)=>$s);$me=current_user();
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();$action=clean('action',40);
        if($action==='login'){
            if(login_attempt(clean('username',32),clean('password',72),clean('token',100,false)))go();
            throw new RuntimeException('Anmeldung fehlgeschlagen. Bitte Eingaben prüfen. Nach mehreren Versuchen 15 Minuten warten.');
        }
        if(!$me)throw new RuntimeException('Bitte zuerst anmelden.');
        if($action==='logout'){$_SESSION=[];session_destroy();go();}
        if($action==='save'){
            $id=clean('id',100,false);$title=clean('title',180);$date=clean('date',10);$category=clean('category',80);
            $excerpt=clean('excerpt',600);$body=clean('body',60000);$alt=clean('image_alt',250,false);$status=clean('status',12);
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))||!in_array($status,['draft','published'],true))throw new RuntimeException('Bitte Datum und Status prüfen.');
            $links=[];foreach(preg_split('/\R/',clean('links',5000,false)) as $line){if(trim($line)==='')continue;$pair=explode('|',$line,2);$url=trim($pair[1]??'');$label=trim($pair[0]);if(!$label||!local_site_link($url))throw new RuntimeException('Links bitte im Format Titel | /seidoryu/... eingeben.');$links[]=['label'=>$label,'url'=>$url];}
            $remove=isset($_POST['remove_image']);$image=save_image();
            $id=with_state(function(&$s)use($id,$title,$date,$category,$excerpt,$body,$alt,$status,$links,$image,$remove){
                $idx=null;foreach($s['posts'] as $i=>$p)if($p['id']===$id)$idx=$i;
                if($id!==''&&$idx===null)throw new RuntimeException('Beitrag nicht gefunden.');
                $old=$idx!==null?$s['posts'][$idx]:[];$newId=$id?:bin2hex(random_bytes(12));$cover=$image??($remove?'':($old['image']??''));
                if($cover!==''&&$alt==='')throw new RuntimeException('Bitte einen Alternativtext für das Bild angeben.');
                $p=['id'=>$newId,'title'=>$title,'date'=>$date,'category'=>$category,'kanji'=>$old['kanji']??'道','archived'=>isset($_POST['archived']),'excerpt'=>$excerpt,'paragraphs'=>array_values(array_filter(array_map('trim',preg_split('/\R\s*\R/',$body)))),'links'=>$links,'status'=>$status,'image'=>$cover,'image_alt'=>$alt];
                if($idx===null)$s['posts'][]=$p;else $s['posts'][$idx]=$p;return $newId;
            },true);go('?edit='.rawurlencode($id).'&saved=1');
        }
        if($action==='account'){
            if($me['role']!=='admin')throw new RuntimeException('Keine Berechtigung.');
            $name=strtolower(clean('new_username',32));$pw=clean('new_password',72);
            if(!preg_match('/^[a-z0-9_-]{3,32}$/D',$name)||strlen($pw)<14)throw new RuntimeException('Benutzername: 3–32 Zeichen a-z, Zahlen, _ oder -. Passwort mindestens 14 Zeichen.');
            with_state(function(&$s)use($name,$pw){if(isset($s['users'][$name]))throw new RuntimeException('Dieses Konto existiert bereits.');$s['users'][$name]=['role'=>'editor','hash'=>password_hash($pw,PASSWORD_DEFAULT),'version'=>bin2hex(random_bytes(16))];},true);go('?account=1');
        }
        if($action==='revoke'){
            if($me['role']!=='admin')throw new RuntimeException('Keine Berechtigung.');$name=clean('target',32);
            with_state(function(&$s)use($name){if(($s['users'][$name]['role']??'')!=='editor')throw new RuntimeException('Konto nicht gefunden.');unset($s['users'][$name]);},true);go('?account=1');
        }
        if($action==='password'){
            $old=clean('old_password',72);$new=clean('new_password',72);if(strlen($new)<14)throw new RuntimeException('Neues Passwort mindestens 14 Zeichen.');
            with_state(function(&$s)use($me,$old,$new){$u=&$s['users'][$me['name']];if(!password_verify($old,$u['hash']))throw new RuntimeException('Aktuelles Passwort nicht korrekt.');$u['hash']=password_hash($new,PASSWORD_DEFAULT);$u['version']=bin2hex(random_bytes(16));$_SESSION['version']=$u['version'];},true);session_regenerate_id(true);go('?password=1');
        }
        throw new RuntimeException('Unbekannte Aktion.');
    }
} catch(Throwable $x) { $error=$x instanceof RuntimeException?$x->getMessage():'Redaktionsspeicher nicht verfügbar.';if(!$state['users']&&!$me){try{data_dir();}catch(Throwable $ignored){$ready=false;}} }
$edit=is_string($_GET['edit']??null)?$_GET['edit']:'';
$p=['id'=>'','title'=>'','date'=>date('Y-m-d'),'category'=>'Vereinsleben','excerpt'=>'','paragraphs'=>[],'links'=>[],'status'=>'draft','archived'=>false,'image'=>'','image_alt'=>''];
if($me)foreach($state['posts'] as $entry)if($entry['id']===$edit)$p=$entry;
$token=e($_SESSION['csrf']);
?><!doctype html><html lang="de-AT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>SeiDoRyu · Redaktion</title><link rel="stylesheet" href="/seidoryu/redaktion/editor.css?v=1"><script src="/seidoryu/redaktion/editor.js?v=1" defer></script></head><body>
<header><a class="brand" href="/seidoryu/"><span>道</span><div>SeiDoRyu<small>Redaktion</small></div></a><a class="toplink" href="/seidoryu/blog/">Zum Blog ↗</a><?php if($me):?><form method="post"><input type="hidden" name="csrf" value="<?=$token?>"><button class="quiet" name="action" value="logout">Abmelden</button></form><?php endif;?></header>
<main>
<?php if($error):?><div class="notice error" role="alert"><?=e($error)?></div><?php endif;?>
<?php if(!$me):?>
<section class="login panel"><div class="eyebrow">Geschützter Bereich</div><h1><?=$state['users']?'Willkommen zurück.':'Redaktion einrichten.'?></h1>
<?php if($ready):?><p><?=$state['users']?'Melde dich mit deinem Redaktionskonto an.':'Einmalig mit dem Einrichtungscode ein persönliches Administratorkonto anlegen.'?></p><form method="post"><input type="hidden" name="csrf" value="<?=$token?>"><input type="hidden" name="action" value="login">
<?php if(!$state['users']):?><label>Einrichtungscode<input name="token" type="password" required autocomplete="off" maxlength="100"></label><?php endif;?>
<label>Benutzername<input name="username" required autocomplete="username" maxlength="32"></label><label>Passwort<?=$state['users']?'':' (mindestens 14 Zeichen)'?><input type="password" name="password" required maxlength="72" autocomplete="<?=$state['users']?'current-password':'new-password'?>"></label><button class="primary"><?=$state['users']?'Anmelden':'Konto einrichten'?></button></form><?php else:?><p>Bitte den Hosting-Speicher einrichten. Die Anmeldung bleibt aus Sicherheitsgründen gesperrt.</p><?php endif;?></section>
<?php else:?>
<div class="intro"><div><div class="eyebrow">Vereinsblog · Redaktion</div><h1>Beiträge verwalten.</h1><p>Angemeldet als <?=e($me['name'])?> · <?=e($me['role']==='admin'?'Administration':'Redaktion')?></p></div><a class="primary" href="/seidoryu/redaktion/">+ Neuer Beitrag</a></div>
<?php if(isset($_GET['saved'])):?><div class="notice">Beitrag gespeichert.</div><?php endif;?>
<?php if(isset($_GET['account'])):?><div class="notice">Konten aktualisiert.</div><?php endif;?>
<?php if(isset($_GET['password'])):?><div class="notice">Passwort geändert.</div><?php endif;?>
<div class="panel" style="margin-bottom:20px"><a class="primary" href="/seidoryu/redaktion/verein.php">Kurse, Termine &amp; Anfragen verwalten ↗</a><?php if($me['role']==='admin'):?> <a class="quiet" href="/seidoryu/redaktion/fotoshooting.php">Website-Bilder verwalten ↗</a><?php endif;?></div><div class="workspace"><aside class="panel"><h2>Alle Beiträge</h2><?php $sorted=$state['posts'];usort($sorted,fn($a,$b)=>strcmp($b['date'],$a['date']));foreach($sorted as $post):?><a class="post-row <?=$post['id']===$edit?'active':''?>" href="?edit=<?=e(rawurlencode($post['id']))?>"><span class="post-title"><?=e($post['title'])?></span><small><?=e($post['date'])?> · <?=$post['status']==='published'?'Veröffentlicht':'Entwurf'?></small></a><?php endforeach;?></aside>
<section class="panel"><h2><?=$p['id']?'Beitrag bearbeiten':'Neuer Beitrag'?></h2><form method="post" enctype="multipart/form-data" id="post-editor"><input type="hidden" name="csrf" value="<?=$token?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e($p['id'])?>">
<label>Titel<input required name="title" maxlength="180" value="<?=e($p['title'])?>"></label><div class="fields"><label>Datum<input type="date" required name="date" value="<?=e($p['date'])?>"></label><label>Kategorie<input required name="category" maxlength="80" list="categories" value="<?=e($p['category'])?>"><datalist id="categories"><option>Vereinsleben</option><option>Kurse</option><option>Veranstaltungen</option><option>Prüfungen</option><option>Training</option></datalist></label></div>
<label>Kurzbeschreibung<textarea required name="excerpt" maxlength="600" rows="3"><?=e($p['excerpt'])?></textarea></label><label>Beitragstext <small>Absätze mit einer Leerzeile trennen.</small><textarea required name="body" maxlength="60000" rows="11"><?=e(implode("\n\n",$p['paragraphs']))?></textarea></label>
<label>Titelbild (JPG, PNG, WebP · max. 8 MB)<input type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label><label>Bildbeschreibung / Alternativtext<input name="image_alt" maxlength="250" value="<?=e($p['image_alt']??'')?>"></label>
<?php if(!empty($p['image'])):?><img class="existing-image" id="existing-image" src="<?=e(image_url($p['image']))?>" alt="<?=e($p['image_alt']??'')?>"><label class="check"><input type="checkbox" name="remove_image"> Vorhandenes Bild entfernen</label><?php endif;?>
<label>Weiterführende Links (optional)<small>Ein Link pro Zeile: Titel | /seidoryu/...</small><textarea name="links" maxlength="5000" rows="3"><?=e(implode("\n",array_map(fn($l)=>$l['label'].' | '.$l['url'],$p['links'])))?></textarea></label>
<label class="check"><input type="checkbox" name="archived" <?=$p['archived']?'checked':''?>> Als Archivbeitrag kennzeichnen</label>
<div class="actions"><button type="button" class="quiet" id="preview-toggle">Vorschau</button><button class="quiet" name="status" value="draft">Entwurf speichern</button><button class="primary" name="status" value="published">Veröffentlichen</button></div><p class="hint">Entwürfe sind nicht öffentlich sichtbar. „Entwurf speichern“ nimmt einen bereits veröffentlichten Beitrag wieder aus dem Blog.</p></form>
<section id="preview" class="preview" hidden aria-live="polite"><div class="eyebrow">Vorschau · nicht öffentlich</div><h2 id="preview-title"></h2><div id="preview-meta"></div><img id="preview-image" hidden alt=""><p id="preview-excerpt"></p><div id="preview-body"></div></section></section></div>
<?php if($me['role']==='admin'):?><section class="panel account"><h2>Trainerkonten</h2><p>Lege für jede berechtigte Person ein eigenes Konto an. Konten können bei Bedarf wieder entzogen werden.</p><form method="post"><input type="hidden" name="csrf" value="<?=$token?>"><div class="fields"><label>Benutzername<input name="new_username" required maxlength="32"></label><label>Startpasswort (mind. 14 Zeichen)<input type="password" name="new_password" required minlength="14" maxlength="72" autocomplete="new-password"></label></div><button class="primary" name="action" value="account">Redaktionskonto anlegen</button></form><?php foreach($state['users'] as $name=>$u):?><div class="user-row"><span><?=e($name)?> · <?=e($u['role'])?></span><?php if($u['role']==='editor'):?><form method="post"><input type="hidden" name="csrf" value="<?=$token?>"><input type="hidden" name="target" value="<?=e($name)?>"><button class="quiet" name="action" value="revoke">Zugang entziehen</button></form><?php endif;?></div><?php endforeach;?></section><?php endif;?>
<section class="panel account"><h2>Eigenes Passwort ändern</h2><form method="post"><input type="hidden" name="csrf" value="<?=$token?>"><div class="fields"><label>Aktuelles Passwort<input type="password" name="old_password" required maxlength="72" autocomplete="current-password"></label><label>Neues Passwort<input type="password" name="new_password" required minlength="14" maxlength="72" autocomplete="new-password"></label></div><button class="quiet" name="action" value="password">Passwort ändern</button></form></section>
<?php endif;?>
</main><footer>SeiDoRyu · Interner Redaktionsbereich</footer></body></html>